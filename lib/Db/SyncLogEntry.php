<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getDirection()
 * @method void setDirection(string $v)
 * @method string getEntityType()
 * @method void setEntityType(string $v)
 * @method string|null getEntityId()
 * @method void setEntityId(?string $v)
 * @method string getStatus()
 * @method void setStatus(string $v)
 * @method string|null getMessage()
 * @method void setMessage(?string $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 */
class SyncLogEntry extends Entity {
	protected string $direction = 'in';
	protected string $entityType = '';
	protected ?string $entityId = null;
	protected string $status = 'success';
	protected ?string $message = null;
	protected int $createdAt = 0;

	public function __construct() {
		$this->addType('direction', 'string');
		$this->addType('entityType', 'string');
		$this->addType('entityId', 'string');
		$this->addType('status', 'string');
		$this->addType('message', 'string');
		$this->addType('createdAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'direction' => $this->direction,
			'entityType' => $this->entityType,
			'entityId' => $this->entityId,
			'status' => $this->status,
			'message' => $this->message,
			'createdAt' => $this->createdAt,
		];
	}
}
