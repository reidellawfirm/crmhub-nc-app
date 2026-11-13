<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Listener;

use OCA\ReidelCrm\Service\ProjectService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeDeletedEvent;

/**
 * @template-implements IEventListener<NodeDeletedEvent>
 */
class NodeDeletedListener implements IEventListener {

	public function __construct(private ProjectService $projects) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof NodeDeletedEvent) {
			return;
		}
		try {
			$nodeId = $event->getNode()->getId();
		} catch (\Throwable) {
			return;
		}
		$this->projects->clearFolderForNode($nodeId);
	}
}
