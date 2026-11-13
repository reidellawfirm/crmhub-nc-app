<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getOwnerId()
 * @method void setOwnerId(string $v)
 * @method string getLabel()
 * @method void setLabel(string $v)
 * @method string getUrl()
 * @method void setUrl(string $v)
 * @method string|null getSecret()
 * @method void setSecret(?string $v)
 * @method string getEventsJson()
 * @method void setEventsJson(string $v)
 * @method int  getIsActive()
 * @method void setIsActive(int $v)
 * @method int|null getLastCalledAt()
 * @method void setLastCalledAt(?int $v)
 * @method int|null getLastHttpStatus()
 * @method void setLastHttpStatus(?int $v)
 * @method int getFailCount()
 * @method void setFailCount(int $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 */
class Webhook extends Entity {
	protected string $ownerId = '';
	protected string $label = '';
	protected string $url = '';
	protected ?string $secret = null;
	protected string $eventsJson = '[]';
	protected int $isActive = 1;
	protected ?int $lastCalledAt = null;
	protected ?int $lastHttpStatus = null;
	protected int $failCount = 0;
	protected int $createdAt = 0;

	public function __construct() {
		$this->addType('ownerId', 'string');
		$this->addType('label', 'string');
		$this->addType('url', 'string');
		$this->addType('secret', 'string');
		$this->addType('eventsJson', 'string');
		$this->addType('isActive', 'integer');
		$this->addType('lastCalledAt', 'integer');
		$this->addType('lastHttpStatus', 'integer');
		$this->addType('failCount', 'integer');
		$this->addType('createdAt', 'integer');
	}

	/** @return string[] */
	public function getEvents(): array {
		return json_decode($this->eventsJson, true) ?? [];
	}

	/** @param string[] $events */
	public function setEvents(array $events): void {
		$this->eventsJson = json_encode(array_values($events));
		$this->markFieldUpdated('eventsJson');
	}

	public function jsonSerialize(): array {
		return [
			'id'             => $this->id,
			'ownerId'        => $this->ownerId,
			'label'          => $this->label,
			'url'            => $this->url,
			'events'         => $this->getEvents(),
			'isActive'       => $this->isActive,
			'lastCalledAt'   => $this->lastCalledAt,
			'lastHttpStatus' => $this->lastHttpStatus,
			'failCount'      => $this->failCount,
			'createdAt'      => $this->createdAt,
		];
	}
}
