<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

/**
 * Lightweight rule-based automation engine.
 *
 * Rules are stored in reidel_automation_rules.  process() is called by the
 * service layer after any significant CRM event and evaluates all active rules
 * whose trigger matches the event, running matching actions.
 *
 * Condition evaluation is intentionally simple (v1):
 *   - All conditions must match (implicit AND).
 *   - Each condition: { "field": "<context_key>", "op": "eq|neq|gt|lt|gte|lte|contains", "value": "<v>" }
 *   - Fields are looked up from the $context array passed to process().
 *
 * Supported triggers (any ActivityService::TYPE_* constant):
 *   contact_created, project_created, project_status_changed, project_overdue, …
 *
 * Supported actions:
 *   log_activity      — logs a TYPE_FUNNEL_EVENT CRM activity
 *   dispatch_webhook  — fires all configured webhooks for the trigger event
 */
class AutomationEngine {

	public function __construct(
		private IDBConnection $db,
		private ActivityService $activity,
		private WebhookService $webhooks,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Evaluate and execute all active rules matching $trigger for $ownerId.
	 *
	 * @param string $trigger  CRM event type (e.g. ActivityService::TYPE_CONTACT_CREATED)
	 * @param string $ownerId  NC user who triggered the event
	 * @param array  $context  Key→value context available to condition evaluation
	 */
	public function process(string $trigger, string $ownerId, array $context): void {
		try {
			$rules = $this->loadActiveRules($trigger, $ownerId);
		} catch (\Throwable $e) {
			// Automation table may not exist yet (before V0007 migration) — silent no-op.
			$this->logger->debug('AutomationEngine: cannot load rules — ' . $e->getMessage());
			return;
		}

		foreach ($rules as $rule) {
			try {
				if (!$this->matchesConditions($rule, $context)) {
					continue;
				}
				$this->executeAction($rule, $ownerId, $context);
				$this->incrementRunCount((int)$rule['id']);
			} catch (\Throwable $e) {
				$this->logger->warning(
					'AutomationEngine: rule ' . ($rule['id'] ?? '?') . ' failed — ' . $e->getMessage(),
					['exception' => $e],
				);
			}
		}
	}

	// ── Private helpers ────────────────────────────────────────────────────

	/** @return array<int, array<string,mixed>> */
	private function loadActiveRules(string $trigger, string $ownerId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('reidel_automation_rules')
			->where($qb->expr()->eq('trigger_event', $qb->createNamedParameter($trigger)))
			->andWhere($qb->expr()->eq('owner_id', $qb->createNamedParameter($ownerId)))
			->andWhere($qb->expr()->eq('is_active', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)));
		$res  = $qb->executeQuery();
		$rows = [];
		while ($row = $res->fetch()) {
			$rows[] = $row;
		}
		$res->closeCursor();
		return $rows;
	}

	/** @param array<string,mixed> $rule */
	private function matchesConditions(array $rule, array $context): bool {
		$json = $rule['conditions_json'] ?? null;
		if ($json === null || $json === '' || $json === 'null') {
			return true; // no conditions → always fire
		}
		$conditions = json_decode((string)$json, true);
		if (!is_array($conditions) || empty($conditions)) {
			return true;
		}
		foreach ($conditions as $cond) {
			if (!isset($cond['field'], $cond['op'], $cond['value'])) {
				continue; // malformed condition — skip
			}
			$fieldValue = $context[$cond['field']] ?? null;
			$ruleValue  = $cond['value'];
			$match = match ($cond['op']) {
				'eq'       => (string)$fieldValue === (string)$ruleValue,
				'neq'      => (string)$fieldValue !== (string)$ruleValue,
				'gt'       => is_numeric($fieldValue) && (float)$fieldValue >  (float)$ruleValue,
				'lt'       => is_numeric($fieldValue) && (float)$fieldValue <  (float)$ruleValue,
				'gte'      => is_numeric($fieldValue) && (float)$fieldValue >= (float)$ruleValue,
				'lte'      => is_numeric($fieldValue) && (float)$fieldValue <= (float)$ruleValue,
				'contains' => str_contains((string)$fieldValue, (string)$ruleValue),
				default    => false,
			};
			if (!$match) {
				return false;
			}
		}
		return true;
	}

	/** @param array<string,mixed> $rule */
	private function executeAction(array $rule, string $ownerId, array $context): void {
		$params = json_decode((string)($rule['params_json'] ?? '{}'), true) ?? [];

		switch ($rule['action']) {
			case 'log_activity':
				$contactId = (int)($context['contactCacheId'] ?? $context['contact_cache_id'] ?? 0);
				$projectId = isset($context['projectId']) ? (int)$context['projectId'] : null;
				if ($contactId > 0) {
					$this->activity->log(
						contactCacheId: $contactId,
						type: ActivityService::TYPE_FUNNEL_EVENT,
						summary: $params['summary'] ?? ('Automation: ' . ($rule['label'] ?? $rule['id'])),
						projectId: $projectId,
						metadata: ['rule_id' => $rule['id'], 'rule_label' => $rule['label'] ?? ''],
						actorUserId: $ownerId,
					);
				}
				break;

			case 'dispatch_webhook':
				$this->webhooks->dispatch($ownerId, (string)$rule['trigger_event'], $context);
				break;

			default:
				$this->logger->info(
					'AutomationEngine: unknown action "' . $rule['action'] . '" in rule ' . ($rule['id'] ?? '?'),
				);
		}
	}

	private function incrementRunCount(int $ruleId): void {
		// Fetch → increment → save pattern for SQLite compatibility.
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('run_count')
				->from('reidel_automation_rules')
				->where($qb->expr()->eq('id', $qb->createNamedParameter($ruleId, IQueryBuilder::PARAM_INT)));
			$res = $qb->executeQuery();
			$row = $res->fetch();
			$res->closeCursor();
			if ($row === false) {
				return;
			}
			$qb2 = $this->db->getQueryBuilder();
			$qb2->update('reidel_automation_rules')
				->set('run_count', $qb2->createNamedParameter((int)$row['run_count'] + 1, IQueryBuilder::PARAM_INT))
				->set('last_run_at', $qb2->createNamedParameter(time(), IQueryBuilder::PARAM_INT))
				->where($qb2->expr()->eq('id', $qb2->createNamedParameter($ruleId, IQueryBuilder::PARAM_INT)));
			$qb2->executeStatement();
		} catch (\Throwable) {
			// Non-critical
		}
	}
}
