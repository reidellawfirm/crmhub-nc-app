<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * V0007 — Phase 4/5 enterprise extension schema.
 *
 * New columns:
 *   reidel_projects.pipeline_stage      VARCHAR(50)  — Kanban / pipeline stage
 *   reidel_activity_log.thread_id       VARCHAR(255) — mail thread identifier for grouping
 *
 * New tables:
 *   reidel_webhooks          — outbound webhook endpoint configuration
 *   reidel_audit_log         — compliance-grade write audit trail
 *   reidel_automation_rules  — rule-based automation engine config
 */
class Version0007Date20260516000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// ── reidel_projects: pipeline_stage ──────────────────────────────
		if ($schema->hasTable('reidel_projects')) {
			$t = $schema->getTable('reidel_projects');
			if (!$t->hasColumn('pipeline_stage')) {
				$t->addColumn('pipeline_stage', Types::STRING, [
					'notnull' => false,
					'length'  => 50,
					'default' => null,
				]);
			}
		}

		// ── reidel_activity_log: thread_id ───────────────────────────────
		if ($schema->hasTable('reidel_activity_log')) {
			$t = $schema->getTable('reidel_activity_log');
			if (!$t->hasColumn('thread_id')) {
				$t->addColumn('thread_id', Types::STRING, [
					'notnull' => false,
					'length'  => 255,
					'default' => null,
				]);
			}
		}

		// ── reidel_webhooks ───────────────────────────────────────────────
		if (!$schema->hasTable('reidel_webhooks')) {
			$t = $schema->createTable('reidel_webhooks');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 8]);
			$t->addColumn('owner_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('label', Types::STRING, ['notnull' => true, 'length' => 255, 'default' => '']);
			$t->addColumn('url', Types::STRING, ['notnull' => true, 'length' => 1024]);
			$t->addColumn('secret', Types::STRING, ['notnull' => false, 'length' => 128]);
			$t->addColumn('events_json', Types::TEXT, ['notnull' => true, 'default' => '[]']);
			$t->addColumn('is_active', Types::SMALLINT, ['notnull' => true, 'default' => 1]);
			$t->addColumn('last_called_at', Types::BIGINT, ['notnull' => false]);
			$t->addColumn('last_http_status', Types::SMALLINT, ['notnull' => false]);
			$t->addColumn('fail_count', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
			$t->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$t->setPrimaryKey(['id'], 'reidel_wh_pk');
			$t->addIndex(['owner_id'], 'reidel_wh_owner');
			$t->addIndex(['is_active'], 'reidel_wh_active');
		}

		// ── reidel_audit_log ──────────────────────────────────────────────
		if (!$schema->hasTable('reidel_audit_log')) {
			$t = $schema->createTable('reidel_audit_log');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 8]);
			$t->addColumn('actor_user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('action', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('entity_type', Types::STRING, ['notnull' => true, 'length' => 32]);
			$t->addColumn('entity_id', Types::BIGINT, ['notnull' => true]);
			$t->addColumn('changes_json', Types::TEXT, ['notnull' => false]);
			$t->addColumn('ip_address', Types::STRING, ['notnull' => false, 'length' => 45]);
			$t->addColumn('occurred_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$t->setPrimaryKey(['id'], 'reidel_al_pk');
			$t->addIndex(['actor_user_id', 'occurred_at'], 'reidel_aud_actor_when');
			$t->addIndex(['entity_type', 'entity_id'], 'reidel_aud_entity');
			$t->addIndex(['occurred_at'], 'reidel_aud_when');
		}

		// ── reidel_automation_rules ────────────────────────────────────────
		if (!$schema->hasTable('reidel_automation_rules')) {
			$t = $schema->createTable('reidel_automation_rules');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 8]);
			$t->addColumn('owner_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('label', Types::STRING, ['notnull' => true, 'length' => 255, 'default' => '']);
			$t->addColumn('trigger_event', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('conditions_json', Types::TEXT, ['notnull' => false]);
			$t->addColumn('action', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('params_json', Types::TEXT, ['notnull' => false]);
			$t->addColumn('is_active', Types::SMALLINT, ['notnull' => true, 'default' => 1]);
			$t->addColumn('run_count', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$t->addColumn('last_run_at', Types::BIGINT, ['notnull' => false]);
			$t->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$t->setPrimaryKey(['id'], 'reidel_ar_pk');
			$t->addIndex(['owner_id', 'is_active'], 'reidel_ar_owner_active');
			$t->addIndex(['trigger_event'], 'reidel_ar_trigger');
		}

		return $schema;
	}
}
