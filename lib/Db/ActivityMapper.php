<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<ActivityEntry>
 */
class ActivityMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'reidel_activity_log', ActivityEntry::class);
	}

	/** @return ActivityEntry[] */
	public function findByContactId(int $contactId, int $limit = 50, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('contact_cache_id', $qb->createNamedParameter($contactId, IQueryBuilder::PARAM_INT)))
			->orderBy('occurred_at', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);
		return $this->findEntities($qb);
	}

	/** @return ActivityEntry[] */
	public function findByProjectId(int $projectId, int $limit = 50, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)))
			->orderBy('occurred_at', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);
		return $this->findEntities($qb);
	}

	/** @return ActivityEntry[] */
	public function findRecent(int $limit = 20): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->orderBy('occurred_at', 'DESC')
			->setMaxResults($limit);
		return $this->findEntities($qb);
	}

	/**
	 * Returns recent entries whose related contact is owned by $userId.
	 * Used by the Dashboard widget and the global activity feed endpoint.
	 *
	 * @param string   $userId   NC user ID — only contacts owned by this user are included.
	 * @param int      $limit    Maximum number of rows to return.
	 * @param int|null $sinceId  When provided, only return entries with id > $sinceId.
	 *                           Used by the Dashboard widget for incremental polling.
	 * @return ActivityEntry[]
	 */
	public function findRecentForOwner(string $userId, int $limit = 20, ?int $sinceId = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('a.*')
			->from($this->getTableName(), 'a')
			->innerJoin('a', 'reidel_contacts_cache', 'c', $qb->expr()->eq('a.contact_cache_id', 'c.id'))
			->where($qb->expr()->eq('c.owner_id', $qb->createNamedParameter($userId)))
			->orderBy('a.id', 'DESC')
			->setMaxResults($limit);
		// Incremental polling: only return rows inserted after the last known row.
		if ($sinceId !== null && $sinceId > 0) {
			$qb->andWhere($qb->expr()->gt('a.id', $qb->createNamedParameter($sinceId, IQueryBuilder::PARAM_INT)));
		}
		return $this->findEntities($qb);
	}

	/**
	 * Count activity entries that occurred today (midnight to midnight local) for
	 * contacts owned by this user. Used for the dashboard "Activity Today" KPI.
	 */
	public function countTodayForOwner(string $userId): int {
		$startOfDay = (int)strtotime('today');
		$endOfDay   = $startOfDay + 86400;
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('a.id', 'cnt'))
			->from($this->getTableName(), 'a')
			->innerJoin('a', 'reidel_contacts_cache', 'c', $qb->expr()->eq('a.contact_cache_id', 'c.id'))
			->where($qb->expr()->eq('c.owner_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->gte('a.occurred_at', $qb->createNamedParameter($startOfDay, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->lt('a.occurred_at', $qb->createNamedParameter($endOfDay, IQueryBuilder::PARAM_INT)));
		$res = $qb->executeQuery();
		$row = $res->fetch();
		$res->closeCursor();
		return (int)($row['cnt'] ?? 0);
	}

	public function reassignContact(int $fromContactId, int $toContactId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('contact_cache_id', $qb->createNamedParameter($toContactId, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('contact_cache_id', $qb->createNamedParameter($fromContactId, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}

	public function deleteByContactId(int $contactId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('contact_cache_id', $qb->createNamedParameter($contactId, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}

	public function deleteByProjectId(int $projectId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}

	/**
	 * Returns true if an entry with the given type, project (nullable), and
	 * exact summary string was already recorded within the last $withinSeconds
	 * seconds.  Used to deduplicate rapid-fire events.
	 *
	 * When $projectId is NULL the WHERE clause uses IS NULL so that mail
	 * activities (which have no linked project) are correctly deduplicated.
	 * Previously passing 0 would never match a real NULL row.
	 */
	public function hasRecentEntry(?int $projectId, string $type, string $summary, int $withinSeconds = 60): bool {
		$since = time() - $withinSeconds;
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->getTableName())
			->where($qb->expr()->eq('activity_type', $qb->createNamedParameter($type)))
			->andWhere($qb->expr()->eq('summary', $qb->createNamedParameter($summary)))
			->andWhere($qb->expr()->gte('occurred_at', $qb->createNamedParameter($since, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);

		// Correctly distinguish "no project" (NULL) from "project 0" (invalid).
		if ($projectId !== null) {
			$qb->andWhere($qb->expr()->eq(
				'project_id',
				$qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT),
			));
		} else {
			$qb->andWhere($qb->expr()->isNull('project_id'));
		}

		$result = $qb->executeQuery();
		$row    = $result->fetch();
		$result->closeCursor();
		return $row !== false;
	}

	/**
	 * Delete activity_log rows older than $cutoffTimestamp in safe batches so
	 * we never lock the table for an extended period.
	 *
	 * @param int $batchSize Rows deleted per iteration (default 500).
	 * @return int Total rows deleted across all batches.
	 */
	public function purgeOlderThan(int $cutoffTimestamp, int $batchSize = 500): int {
		$deleted = 0;
		do {
			// Identify the oldest $batchSize IDs to remove.
			$subQb = $this->db->getQueryBuilder();
			$subQb->select('id')
				->from($this->getTableName())
				->where($subQb->expr()->lt(
					'occurred_at',
					$subQb->createNamedParameter($cutoffTimestamp, IQueryBuilder::PARAM_INT),
				))
				->orderBy('id', 'ASC')
				->setMaxResults($batchSize);
			$res = $subQb->executeQuery();
			$ids = [];
			while ($row = $res->fetch()) {
				$ids[] = (int)$row['id'];
			}
			$res->closeCursor();

			if (empty($ids)) {
				break;
			}

			$qb = $this->db->getQueryBuilder();
			$qb->delete($this->getTableName())
				->where($qb->expr()->in(
					'id',
					$qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY),
				));
			$deleted += $qb->executeStatement();
		} while (count($ids) === $batchSize);

		return $deleted;
	}
}
