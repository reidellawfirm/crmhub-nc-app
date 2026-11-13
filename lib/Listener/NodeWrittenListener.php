<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Listener;

use OCA\ReidelCrm\AppInfo\Application;
use OCA\ReidelCrm\Service\ProjectService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\File;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

/**
 * Fires a CRM + native-NC activity entry when a file is written inside a
 * folder that is linked to a CRM project (project.folder_node_id matches the
 * file's immediate parent folder OR any ancestor up to MAX_DEPTH levels deep).
 *
 * Phase A2: Recursive ancestor walk ensures that uploads inside sub-folders
 * of a project folder are also detected and logged.
 *
 * Implements IEventListener<NodeWrittenEvent>
 */
class NodeWrittenListener implements IEventListener {

	/** Default upper bound when the admin config is not set. */
	private const DEFAULT_MAX_DEPTH = 5;
	/** Hard ceiling so a misconfigured value cannot walk the whole tree. */
	private const HARD_MAX_DEPTH = 12;

	public function __construct(
		private ProjectService $projects,
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof NodeWrittenEvent) {
			return;
		}
		try {
			$node = $event->getNode();

			// Only handle regular files, not directories.
			if (!$node instanceof File) {
				return;
			}

			$fileName = $node->getName();

			// Skip dot-files (hidden files, NC system files like .htaccess,
			// .ocdata, .DS_Store) — these are never meaningful project uploads.
			if (str_starts_with($fileName, '.')) {
				return;
			}

			// Infer the acting NC user from the filesystem path.
			// Paths have the form /uid/files/path/to/file so the first segment
			// is the owner.  Falls back to null (system / unknown actor).
			$actorUserId = null;
			$path = $node->getPath();
			if (preg_match('#^/([^/]+)/files/#', $path, $m)) {
				$actorUserId = $m[1];
			}

			// Phase A2: Walk ancestors up to the admin-configured depth.
			$maxDepth = (int)$this->appConfig->getValueString(
				Application::APP_ID,
				'file_recursion_depth',
				(string)self::DEFAULT_MAX_DEPTH,
			);
			$maxDepth = max(1, min(self::HARD_MAX_DEPTH, $maxDepth));

			// ProjectService::logFileUploaded() returns immediately when no project
			// matches the folder ID — fast no-op for most events.
			// We stop early when we reach the user's /uid/files root.
			$folder  = $node->getParent();
			$visited = [];
			for ($depth = 0; $depth < $maxDepth; $depth++) {
				if ($folder === null) {
					break;
				}
				$folderId = $folder->getId();
				// Cycle guard (should never happen but be safe)
				if (in_array($folderId, $visited, true)) {
					break;
				}
				$visited[] = $folderId;

				// Stop at or above the user's NC files root (/uid/files)
				$folderPath = $folder->getPath();
				$segments   = array_filter(explode('/', $folderPath));
				if (count($segments) <= 2) {
					// We've reached /uid/files — no project folder is this high.
					break;
				}

				$this->projects->logFileUploaded($folderId, $fileName, $actorUserId);

				try {
					$folder = $folder->getParent();
				} catch (\Throwable) {
					break;
				}
			}
		} catch (\Throwable $e) {
			$this->logger->debug('NodeWrittenListener failed: ' . $e->getMessage(), ['exception' => $e]);
		}
	}
}
