<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Listener;

use OCA\ReidelCrm\AppInfo\Application;
use OCA\ReidelCrm\Db\ProjectMapper;
use OCA\ReidelCrm\Service\ActivityService;
use OCA\ReidelCrm\Service\ContactCacheService;
use OCA\ReidelCrm\Service\ProjectService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

/**
 * Optional reverse-provisioning: when a new card is created in a configured
 * Deck board, automatically create a CRM project for the linked contact.
 *
 * Soft-dependency: listener is only registered when OCA\Deck is installed.
 *
 * Admin opt-in: both `deck_reverse_provisioning` (= 'yes') and
 * `deck_reverse_board_id` must be configured by an admin for any work to happen.
 *
 * Loop safety: CardCreatedListener (DAV) checks vcard_uid → skips if the
 * contact already exists.  This listener only creates a *project*, not a contact,
 * so there is no possible loop.
 *
 * @template-implements IEventListener<\OCA\Deck\Event\CardCreatedEvent>
 */
class DeckCardCreatedListener implements IEventListener {

	public function __construct(
		private ContactCacheService $contacts,
		private ProjectService $projectService,
		private ProjectMapper $projectMapper,
		private ActivityService $activity,
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!class_exists(\OCA\Deck\Event\CardCreatedEvent::class)) {
			return;
		}
		if (!$event instanceof \OCA\Deck\Event\CardCreatedEvent) {
			return;
		}

		// Feature must be explicitly enabled by an admin.
		$enabled = $this->appConfig->getValueString(Application::APP_ID, 'deck_reverse_provisioning', 'no');
		if ($enabled !== 'yes') {
			return;
		}

		$configuredBoardId = (int)$this->appConfig->getValueString(Application::APP_ID, 'deck_reverse_board_id', '0');
		if ($configuredBoardId <= 0) {
			return;
		}

		try {
			$card    = $event->getCard();
			$boardId = $card->getBoardId();

			// Only act on cards in the configured board.
			if ($boardId !== $configuredBoardId) {
				return;
			}

			$cardId    = $card->getId();
			$cardTitle = $card->getTitle();

			// Try to resolve a CRM contact from the card's title (treated as email).
			// CRM teams often name cards with the contact's email or "First Last".
			$contact = $this->contacts->findByEmail($cardTitle);
			if ($contact === null) {
				// No contact found — we cannot provision a project without a contact.
				$this->logger->debug(sprintf(
					'DeckCardCreatedListener: no CRM contact found for card title "%s" (board %d)',
					$cardTitle,
					$boardId,
				));
				return;
			}

			// Idempotency: check whether a project is already linked to this exact card.
			// findByDeckBoardId() returns projects on the board — filter by card to be precise.
			$existingForBoard = $this->projectMapper->findByDeckBoardId($boardId);
			foreach ($existingForBoard as $p) {
				if ($p->getDeckCardId() === $cardId) {
					return;  // Already provisioned.
				}
			}

			// Create a CRM project.  ProjectService::create() expects the
			// contactId as the first positional argument and a camelCased
			// data array as the second; the actorUserId is the third.
			$project = $this->projectService->create(
				$contact->getId(),
				[
					'projectName' => $cardTitle,
					'status'      => 'active',
					'deckCardId'  => $cardId,
					'deckBoardId' => $boardId,
				],
				$contact->getOwnerId(),
			);

			$this->activity->log(
				contactCacheId: $contact->getId(),
				type: ActivityService::TYPE_PROJECT_CREATED,
				projectId: $project->getId(),
				summary: sprintf('Project "%s" auto-provisioned from Deck card', $cardTitle),
				metadata: [
					'deck_card_id'  => $cardId,
					'deck_board_id' => $boardId,
				],
				actorUserId: $contact->getOwnerId(),
			);
		} catch (\Throwable $e) {
			$this->logger->warning(
				'DeckCardCreatedListener: error during reverse-provisioning: ' . $e->getMessage(),
				['exception' => $e],
			);
		}
	}
}
