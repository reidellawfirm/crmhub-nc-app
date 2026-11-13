<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Listener;

use OCA\ReidelCrm\Service\ActivityService;
use OCA\ReidelCrm\Service\ProjectService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Share\Events\ShareCreatedEvent;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * Phase 2.B — Log a CRM activity when a project folder (or any node inside
 * a project folder up to MAX_DEPTH levels) is shared with another user/group.
 *
 * Soft-typed against OCP\Share\Events\ShareCreatedEvent so the class loads
 * even if Share is not registered as an event source on this server.
 *
 * @template-implements IEventListener<ShareCreatedEvent>
 */
class NodeSharedListener implements IEventListener {

	/** Walk up to this many ancestors looking for a project folder match. */
	private const MAX_DEPTH = 5;

	public function __construct(
		private ProjectService $projects,
		private ActivityService $activity,
		private IRootFolder $rootFolder,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof ShareCreatedEvent) {
			return;
		}

		try {
			$share = $event->getShare();
			if (!$share instanceof IShare) {
				return;
			}

			$nodeId = (int)$share->getNodeId();
			if ($nodeId <= 0) {
				return;
			}

			// Resolve the shared node from the share owner's perspective so
			// we can walk its parent chain.
			$ownerId = $share->getShareOwner();
			if (!$ownerId) {
				return;
			}
			$nodes = $this->rootFolder->getUserFolder($ownerId)->getById($nodeId);
			if (empty($nodes)) {
				return;
			}
			$node = $nodes[0];

			$current = $node instanceof Folder ? $node : $node->getParent();
			$visited = [];
			$matched = [];

			for ($depth = 0; $depth < self::MAX_DEPTH; $depth++) {
				if ($current === null) {
					break;
				}
				$cid = $current->getId();
				if (in_array($cid, $visited, true)) {
					break;
				}
				$visited[] = $cid;

				foreach ($this->projects->findProjectsByFolderNodeId($cid) as $project) {
					if (in_array($project->getId(), $matched, true)) {
						continue;
					}
					$matched[] = $project->getId();

					$shareWith = (string)($share->getSharedWith() ?? '');
					$summary = sprintf(
						'Project file/folder "%s" shared with %s',
						$node->getName(),
						$shareWith !== '' ? $shareWith : '(link/recipient unknown)',
					);

					$this->activity->log(
						contactCacheId: $project->getContactCacheId(),
						type: ActivityService::TYPE_FILE_UPLOADED, // share is a file-lifecycle event
						summary: $summary,
						projectId: $project->getId(),
						metadata: [
							'event'       => 'shared',
							'node_id'     => $nodeId,
							'node_name'   => $node->getName(),
							'share_type'  => $share->getShareType(),
							'shared_with' => $shareWith,
						],
						actorUserId: $share->getSharedBy() ?: $ownerId,
					);
				}

				try {
					$current = $current->getParent();
				} catch (\Throwable) {
					break;
				}
			}
		} catch (\Throwable $e) {
			$this->logger->debug('NodeSharedListener failed: ' . $e->getMessage(), ['exception' => $e]);
		}
	}
}
