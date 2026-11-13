<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Listener;

use OCA\ReidelCrm\Db\ProjectMapper;
use OCA\ReidelCrm\Service\ActivityService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Nullifies the deck_card_id / deck_board_id on any CRM project that was
 * linked to a Deck card that has just been deleted, then logs activity so the
 * CRM timeline reflects the change.
 *
 * Implements IEventListener<\OCA\Deck\Event\CardDeletedEvent>
 */
class DeckCardDeletedListener implements IEventListener {

	public function __construct(
		private ProjectMapper $projects,
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
			$title = method_exists($card, 'getTitle')
				? (string)$card->getTitle()
				: (string)($card->title ?? '(card)');

			foreach ($this->projects->findByDeckCardId($cardId) as $project) {
				// Unlink the deleted Deck card from the project.
				// Keep deck_board_id so the user can later re-link a new card
				// in the same board without reconfiguring the board.
				$project->setDeckCardId(null);
				$project->setUpdatedAt(time());
				$this->projects->update($project);

				$this->activity->log(
					contactCacheId: $project->getContactCacheId(),
					type: ActivityService::TYPE_DECK_UPDATE,
					summary: sprintf('Deck card "%s" was deleted — project unlinked', $title),
					projectId: $project->getId(),
					metadata: ['deck_card_id' => $cardId, 'event' => 'card_deleted'],
				);
			}
		} catch (\Throwable $e) {
			$this->logger->debug('DeckCardDeletedListener failed: ' . $e->getMessage(), ['exception' => $e]);
		}
	}
}
