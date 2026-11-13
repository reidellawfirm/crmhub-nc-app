<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Task>
 */
class TaskMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'reidel_tasks', Task::class);
	}

	public function find(int $id): Task {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/** @return Task[] */
	public function findByContactId(int $contactId, bool $includeCompleted = false): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('contact_cache_id', $qb->createNamedParameter($contactId, IQueryBuilder::PARAM_INT)));

		if (!$includeCompleted) {
			$qb->andWhere($qb->expr()->neq('status', $qb->createNamedParameter('done')));
		}

		$qb->orderBy('due_at', 'ASC')
		   ->addOrderBy('created_at', 'DESC');

		return $this->findEntities($qb);
	}

	/** @return Task[] */
	public function findOverdueForOwner(string $userId): array {
		$now = time();
		$qb  = $this->db->getQueryBuilder();
		$qb->select('t.*')
			->from($this->getTableName(), 't')
			->innerJoin('t', 'reidel_contacts_cache', 'c', $qb->expr()->eq('t.contact_cache_id', 'c.id'))
			->where($qb->expr()->eq('c.owner_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->neq('t.status', $qb->createNamedParameter('done')))
			->andWhere($qb->expr()->lt('t.due_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT)))
			->orderBy('t.due_at', 'ASC');
		return $this->findEntities($qb);
	}

	public function deleteByContactId(int $contactId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('contact_cache_id', $qb->createNamedParameter($contactId, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}
}
