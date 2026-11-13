<?php
declare(strict_types=1);

namespace OCA\ReidelCrm\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\IAppConfig;
use OCA\ReidelCrm\AppInfo\Application;

/**
 * @template-implements IEventListener<NodeDeletedEvent>
 */
class HugoNodeDeletedListener implements IEventListener {
	private $appConfig;

	public function __construct(IAppConfig $appConfig) {
		$this->appConfig = $appConfig;
	}

	public function handle(Event $event): void {
		if (!$event instanceof NodeDeletedEvent) {
			return;
		}

		try {
			$node = $event->getNode();
			$internalPath = $node->getPath();
			
			// $internalPath is typically /{userId}/files/Documents/hugo
			// We strip the prefix to match the format stored in Hugo projects (e.g. /Documents/hugo)
			$virtualPath = (string)preg_replace('#^/[^/]+/files(?=/|$)#', '', $internalPath);
			if ($virtualPath === '') {
				$virtualPath = '/';
			}

			$projectsJson = $this->appConfig->getValue(Application::APP_ID, 'projects', '[]');
			$projects = json_decode($projectsJson, true);
			if (!is_array($projects) || empty($projects)) {
				return;
			}

			$newProjects = [];
			$found = false;
			foreach ($projects as $project) {
				$folder = $project['folder'] ?? '';
				
				// Normalize both paths to ensure matching
				$normVirtual = rtrim($virtualPath, '/');
				$normFolder = rtrim($folder, '/');

				if ($normVirtual !== '' && $normVirtual === $normFolder) {
					$found = true;
					// Cleanup preview build
					$appPath = \OC::$server->getAppManager()->getAppPath('crmhub_nc_app');
					$dest = $appPath . '/img/preview_' . $project['id'];
					if (file_exists($dest)) {
						$this->recursiveDelete($dest);
					}
				} else {
					$newProjects[] = $project;
				}
			}

			if ($found) {
				$this->appConfig->setValue(Application::APP_ID, 'projects', json_encode($newProjects));
			}
		} catch (\Throwable $e) {
			// ignore and let Nextcloud continue
		}
	}

	private function recursiveDelete(string $dir): void {
		if (!is_dir($dir)) {
			if (file_exists($dir)) {
				unlink($dir);
			}
			return;
		}
		$files = array_diff(scandir($dir), ['.', '..']);
		foreach ($files as $file) {
			$path = "$dir/$file";
			(is_dir($path) && !is_link($path)) ? $this->recursiveDelete($path) : unlink($path);
		}
		rmdir($dir);
	}
}
