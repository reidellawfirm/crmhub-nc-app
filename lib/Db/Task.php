<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int    getContactCacheId()
 * @method void   setContactCacheId(int $v)
 * @method string getOwnerUserId()
 * @method void   setOwnerUserId(string $v)
 * @method string getTitle()
 * @method void   setTitle(string $v)
 * @method string|null getNotes()
 * @method void   setNotes(?string $v)
 * @method string getStatus()
 * @method void   setStatus(string $v)
 * @method int|null getDueAt()
 * @method void   setDueAt(?int $v)
 * @method int    getCreatedAt()
 * @method void   setCreatedAt(int $v)
 * @method int|null getCompletedAt()
 * @method void   setCompletedAt(?int $v)
 */
class Task extends Entity {
	protected int    $contactCacheId = 0;
	protected string $ownerUserId    = '';
	protected string $title          = '';
	protected ?string $notes         = null;
	protected string $status         = 'pending';
	protected ?int   $dueAt          = null;
	protected int    $createdAt      = 0;
	protected ?int   $completedAt    = null;

	public function __construct() {
		$this->addType('contactCacheId', 'integer');
		$this->addType('dueAt', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('completedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id'             => $this->id,
			'contactCacheId' => $this->contactCacheId,
			'ownerUserId'    => $this->ownerUserId,
			'title'          => $this->title,
			'notes'          => $this->notes,
			'status'         => $this->status,
			'dueAt'          => $this->dueAt,
			'createdAt'      => $this->createdAt,
			'completedAt'    => $this->completedAt,
		];
	}
}
