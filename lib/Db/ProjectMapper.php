<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Project>
 */
class ProjectMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'reidel_projects', Project::class);
	}

	/**
	 * Returns a map of status → count for all projects whose parent contact is
	 * owned by $ownerId (or has no owner).  One query, zero N+1 risk.
	 * Example return: ['active' => 7, 'pending' => 2, 'completed' => 3]
	 *
	 * @return array<string, int>
	 */
	public function countByStatusForOwner(string $ownerId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('p.status')
			->addSelect($qb->func()->count('p.id', 'cnt'))
			->from($this->getTableName(), 'p')
			->innerJoin('p', 'reidel_contacts_cache', 'c', $qb->expr()->eq('p.contact_cache_id', 'c.id'))
			->where($qb->expr()->orX(
				$qb->expr()->isNull('c.owner_id'),
				$qb->expr()->eq('c.owner_id', $qb->createNamedParameter($ownerId)),
			))
			->groupBy('p.status');
		$res = $qb->executeQuery();
		$counts = [];
		while ($row = $res->fetch()) {
			$counts[(string)($row['status'] ?? 'unknown')] = (int)($row['cnt'] ?? 0);
		}
		$res->closeCursor();
		return $counts;
	}

	public function find(int $id): Project {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/** @return Project[] */
	public function findByContactId(int $contactId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('contact_cache_id', $qb->createNamedParameter($contactId, IQueryBuilder::PARAM_INT)))
			->orderBy('updated_at', 'DESC');
		return $this->findEntities($qb);
	}

	/** @return Project[] */
	public function findByFolderNodeId(int $nodeId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('folder_node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	/** @return Project[] */
	public function findByDeckCardId(int $cardId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('deck_card_id', $qb->createNamedParameter($cardId, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	/** @return Project[] */
	public function search(?string $status = null, int $limit = 100, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())->orderBy('updated_at', 'DESC');
		if ($status !== null && $status !== '') {
			$qb->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($status)));
		}
		$qb->setMaxResults($limit)->setFirstResult($offset);
		return $this->findEntities($qb);
	}

	/**
	 * Returns all projects whose parent contact is owned by $ownerId, or whose
	 * parent contact has no owner (accessible by any authenticated user).
	 * Used to enforce per-user data isolation on the projects list endpoint.
	 *
	 * @return Project[]
	 */
	public function searchForOwner(string $ownerId, ?string $status = null, int $limit = 100, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('p.*')
			->from($this->getTableName(), 'p')
			->innerJoin('p', 'reidel_contacts_cache', 'c', $qb->expr()->eq('p.contact_cache_id', 'c.id'))
			->where($qb->expr()->orX(
				$qb->expr()->isNull('c.owner_id'),
				$qb->expr()->eq('c.owner_id', $qb->createNamedParameter($ownerId)),
			));
		if ($status !== null && $status !== '') {
			$qb->andWhere($qb->expr()->eq('p.status', $qb->createNamedParameter($status)));
		}
		$qb->orderBy('p.updated_at', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);
		return $this->findEntities($qb);
	}

	/**
	 * Full-text search on project_name with owner isolation.
	 * Includes projects whose parent contact is owned by $ownerId or has no owner.
	 *
	 * @return Project[]
	 */
	public function searchByTerm(string $term, string $ownerId, int $limit = 20, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$like = '%' . $this->db->escapeLikeParameter($term) . '%';
		$qb->select('p.*')
			->from($this->getTableName(), 'p')
			->innerJoin('p', 'reidel_contacts_cache', 'c', $qb->expr()->eq('p.contact_cache_id', 'c.id'))
			->where($qb->expr()->iLike('p.project_name', $qb->createNamedParameter($like)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('c.owner_id'),
				$qb->expr()->eq('c.owner_id', $qb->createNamedParameter($ownerId)),
			))
			->orderBy('p.updated_at', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);
		return $this->findEntities($qb);
	}

	/**
	 * Find overdue active projects: status = 'active', due_date is set and
	 * is older than $dueBefore (unix timestamp).
	 * Used by OverdueProjectJob to generate reminder activity entries.
	 *
	 * @return Project[]
	 */
	public function findOverdue(int $dueBefore, int $limit = 100): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('status', $qb->createNamedParameter('active')))
			->andWhere($qb->expr()->isNotNull('due_date'))
			->andWhere($qb->expr()->lt('due_date', $qb->createNamedParameter($dueBefore, IQueryBuilder::PARAM_INT)))
			->orderBy('due_date', 'ASC')
			->setMaxResults($limit);
		return $this->findEntities($qb);
	}

	/**
	 * Find projects linked to a Deck board (any card in that board).
	 * Used by DeckCardCreatedListener to check whether auto-provisioning is needed.
	 *
	 * @return Project[]
	 */
	public function findByDeckBoardId(int $boardId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('deck_board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	public function reassignContact(int $fromContactId, int $toContactId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('contact_cache_id', $qb->createNamedParameter($toContactId, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('contact_cache_id', $qb->createNamedParameter($fromContactId, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}

	/**
	 * Returns all active projects that have a due_date set (any date — past or future).
	 * Used by CalendarSyncJob to ensure every project with a due date has a
	 * corresponding calendar event, regardless of who owns it.
	 *
	 * @return Project[]
	 */
	public function findAllActiveWithDueDate(int $limit = 500): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('status', $qb->createNamedParameter('active')))
			->andWhere($qb->expr()->isNotNull('due_date'))
			->orderBy('due_date', 'ASC')
			->setMaxResults($limit);
		return $this->findEntities($qb);
	}

	/**
	 * Returns active projects whose due_date falls within the next $withinSeconds
	 * seconds.  Used by AnalyticsApiController::projectHealth() for "due soon" KPI.
	 *
	 * @return Project[]
	 */
	public function findDueSoon(int $withinSeconds, int $limit = 100): array {
		$now  = time();
		$ceil = $now + $withinSeconds;
		$qb   = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('status', $qb->createNamedParameter('active')))
			->andWhere($qb->expr()->isNotNull('due_date'))
			->andWhere($qb->expr()->gte('due_date', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->lte('due_date', $qb->createNamedParameter($ceil, IQueryBuilder::PARAM_INT)))
			->orderBy('due_date', 'ASC')
			->setMaxResults($limit);
		return $this->findEntities($qb);
	}
}
