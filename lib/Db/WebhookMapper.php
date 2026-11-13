<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Webhook>
 */
class WebhookMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'reidel_webhooks', Webhook::class);
	}

	/** @return Webhook[] */
	public function findByOwner(string $ownerId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('owner_id', $qb->createNamedParameter($ownerId)))
			->orderBy('created_at', 'DESC');
		return $this->findEntities($qb);
	}

	public function find(int $id): Webhook {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Returns all active webhooks subscribed to $eventType or the wildcard '*'.
	 * Filtering is done in PHP (webhook count is always very small, < 50 per user).
	 *
	 * @return Webhook[]
	 */
	public function findActiveForEvent(string $eventType): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('is_active', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)));
		$all = $this->findEntities($qb);
		return array_values(array_filter(
			$all,
			static fn(Webhook $wh) =>
				in_array($eventType, $wh->getEvents(), true)
				|| in_array('*', $wh->getEvents(), true),
		));
	}
}
