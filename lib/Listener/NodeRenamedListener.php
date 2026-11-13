<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Listener;

use OCA\ReidelCrm\Service\ProjectService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeRenamedEvent;

/**
 * @template-implements IEventListener<NodeRenamedEvent>
 */
class NodeRenamedListener implements IEventListener {

	public function __construct(private ProjectService $projects) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof NodeRenamedEvent) {
			return;
		}
		try {
			$nodeId = $event->getTarget()->getId();
		} catch (\Throwable) {
			return;
		}
		$this->projects->refreshFolderPathForNode($nodeId);
	}
}
