<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Listener;

use OCA\ReidelCrm\Db\ProjectMapper;
use OCA\ReidelCrm\Service\ActivityService;
use OCA\ReidelCrm\Service\ProjectService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Handles Deck card update events:
 *
 * 1. Logs every card update as a CRM activity entry (existing behaviour).
 * 2. Bidirectional status sync — when a Deck card is archived, the linked
 *    CRM project is closed automatically (if not already closed).
 *
 * Loop-safety:
 *   CRM project closed → ProjectService::archiveDeckCard() → Deck CardService::archive()
 *   → CardUpdatedEvent fires again → this listener runs → card.archived = true, but
 *   project is ALREADY closed → update() short-circuits on previousStatus check → no loop.
 *
 * Implements IEventListener<\OCA\Deck\Event\CardUpdatedEvent>
 */
class DeckCardUpdatedListener implements IEventListener {

	public function __construct(
		private ProjectMapper $projects,
		private ProjectService $projectService,
		private ActivityService $activity,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		try {
			if (!is_object($event) || !method_exists($event, 'getCard')) {
				return;
			}
			$card   = $event->getCard();
			$cardId = (int)(method_exists($card, 'getId') ? $card->getId() : ($card->id ?? 0));
			if ($cardId === 0) {
				return;
			}
			$title = method_exists($card, 'getTitle') ? (string)$card->getTitle() : ($card->title ?? '(card)');

			// Detect whether the Deck card has been archived.
			$isArchived = method_exists($card, 'getArchived')
				? (bool)$card->getArchived()
				: (bool)($card->archived ?? false);

			$linkedProjects = $this->projects->findByDeckCardId($cardId);

			foreach ($linkedProjects as $project) {
				// ── 1. Log card-update activity ─────────────────────────
				$this->activity->log(
					contactCacheId: $project->getContactCacheId(),
					type: ActivityService::TYPE_DECK_UPDATE,
					summary: sprintf('Deck card "%s" updated', $title),
					projectId: $project->getId(),
					metadata: ['deck_card_id' => $cardId, 'archived' => $isArchived],
				);

				// ── 2. Bidirectional status sync: archive → close ────────
				// Only transition when the Deck card is newly archived AND the
				// CRM project is still open (prevents duplicate state writes).
				if ($isArchived) {
					$closedStatuses = ['closed', 'archived', 'inactive'];
					if (!in_array($project->getStatus(), $closedStatuses, true)) {
						// Use ProjectService::update() so that:
						//   a) activity TYPE_PROJECT_CLOSED is logged
						//   b) CalendarService::deleteProjectEvent() is called
						//   c) archiveDeckCard() is NOT re-called (card already archived)
						$this->projectService->update(
							$project->getId(),
							['status' => 'closed'],
							null, // actor = system
						);
					}
				}
			}
		} catch (\Throwable $e) {
			$this->logger->debug('DeckCardUpdatedListener failed: ' . $e->getMessage(), ['exception' => $e]);
		}
	}
}
