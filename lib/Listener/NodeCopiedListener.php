<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Listener;

use OCA\ReidelCrm\Service\ActivityService;
use OCA\ReidelCrm\Service\ProjectService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeCopiedEvent;
use OCP\Files\Folder;
use Psr\Log\LoggerInterface;

/**
 * Log a CRM activity when a folder that is linked to a project is copied.
 *
 * Phase A2 extension: mirrors NodeRenamedListener but for copy operations.
 * On copy, NC fires NodeCopiedEvent with the TARGET node already existing.
 * We look up whether the TARGET node ID belongs to any CRM project and log
 * a TYPE_FOLDER_CREATED activity (folder copied = new folder created).
 *
 * @template-implements IEventListener<NodeCopiedEvent>
 */
class NodeCopiedListener implements IEventListener {

	public function __construct(
		private ProjectService $projects,
		private ActivityService $activity,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof NodeCopiedEvent) {
			return;
		}

		// Only interested in folder copies — file copies produce NodeWrittenEvent
		// which is already handled by NodeWrittenListener.
		$target = $event->getTarget();
		if (!$target instanceof Folder) {
			return;
		}

		try {
			$targetId = $target->getId();
		} catch (\Throwable) {
			return;
		}

		// Infer actor from filesystem path (/uid/files/...).
		$actorUserId = null;
		if (preg_match('#^/([^/]+)/files/#', $target->getPath(), $m)) {
			$actorUserId = $m[1];
		}

		try {
			// ProjectService::findByFolderNodeId() returns null when there is no
			// project linked to this folder — fast path for unrelated copies.
			$project = $this->projects->findByFolderNodeId($targetId);
			if ($project === null) {
				return;
			}

			$sourceName = $event->getSource()->getName();

			$this->activity->log(
				contactCacheId: $project->getContactCacheId(),
				type: ActivityService::TYPE_FOLDER_CREATED,
				projectId: $project->getId(),
				summary: sprintf('Folder copied from "%s" to "%s"', $sourceName, $target->getName()),
				metadata: [
					'source_name'     => $sourceName,
					'target_name'     => $target->getName(),
					'target_node_id'  => $targetId,
				],
				actorUserId: $actorUserId,
			);
		} catch (\Throwable $e) {
			// Non-fatal — log and continue so the file operation is not blocked.
			$this->logger->warning(
				'NodeCopiedListener: failed to log activity: ' . $e->getMessage(),
				['exception' => $e],
			);
		}
	}
}
