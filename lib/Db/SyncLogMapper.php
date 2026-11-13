<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<SyncLogEntry>
 */
class SyncLogMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'reidel_sync_log', SyncLogEntry::class);
	}

	/** @return SyncLogEntry[] */
	public function findRecent(int $limit = 100): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->orderBy('created_at', 'DESC')
			->setMaxResults($limit);
		return $this->findEntities($qb);
	}

	public function purgeOlderThan(int $cutoffTimestamp): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->lt('created_at', $qb->createNamedParameter($cutoffTimestamp, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}
}
