<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getContactCacheId()
 * @method void setContactCacheId(int $v)
 * @method int|null getProjectId()
 * @method void setProjectId(?int $v)
 * @method string getAuthorUserId()
 * @method void setAuthorUserId(string $v)
 * @method string getContent()
 * @method void setContent(string $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 */
class Note extends Entity {
	protected int $contactCacheId = 0;
	protected ?int $projectId = null;
	protected string $authorUserId = '';
	protected string $content = '';
	protected int $createdAt = 0;

	public function __construct() {
		$this->addType('contactCacheId', 'integer');
		$this->addType('projectId', 'integer');
		$this->addType('authorUserId', 'string');
		$this->addType('content', 'string');
		$this->addType('createdAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'contactCacheId' => $this->contactCacheId,
			'projectId' => $this->projectId,
			'authorUserId' => $this->authorUserId,
			'content' => $this->content,
			'createdAt' => $this->createdAt,
		];
	}
}
