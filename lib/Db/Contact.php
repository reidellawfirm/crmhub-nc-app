<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getVcardUid()
 * @method void setVcardUid(string $v)
 * @method string|null getVcardAddressbookUri()
 * @method void setVcardAddressbookUri(?string $v)
 * @method string getEmail()
 * @method void setEmail(string $v)
 * @method string|null getFirstName()
 * @method void setFirstName(?string $v)
 * @method string|null getLastName()
 * @method void setLastName(?string $v)
 * @method string|null getPhone()
 * @method void setPhone(?string $v)
 * @method string|null getOrganization()
 * @method void setOrganization(?string $v)
 * @method string|null getOwnerId()
 * @method void setOwnerId(?string $v)
 * @method string getStatus()
 * @method void setStatus(string $v)
 * @method string|null getLeadSource()
 * @method void setLeadSource(?string $v)
 * @method string|null getPipelineStage()
 * @method void setPipelineStage(?string $v)
 * @method int|null getGhContactId()
 * @method void setGhContactId(?int $v)
 * @method int|null getGhOptinStatus()
 * @method void setGhOptinStatus(?int $v)
 * @method string|null getGhTagsJson()
 * @method void setGhTagsJson(?string $v)
 * @method string|null getGhMetaJson()
 * @method void setGhMetaJson(?string $v)
 * @method \DateTime|null getGhDateCreated()
 * @method void setGhDateCreated(?\DateTime $v)
 * @method \DateTime|null getGhLastSynced()
 * @method void setGhLastSynced(?\DateTime $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $v)
 */
class Contact extends Entity {
	protected string $vcardUid = '';
	protected ?string $vcardAddressbookUri = null;
	protected string $email = '';
	protected ?string $firstName = null;
	protected ?string $lastName = null;
	protected ?string $phone = null;
	protected ?string $organization = null;
	protected ?string $ownerId = null;
	protected string $status = 'active';
	protected ?string $leadSource    = null;
	protected ?string $pipelineStage = null;
	protected ?int $ghContactId = null;
	protected ?int $ghOptinStatus = null;
	protected ?string $ghTagsJson = null;
	protected ?string $ghMetaJson = null;
	protected ?\DateTime $ghDateCreated = null;
	protected ?\DateTime $ghLastSynced = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('vcardUid', 'string');
		$this->addType('vcardAddressbookUri', 'string');
		$this->addType('email', 'string');
		$this->addType('firstName', 'string');
		$this->addType('lastName', 'string');
		$this->addType('phone', 'string');
		$this->addType('organization', 'string');
		$this->addType('ownerId', 'string');
		$this->addType('status', 'string');
		$this->addType('leadSource', 'string');
		$this->addType('pipelineStage', 'string');
		$this->addType('ghContactId', 'integer');
		$this->addType('ghOptinStatus', 'integer');
		$this->addType('ghTagsJson', 'string');
		$this->addType('ghMetaJson', 'string');
		$this->addType('ghDateCreated', 'datetime');
		$this->addType('ghLastSynced', 'datetime');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'vcardUid' => $this->vcardUid,
			'vcardAddressbookUri' => $this->vcardAddressbookUri,
			'email' => $this->email,
			'firstName' => $this->firstName,
			'lastName' => $this->lastName,
			'phone' => $this->phone,
			'organization' => $this->organization,
			'ownerId' => $this->ownerId,
			'status'        => $this->status,
			'leadSource'    => $this->leadSource,
			'pipelineStage' => $this->pipelineStage,
			'ghContactId'   => $this->ghContactId,
			'ghTags' => $this->ghTagsJson ? json_decode($this->ghTagsJson, true) : [],
			'ghDateCreated' => $this->ghDateCreated?->format(\DateTime::ATOM),
			'ghLastSynced' => $this->ghLastSynced?->format(\DateTime::ATOM),
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
		];
	}
}
