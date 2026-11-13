<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Controller;

use OCA\ReidelCrm\Db\ProjectMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Analytics API for trend charts and project health KPIs.
 *
 * Design constraints:
 *   - All queries are owner-scoped (no cross-user leakage).
 *   - No MySQL-specific date functions — PHP aggregation used throughout for
 *     SQLite/MySQL/PostgreSQL portability.
 *   - Aggregation functions (COUNT, GROUP BY) are used where safe (no date ops).
 */
class AnalyticsApiController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IDBConnection $db,
		private ProjectMapper $projectMapper,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Activity trends: daily counts by type for the past $days days.
	 *
	 * Returns:
	 *   { labels: ['2026-05-01', ...], series: { note_added: [3,1,...], ... }, totals: { ... }, total: N }
	 */
	#[NoAdminRequired]
	public function trends(int $days = 30): DataResponse {
		$days   = max(7, min(90, $days));
		$userId = $this->currentUserId();
		if ($userId === null) {
			return new DataResponse([], Http::STATUS_UNAUTHORIZED);
		}
		$since = time() - ($days * 86400);

		$qb = $this->db->getQueryBuilder();
		$qb->select('al.occurred_at', 'al.activity_type')
			->from('reidel_activity_log', 'al')
			->innerJoin('al', 'reidel_contacts_cache', 'c',
				$qb->expr()->eq('al.contact_cache_id', 'c.id'))
			->where($qb->expr()->orX(
				$qb->expr()->isNull('c.owner_id'),
				$qb->expr()->eq('c.owner_id', $qb->createNamedParameter($userId)),
			))
			->andWhere($qb->expr()->gte('al.occurred_at', $qb->createNamedParameter($since, IQueryBuilder::PARAM_INT)))
			->orderBy('al.occurred_at', 'ASC');

		$res  = $qb->executeQuery();
		$rows = [];
		while ($row = $res->fetch()) {
			$rows[] = $row;
		}
		$res->closeCursor();

		// Build ordered day-bucket map (UTC)
		$now        = time();
		$dayBuckets = [];
		for ($i = $days - 1; $i >= 0; $i--) {
			$dayBuckets[date('Y-m-d', $now - $i * 86400)] = [];
		}

		$typeCounts = [];
		foreach ($rows as $row) {
			$day  = date('Y-m-d', (int)$row['occurred_at']);
			$type = (string)$row['activity_type'];
			if (isset($dayBuckets[$day])) {
				$dayBuckets[$day][$type] = ($dayBuckets[$day][$type] ?? 0) + 1;
			}
			$typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
		}

		// Top 5 types by volume → chart series
		arsort($typeCounts);
		$topTypes = array_keys(array_slice($typeCounts, 0, 5, true));
		$labels   = array_keys($dayBuckets);
		$series   = [];
		foreach ($topTypes as $type) {
			$series[$type] = array_map(fn($d) => $dayBuckets[$d][$type] ?? 0, $labels);
		}

		return new DataResponse([
			'labels' => $labels,
			'series' => $series,
			'totals' => array_slice($typeCounts, 0, 10, true),
			'total'  => array_sum($typeCounts),
		]);
	}

	/**
	 * Contact growth: weekly new-contact counts for the past $weeks weeks.
	 *
	 * Returns: { weeks: ['2026-W18', ...], counts: [3, 7, ...] }
	 */
	#[NoAdminRequired]
	public function contactGrowth(int $weeks = 12): DataResponse {
		$weeks  = max(4, min(52, $weeks));
		$userId = $this->currentUserId();
		if ($userId === null) {
			return new DataResponse([], Http::STATUS_UNAUTHORIZED);
		}
		$since = time() - ($weeks * 7 * 86400);

		$qb = $this->db->getQueryBuilder();
		$qb->select('created_at')
			->from('reidel_contacts_cache')
			->where($qb->expr()->orX(
				$qb->expr()->isNull('owner_id'),
				$qb->expr()->eq('owner_id', $qb->createNamedParameter($userId)),
			))
			->andWhere($qb->expr()->gte('created_at', $qb->createNamedParameter($since, IQueryBuilder::PARAM_INT)));

		$res  = $qb->executeQuery();
		$rows = [];
		while ($row = $res->fetch()) {
			$rows[] = (int)$row['created_at'];
		}
		$res->closeCursor();

		// Build ISO week-label map
		$now      = time();
		$weekData = [];
		for ($i = $weeks - 1; $i >= 0; $i--) {
			$weekData[date('Y-\WW', $now - $i * 7 * 86400)] = 0;
		}
		foreach ($rows as $ts) {
			$label = date('Y-\WW', $ts);
			if (array_key_exists($label, $weekData)) {
				$weekData[$label]++;
			}
		}

		return new DataResponse([
			'weeks'  => array_keys($weekData),
			'counts' => array_values($weekData),
		]);
	}

	/**
	 * Project health: status distribution + overdue + due-soon breakdown.
	 */
	#[NoAdminRequired]
	public function projectHealth(): DataResponse {
		$userId = $this->currentUserId();
		if ($userId === null) {
			return new DataResponse([], Http::STATUS_UNAUTHORIZED);
		}

		$statusCounts = $this->projectMapper->countByStatusForOwner($userId);
		$now     = time();
		$overdue = $this->countProjectsInRange($userId, null, $now - 1);
		$dueSoon = $this->countProjectsInRange($userId, $now, $now + 7 * 86400);

		return new DataResponse([
			'statusCounts' => $statusCounts,
			'overdueCount' => $overdue,
			'dueSoonCount' => $dueSoon,
			'total'        => array_sum($statusCounts),
		]);
	}

	/**
	 * Activity breakdown: COUNT per type for the past $days days.
	 *
	 * Uses SQL GROUP BY (count only, no date functions) — fully portable.
	 */
	#[NoAdminRequired]
	public function activityByType(int $days = 30): DataResponse {
		$days   = max(7, min(90, $days));
		$userId = $this->currentUserId();
		if ($userId === null) {
			return new DataResponse([], Http::STATUS_UNAUTHORIZED);
		}
		$since = time() - ($days * 86400);

		$qb = $this->db->getQueryBuilder();
		$qb->select('al.activity_type')
			->addSelect($qb->func()->count('al.id', 'cnt'))
			->from('reidel_activity_log', 'al')
			->innerJoin('al', 'reidel_contacts_cache', 'c',
				$qb->expr()->eq('al.contact_cache_id', 'c.id'))
			->where($qb->expr()->orX(
				$qb->expr()->isNull('c.owner_id'),
				$qb->expr()->eq('c.owner_id', $qb->createNamedParameter($userId)),
			))
			->andWhere($qb->expr()->gte('al.occurred_at', $qb->createNamedParameter($since, IQueryBuilder::PARAM_INT)))
			->groupBy('al.activity_type')
			->orderBy('cnt', 'DESC');

		$res    = $qb->executeQuery();
		$result = [];
		while ($row = $res->fetch()) {
			$result[(string)$row['activity_type']] = (int)$row['cnt'];
		}
		$res->closeCursor();

		return new DataResponse(['days' => $days, 'types' => $result]);
	}

	// ── Private helpers ────────────────────────────────────────────────────

	private function currentUserId(): ?string {
		return $this->userSession->getUser()?->getUID();
	}

	private function countProjectsInRange(string $userId, ?int $afterTs, ?int $beforeTs): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('p.id', 'cnt'))
			->from('reidel_projects', 'p')
			->innerJoin('p', 'reidel_contacts_cache', 'c',
				$qb->expr()->eq('p.contact_cache_id', 'c.id'))
			->where($qb->expr()->eq('p.status', $qb->createNamedParameter('active')))
			->andWhere($qb->expr()->isNotNull('p.due_date'))
			->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('c.owner_id'),
				$qb->expr()->eq('c.owner_id', $qb->createNamedParameter($userId)),
			));
		if ($afterTs !== null) {
			$qb->andWhere($qb->expr()->gte('p.due_date', $qb->createNamedParameter($afterTs, IQueryBuilder::PARAM_INT)));
		}
		if ($beforeTs !== null) {
			$qb->andWhere($qb->expr()->lte('p.due_date', $qb->createNamedParameter($beforeTs, IQueryBuilder::PARAM_INT)));
		}
		$res = $qb->executeQuery();
		$row = $res->fetch();
		$res->closeCursor();
		return (int)($row['cnt'] ?? 0);
	}
}
