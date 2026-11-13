<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getContactCacheId()
 * @method void setContactCacheId(int $v)
 * @method int|null getProjectId()
 * @method void setProjectId(?int $v)
 * @method string getActivityType()
 * @method void setActivityType(string $v)
 * @method string|null getSummary()
 * @method void setSummary(?string $v)
 * @method string|null getMetadataJson()
 * @method void setMetadataJson(?string $v)
 * @method string getSource()
 * @method void setSource(string $v)
 * @method int getOccurredAt()
 * @method void setOccurredAt(int $v)
 * @method string|null getThreadId()
 * @method void setThreadId(?string $v)
 */
class ActivityEntry extends Entity {
	protected int $contactCacheId = 0;
	protected ?int $projectId = null;
	protected string $activityType = '';
	protected ?string $summary = null;
	protected ?string $metadataJson = null;
	protected string $source = 'nextcloud';
	protected int $occurredAt = 0;
	protected ?string $threadId = null;

	public function __construct() {
		$this->addType('contactCacheId', 'integer');
		$this->addType('projectId', 'integer');
		$this->addType('activityType', 'string');
		$this->addType('summary', 'string');
		$this->addType('metadataJson', 'string');
		$this->addType('source', 'string');
		$this->addType('occurredAt', 'integer');
		$this->addType('threadId', 'string');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'contactCacheId' => $this->contactCacheId,
			'projectId' => $this->projectId,
			'activityType' => $this->activityType,
			'summary' => $this->summary,
			'metadata' => $this->metadataJson ? json_decode($this->metadataJson, true) : null,
			'source' => $this->source,
			'occurredAt' => $this->occurredAt,
			'threadId' => $this->threadId,
		];
	}
}
