<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Note>
 */
class NoteMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'reidel_notes', Note::class);
	}

	public function find(int $id): Note {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Count all notes for contacts owned by this user (dashboard KPI).
	 */
	public function countForOwner(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('n.id', 'cnt'))
			->from($this->getTableName(), 'n')
			->innerJoin('n', 'reidel_contacts_cache', 'c', $qb->expr()->eq('n.contact_cache_id', 'c.id'))
			->where($qb->expr()->eq('c.owner_id', $qb->createNamedParameter($userId)));
		$res = $qb->executeQuery();
		$row = $res->fetch();
		$res->closeCursor();
		return (int)($row['cnt'] ?? 0);
	}

	/** @return Note[] */
	public function findByContactId(int $contactId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('contact_cache_id', $qb->createNamedParameter($contactId, IQueryBuilder::PARAM_INT)))
			->orderBy('created_at', 'DESC');
		return $this->findEntities($qb);
	}

	/** @return Note[] */
	public function findByProjectId(int $projectId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)))
			->orderBy('created_at', 'DESC');
		return $this->findEntities($qb);
	}

	public function reassignContact(int $fromContactId, int $toContactId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('contact_cache_id', $qb->createNamedParameter($toContactId, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('contact_cache_id', $qb->createNamedParameter($fromContactId, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}

	public function deleteByProjectId(int $projectId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}

	/**
	 * Full-text search on note content with owner isolation.
	 * Includes notes whose parent contact is owned by $ownerId or has no owner.
	 *
	 * @return Note[]
	 */
	public function searchByContent(string $term, string $ownerId, int $limit = 20, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$like = '%' . $this->db->escapeLikeParameter($term) . '%';
		$qb->select('n.*')
			->from($this->getTableName(), 'n')
			->innerJoin('n', 'reidel_contacts_cache', 'c', $qb->expr()->eq('n.contact_cache_id', 'c.id'))
			->where($qb->expr()->iLike('n.content', $qb->createNamedParameter($like)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('c.owner_id'),
				$qb->expr()->eq('c.owner_id', $qb->createNamedParameter($ownerId)),
			))
			->orderBy('n.created_at', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);
		return $this->findEntities($qb);
	}

	/**
	 * Efficiently delete all notes for a contact without loading them first.
	 */
	public function deleteByContactId(int $contactId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('contact_cache_id', $qb->createNamedParameter($contactId, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}
}
