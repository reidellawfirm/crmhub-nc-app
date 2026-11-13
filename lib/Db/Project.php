<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getContactCacheId()
 * @method void setContactCacheId(int $v)
 * @method string|null getProjectType()
 * @method void setProjectType(?string $v)
 * @method string getProjectName()
 * @method void setProjectName(string $v)
 * @method string getStatus()
 * @method void setStatus(string $v)
 * @method int|null getFolderNodeId()
 * @method void setFolderNodeId(?int $v)
 * @method string|null getFolderPath()
 * @method void setFolderPath(?string $v)
 * @method int|null getDeckBoardId()
 * @method void setDeckBoardId(?int $v)
 * @method int|null getDeckCardId()
 * @method void setDeckCardId(?int $v)
 * @method string|null getAssignedUserId()
 * @method void setAssignedUserId(?string $v)
 * @method int|null getDueDate()
 * @method void setDueDate(?int $v)
 * @method string|null getCalEventUid()
 * @method void setCalEventUid(?string $v)
 * @method string|null getPipelineStage()
 * @method void setPipelineStage(?string $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $v)
 */
class Project extends Entity {
	protected int $contactCacheId = 0;
	protected ?string $projectType = null;
	protected string $projectName = '';
	protected string $status = 'active';
	protected ?int $folderNodeId = null;
	protected ?string $folderPath = null;
	protected ?int $deckBoardId = null;
	protected ?int $deckCardId = null;
	protected ?string $assignedUserId = null;
	protected ?int $dueDate = null;
	protected ?string $calEventUid = null;
	protected ?string $pipelineStage = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('contactCacheId', 'integer');
		$this->addType('projectType', 'string');
		$this->addType('projectName', 'string');
		$this->addType('status', 'string');
		$this->addType('folderNodeId', 'integer');
		$this->addType('folderPath', 'string');
		$this->addType('deckBoardId', 'integer');
		$this->addType('deckCardId', 'integer');
		$this->addType('assignedUserId', 'string');
		$this->addType('dueDate', 'integer');
		$this->addType('calEventUid', 'string');
		$this->addType('pipelineStage', 'string');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'contactCacheId' => $this->contactCacheId,
			'projectType' => $this->projectType,
			'projectName' => $this->projectName,
			'status' => $this->status,
			'folderNodeId' => $this->folderNodeId,
			'folderPath' => $this->folderPath,
			'deckBoardId' => $this->deckBoardId,
			'deckCardId' => $this->deckCardId,
			'assignedUserId' => $this->assignedUserId,
			'dueDate' => $this->dueDate,
			'calEventUid' => $this->calEventUid,
			'pipelineStage' => $this->pipelineStage,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
		];
	}
}
