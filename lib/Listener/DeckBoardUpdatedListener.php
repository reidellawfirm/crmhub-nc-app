<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Listener;

use OCA\ReidelCrm\Db\ProjectMapper;
use OCA\ReidelCrm\Service\ActivityService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Log a CRM activity when a Deck board is renamed or archived.
 *
 * Soft-dependency: only registered when OCA\Deck is installed.
 *
 * We look up all CRM projects whose deck_board_id matches the updated board
 * and log a TYPE_DECK_UPDATE entry for each, so changes to the board are
 * visible in the CRM timeline for every related contact.
 *
 * @template-implements IEventListener<\OCA\Deck\Event\BoardUpdatedEvent>
 */
class DeckBoardUpdatedListener implements IEventListener {

	public function __construct(
		private ProjectMapper $projectMapper,
		private ActivityService $activity,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!class_exists(\OCA\Deck\Event\BoardUpdatedEvent::class)) {
			return;
		}
		if (!$event instanceof \OCA\Deck\Event\BoardUpdatedEvent) {
			return;
		}

		try {
			$board   = $event->getBoard();
			$boardId = $board->getId();
			$title   = $board->getTitle();

			$projects = $this->projectMapper->findByDeckBoardId($boardId);
			if (empty($projects)) {
				return;
			}

			$summary = sprintf('Deck board "%s" updated', $title);

			foreach ($projects as $project) {
				$this->activity->log(
					contactCacheId: $project->getContactCacheId(),
					type: ActivityService::TYPE_DECK_UPDATE,
					projectId: $project->getId(),
					summary: $summary,
					metadata: [
						'deck_board_id'    => $boardId,
						'deck_board_title' => $title,
					],
				);
			}
		} catch (\Throwable $e) {
			$this->logger->warning(
				'DeckBoardUpdatedListener: failed to log activity: ' . $e->getMessage(),
				['exception' => $e],
			);
		}
	}
}
