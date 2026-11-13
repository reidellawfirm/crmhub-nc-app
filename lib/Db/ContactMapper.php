<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Contact>
 */
class ContactMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'reidel_contacts_cache', Contact::class);
	}

	public function find(int $id): Contact {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	public function findByVcardUid(string $uid): Contact {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('vcard_uid', $qb->createNamedParameter($uid)));
		return $this->findEntity($qb);
	}

	public function findByGhContactId(int $ghId): Contact {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('gh_contact_id', $qb->createNamedParameter($ghId, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @return Contact[]
	 */
	public function findByEmail(string $email): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('email', $qb->createNamedParameter($email)));
		return $this->findEntities($qb);
	}

	/**
	 * Finds the first contact whose email matches $email case-insensitively
	 * (uses iLike so it works on both MySQL/MariaDB and PostgreSQL).
	 * When $excludeId is provided the row with that ID is excluded, which lets
	 * the caller implement "is this email taken by a DIFFERENT contact?"
	 *
	 * Special characters in the email that are meaningful to LIKE (% and _) are
	 * escaped via escapeLikeParameter() so the comparison is exact, not a pattern.
	 */
	public function findByEmailExcluding(string $email, ?int $excludeId = null): ?Contact {
		$normalized = $this->db->escapeLikeParameter(trim($email));
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->iLike('email', $qb->createNamedParameter($normalized)))
			->setMaxResults(1);
		if ($excludeId !== null) {
			$qb->andWhere($qb->expr()->neq('id', $qb->createNamedParameter($excludeId, IQueryBuilder::PARAM_INT)));
		}
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * @param string|null $ownerId  When set, returns only contacts owned by this NC user ID
	 *                              plus contacts that have no owner (e.g. pre-mapping imports).
	 *                              Pass null (default) to return all contacts (admin / system use).
	 * @return Contact[]
	 */
	public function search(?string $query = null, ?string $status = null, int $limit = 100, int $offset = 0, ?string $ownerId = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())->orderBy('last_name', 'ASC');
		if ($query !== null && $query !== '') {
			$like = '%' . $this->db->escapeLikeParameter($query) . '%';
			$qb->andWhere($qb->expr()->orX(
				$qb->expr()->iLike('email', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('first_name', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('last_name', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('phone', $qb->createNamedParameter($like)),
			));
		}
		if ($status !== null && $status !== '') {
			$qb->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($status)));
		}
		if ($ownerId !== null) {
			// Include contacts owned by this user AND unowned contacts (system imports).
			$qb->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('owner_id'),
				$qb->expr()->eq('owner_id', $qb->createNamedParameter($ownerId)),
			));
		}
		$qb->setMaxResults($limit)->setFirstResult($offset);
		return $this->findEntities($qb);
	}

	public function findOrNullByVcardUid(string $uid): ?Contact {
		try {
			return $this->findByVcardUid($uid);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * Count contacts matching the same criteria as search() without fetching rows.
	 * Used by the API to return a `total` field so the frontend can show
	 * accurate pagination state ("showing 50 of 1 243 contacts").
	 */
	/**
	 * Count all contacts owned by this user (for the dashboard KPI card).
	 */
	public function countForOwner(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('id', 'cnt'))
			->from($this->getTableName())
			->where($qb->expr()->eq('owner_id', $qb->createNamedParameter($userId)));
		$res = $qb->executeQuery();
		$row = $res->fetch();
		$res->closeCursor();
		return (int)($row['cnt'] ?? 0);
	}

	/**
	 * Return the $limit most-recently-updated contacts for this owner.
	 * Used by the dashboard "Recent Contacts" panel.
	 *
	 * @return Contact[]
	 */
	public function findRecentForOwner(string $userId, int $limit = 5): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('owner_id', $qb->createNamedParameter($userId)))
			->orderBy('updated_at', 'DESC')
			->setMaxResults($limit);
		return $this->findEntities($qb);
	}

	public function countSearch(?string $query = null, ?string $status = null, ?string $ownerId = null): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('id', 'cnt'))->from($this->getTableName());
		if ($query !== null && $query !== '') {
			$like = '%' . $this->db->escapeLikeParameter($query) . '%';
			$qb->andWhere($qb->expr()->orX(
				$qb->expr()->iLike('email', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('first_name', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('last_name', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('phone', $qb->createNamedParameter($like)),
			));
		}
		if ($status !== null && $status !== '') {
			$qb->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($status)));
		}
		if ($ownerId !== null) {
			$qb->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('owner_id'),
				$qb->expr()->eq('owner_id', $qb->createNamedParameter($ownerId)),
			));
		}
		$res = $qb->executeQuery();
		$row = $res->fetch();
		$res->closeCursor();
		return (int)($row['cnt'] ?? 0);
	}
}
