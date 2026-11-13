<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Service;

use OCA\ReidelCrm\Db\ActivityMapper;
use OCA\ReidelCrm\Db\NoteMapper;
use OCA\ReidelCrm\Db\Project;
use OCA\ReidelCrm\Db\ProjectMapper;
use OCA\ReidelCrm\Db\ContactMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\IServerContainer;
use Psr\Log\LoggerInterface;

class ProjectService {

	public function __construct(
		private ProjectMapper $mapper,
		private IRootFolder $rootFolder,
		private ActivityService $activity,
		private LoggerInterface $logger,
		private NoteMapper $noteMapper,
		private ActivityMapper $activityMapper,
		private IDBConnection $db,
		private IServerContainer $serverContainer,
		private IAppConfig $appConfig,
		private ContactMapper $contactMapper,
		private ?CalendarService $calendar = null,
	) {
	}

	public function find(int $id): ?Project {
		try {
			return $this->mapper->find($id);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/** @return Project[] */
	public function listForContact(int $contactId): array {
		return $this->mapper->findByContactId($contactId);
	}

	/**
	 * Returns all projects linked to a specific Deck card ID.
	 * Used by DeckCardUpdatedListener and DeckCardDeletedListener.
	 *
	 * @return Project[]
	 */
	public function findByDeckCardId(int $cardId): array {
		return $this->mapper->findByDeckCardId($cardId);
	}

	public function create(int $contactId, array $data, ?string $actorUserId = null): Project {
		$project = new Project();
		$project->setContactCacheId($contactId);
		$project->setProjectName((string)($data['projectName'] ?? 'Untitled project'));
		$project->setProjectType($data['projectType'] ?? null);
		$project->setStatus($data['status'] ?? 'active');
		$project->setAssignedUserId($data['assignedUserId'] ?? null);
		$project->setDeckBoardId(isset($data['deckBoardId']) ? (int)$data['deckBoardId'] : null);
		$project->setDeckCardId(isset($data['deckCardId']) ? (int)$data['deckCardId'] : null);
		if (!empty($data['dueDate'])) {
			$project->setDueDate((int)$data['dueDate']);
		}

		if (!empty($data['folderNodeId'])) {
			$nodeId = (int)$data['folderNodeId'];
			$project->setFolderNodeId($nodeId);
			$project->setFolderPath($this->resolveFolderPath($nodeId));
		}

		$now = time();
		$project->setCreatedAt($now);
		$project->setUpdatedAt($now);
		$saved = $this->mapper->insert($project);

		// Reverse Deck integration: if a board is configured but no specific
		// card ID is supplied, auto-create a Deck card in the board's first
		// stack and persist the resulting card ID on this project.
		if ($saved->getDeckBoardId() !== null && $saved->getDeckCardId() === null) {
			$this->createDeckCard($saved);
		}

		// Phase B1: Auto-provision a project folder in NC Files if enabled.
		$autoFolder = !empty($data['autoCreateFolder'])
			|| $this->appConfig->getValueString('crmhub_nc_app', 'onboarding_auto_folder', 'no') === 'yes';
		if ($autoFolder && $saved->getFolderNodeId() === null) {
			$ownerUserId = $this->resolveContactOwner($contactId);
			if ($ownerUserId !== null) {
				$this->autoProvisionFolder($saved, $ownerUserId);
			}
		}

		// Phase C1: Create a Calendar event if a due_date is specified.
		if ($saved->getDueDate() !== null) {
			$ownerUserId = $this->resolveContactOwner($contactId);
			if ($ownerUserId !== null && $this->calendar !== null) {
				$this->calendar->upsertProjectEvent($saved, $ownerUserId);
			}
		}

		$this->activity->log(
			contactCacheId: $contactId,
			type: ActivityService::TYPE_PROJECT_CREATED,
			summary: sprintf('Project "%s" created', $saved->getProjectName()),
			projectId: $saved->getId(),
			metadata: ['type' => $saved->getProjectType()],
			actorUserId: $actorUserId,
		);
		return $saved;
	}

	public function update(int $id, array $data, ?string $actorUserId = null): ?Project {
		$project = $this->find($id);
		if ($project === null) {
			return null;
		}
		$mapping = [
			'projectName' => 'setProjectName',
			'projectType' => 'setProjectType',
			'status' => 'setStatus',
			'assignedUserId' => 'setAssignedUserId',
			'deckBoardId' => 'setDeckBoardId',
			'deckCardId' => 'setDeckCardId',
			'dueDate' => 'setDueDate',
		];
		$previousStatus = $project->getStatus();
		foreach ($mapping as $key => $setter) {
			if (array_key_exists($key, $data)) {
				$project->$setter($data[$key]);
			}
		}
		if (array_key_exists('folderNodeId', $data)) {
			$nodeId = $data['folderNodeId'] !== null ? (int)$data['folderNodeId'] : null;
			$project->setFolderNodeId($nodeId);
			$project->setFolderPath($nodeId !== null ? $this->resolveFolderPath($nodeId) : null);
		}
		$project->setUpdatedAt(time());
		$updated = $this->mapper->update($project);

		// Phase A3: When a project is closed/archived, archive the linked Deck card.
		$newStatus = $updated->getStatus();
		$closedStatuses = ['closed', 'archived', 'inactive'];
		if (in_array($newStatus, $closedStatuses, true)
			&& !in_array($previousStatus, $closedStatuses, true)
			&& $updated->getDeckCardId() !== null) {
			$this->archiveDeckCard($updated);
		}

		// Phase C1: Sync Calendar event when due_date changes.
		if (array_key_exists('dueDate', $data)) {
			$ownerUserId = $this->resolveContactOwner($updated->getContactCacheId());
			if ($ownerUserId !== null && $this->calendar !== null) {
				if ($updated->getDueDate() !== null) {
					$this->calendar->upsertProjectEvent($updated, $ownerUserId);
				} else {
					$this->calendar->deleteProjectEvent($updated, $ownerUserId);
				}
			}
		}

		$activityType = in_array($newStatus, $closedStatuses, true) && !in_array($previousStatus, $closedStatuses, true)
			? ActivityService::TYPE_PROJECT_CLOSED
			: ActivityService::TYPE_PROJECT_UPDATED;

		$this->activity->log(
			contactCacheId: $updated->getContactCacheId(),
			type: $activityType,
			summary: sprintf('Project "%s" %s', $updated->getProjectName(),
				$activityType === ActivityService::TYPE_PROJECT_CLOSED ? 'closed' : 'updated'),
			projectId: $updated->getId(),
			actorUserId: $actorUserId,
		);
		return $updated;
	}

	public function delete(int $id): bool {
		$project = $this->find($id);
		if ($project === null) {
			return false;
		}
		// Cascade: remove child notes and activity entries inside a transaction
		// so there are no orphaned rows if the project delete fails.
		$this->db->beginTransaction();
		try {
			$this->noteMapper->deleteByProjectId($id);
			$this->activityMapper->deleteByProjectId($id);
			$this->mapper->delete($project);
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			$this->logger->error('ProjectService::delete transaction failed: ' . $e->getMessage(), ['exception' => $e]);
			return false;
		}
		return true;
	}

	/**
	 * Returns all CRM projects whose folder_node_id matches $nodeId.
	 * Public façade for listeners that should not depend on ProjectMapper directly.
	 *
	 * @return Project[]
	 */
	public function findProjectsByFolderNodeId(int $nodeId): array {
		return $this->mapper->findByFolderNodeId($nodeId);
	}

	/**
	 * Returns the FIRST project linked to $nodeId, or null when no project exists.
	 * Convenience for listeners that need a single match (e.g. NodeCopiedListener).
	 */
	public function findByFolderNodeId(int $nodeId): ?Project {
		$rows = $this->mapper->findByFolderNodeId($nodeId);
		return $rows[0] ?? null;
	}

	/**
	 * Re-resolve folder_path for any project pointing at $nodeId.
	 * Called by NodeRenamedListener.
	 */
	public function refreshFolderPathForNode(int $nodeId): void {
		$path = $this->resolveFolderPath($nodeId);
		foreach ($this->mapper->findByFolderNodeId($nodeId) as $project) {
			$project->setFolderPath($path);
			$project->setUpdatedAt(time());
			$this->mapper->update($project);
			$this->activity->log(
				contactCacheId: $project->getContactCacheId(),
				type: ActivityService::TYPE_FOLDER_RENAMED,
				summary: sprintf('Project folder renamed to %s', $path ?? '(unknown)'),
				projectId: $project->getId(),
			);
		}
	}

	/**
	 * Log a file-upload activity event for any project whose linked folder
	 * is the immediate parent of the written file (matched by $folderNodeId).
	 * Called by NodeWrittenListener whenever a file is written inside NC Files.
	 */
	public function logFileUploaded(int $folderNodeId, string $fileName, ?string $actorUserId): void {
		$summary = sprintf('File "%s" uploaded to project folder', $fileName);
		foreach ($this->mapper->findByFolderNodeId($folderNodeId) as $project) {
			// Deduplicate rapid-fire events (editor autosave, desktop-sync
			// partial writes) — skip if an identical entry exists within 60 s.
				if ($this->activityMapper->hasRecentEntry(
				$project->getId(),
				ActivityService::TYPE_FILE_UPLOADED,
				$summary,
				60,
			)) {
				continue;
			}
			$this->activity->log(
				contactCacheId: $project->getContactCacheId(),
				type: ActivityService::TYPE_FILE_UPLOADED,
				summary: $summary,
				projectId: $project->getId(),
				metadata: ['file_name' => $fileName],
				actorUserId: $actorUserId,
			);
		}
	}
	/**
	 * Auto-create a Deck card in the project's configured board, then link the
	 * new card ID back to the project row.  Soft-depends on OCA\Deck.
	 */
	private function createDeckCard(Project $project): void {
		if (!class_exists(\OCA\Deck\Service\CardService::class)
			|| !class_exists(\OCA\Deck\Service\StackService::class)) {
			return;
		}
		try {
			/** @var \OCA\Deck\Service\StackService $stackService */
			$stackService = $this->serverContainer->get(\OCA\Deck\Service\StackService::class);
			/** @var \OCA\Deck\Service\CardService $cardService */
			$cardService  = $this->serverContainer->get(\OCA\Deck\Service\CardService::class);

			$boardId = $project->getDeckBoardId();
			$stacks  = $stackService->findAll($boardId);
			if (empty($stacks)) {
				$this->logger->debug(
					'ProjectService::createDeckCard: no stacks in board ' . $boardId,
				);
				return;
			}
			$stackId = $stacks[0]->getId();
			// Deck\Service\CardService::create(title, stackId, type, order, description)
			$card = $cardService->create($project->getProjectName(), $stackId, 'plain', 0, '');
			$project->setDeckCardId((int)$card->getId());
			$project->setUpdatedAt(time());
			$this->mapper->update($project);
		} catch (\Throwable $e) {
			// Non-fatal: CRM project already saved; just log the Deck failure.
			$this->logger->warning(
				'ProjectService::createDeckCard failed: ' . $e->getMessage(),
				['exception' => $e, 'project_id' => $project->getId(), 'board_id' => $project->getDeckBoardId()],
			);
		}
	}
	public function clearFolderForNode(int $nodeId): void {
		foreach ($this->mapper->findByFolderNodeId($nodeId) as $project) {
			$project->setFolderNodeId(null);
			$project->setFolderPath(null);
			$project->setUpdatedAt(time());
			$this->mapper->update($project);
			$this->activity->log(
				contactCacheId: $project->getContactCacheId(),
				type: ActivityService::TYPE_FOLDER_DELETED,
				summary: 'Project folder was deleted',
				projectId: $project->getId(),
			);
		}
	}

	private function resolveFolderPath(int $nodeId): ?string {
		try {
			$nodes = $this->rootFolder->getById($nodeId);
			if (!empty($nodes)) {
				// Node::getPath() returns the internal NC storage path:
				//   /{userId}/files/Documents/sad/ewe
				// We must strip the /{userId}/files mount prefix to store only
				// the user-visible virtual path:  /Documents/sad/ewe
				$internal = $nodes[0]->getPath();
				$virtual  = (string)preg_replace('#^/[^/]+/files(?=/|$)#', '', $internal);
				return $virtual !== '' ? $virtual : '/';
			}
		} catch (NotFoundException $e) {
			// fall through
		} catch (\Throwable $e) {
			$this->logger->debug('resolveFolderPath failed: ' . $e->getMessage());
		}
		return null;
	}

	/**
	 * Phase A3: Archive the Deck card linked to a project when the project is closed.
	 * Soft-depends on OCA\Deck — silently skips if the app is absent.
	 */
	private function archiveDeckCard(Project $project): void {
		if (!class_exists(\OCA\Deck\Service\CardService::class)) {
			return;
		}
		try {
			/** @var \OCA\Deck\Service\CardService $cardService */
			$cardService = $this->serverContainer->get(\OCA\Deck\Service\CardService::class);
			$cardService->archive($project->getDeckCardId());
			$this->activity->log(
				contactCacheId: $project->getContactCacheId(),
				type: ActivityService::TYPE_DECK_ARCHIVED,
				summary: sprintf('Deck card archived for project "%s"', $project->getProjectName()),
				projectId: $project->getId(),
				metadata: ['deck_card_id' => $project->getDeckCardId()],
			);
		} catch (\Throwable $e) {
			$this->logger->warning(
				'archiveDeckCard failed: ' . $e->getMessage(),
				['exception' => $e, 'project_id' => $project->getId()],
			);
		}
	}

	/**
	 * Phase B1: Auto-create a project folder in the owner's NC Files.
	 *
	 * Parent path is taken from the admin config `default_project_folder`
	 * (default: "CRM Projects").  The folder is named after the project and
	 * suffixed with a counter if a name collision occurs.
	 */
	private function autoProvisionFolder(Project $project, string $ownerId): void {
		try {
			$parentPath = trim(
				$this->appConfig->getValueString('crmhub_nc_app', 'default_project_folder', 'CRM Projects'),
			);
			if ($parentPath === '') {
				$parentPath = 'CRM Projects';
			}
			$userRoot = $this->rootFolder->getUserFolder($ownerId);

			// Ensure the parent CRM folder exists.
			if (!$userRoot->nodeExists($parentPath)) {
				$userRoot->newFolder($parentPath);
			}

			// Build the project folder name, deduplicate if needed.
			$folderName = $this->sanitizeFolderName($project->getProjectName());
			$basePath   = $parentPath . '/' . $folderName;
			$finalPath  = $basePath;
			$counter    = 1;
			while ($userRoot->nodeExists($finalPath)) {
				$finalPath = $basePath . ' (' . $counter++ . ')';
			}

			$folder = $userRoot->newFolder($finalPath);
			$project->setFolderNodeId($folder->getId());
			$project->setFolderPath($folder->getPath());
			$project->setUpdatedAt(time());
			$this->mapper->update($project);

			$this->activity->log(
				contactCacheId: $project->getContactCacheId(),
				type: ActivityService::TYPE_FOLDER_CREATED,
				summary: sprintf('Project folder "%s" auto-created', $finalPath),
				projectId: $project->getId(),
				metadata: ['folder_path' => $finalPath],
			);
		} catch (\Throwable $e) {
			$this->logger->warning(
				'autoProvisionFolder failed: ' . $e->getMessage(),
				['exception' => $e, 'project_id' => $project->getId()],
			);
		}
	}

	/**
	 * Look up the NC user ID that owns the contact linked to a project.
	 * Returns null if the contact has no owner or is not found.
	 */
	private function resolveContactOwner(int $contactId): ?string {
		try {
			return $this->contactMapper->find($contactId)->getOwnerId();
		} catch (\Throwable) {
			return null;
		}
	}

	/** Strip characters that are unsafe in NC folder names. */
	private function sanitizeFolderName(string $name): string {
		// Replace slashes, backslashes, colons, and control characters.
		$safe = preg_replace('/[\/\\\\:*?"<>|]/', '-', $name) ?? $name;
		$safe = trim($safe, '. ');
		return $safe !== '' ? $safe : 'Project';
	}
}
