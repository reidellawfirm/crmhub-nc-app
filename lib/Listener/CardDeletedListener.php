<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Listener;

use OCA\ReidelCrm\Service\ContactCacheService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Removes the CRM contact row (with full cascade) when the corresponding
 * NC Contacts vCard is deleted by the user.
 *
 * This ensures there are no stale CRM contacts pointing at non-existent vCards.
 * The cascade in ContactCacheService::delete() removes linked projects, notes,
 * and activity log entries automatically.
 *
 * Implements IEventListener<\OCA\DAV\Events\CardDeletedEvent>
 */
class CardDeletedListener implements IEventListener {

	public function __construct(
		private ContactCacheService $contacts,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		// Soft-typed: OCA\DAV may not be available at static-analysis time.
		if (!is_object($event) || !method_exists($event, 'getCardData')) {
			return;
		}
		try {
			$cardData = (string)$event->getCardData();
			$uid = $this->extractUid($cardData);
			if ($uid === null) {
				return;
			}

			$contact = $this->contacts->findByVcardUidOrNull($uid);
			if ($contact === null) {
				// Not tracked in CRM — nothing to clean up.
				return;
			}

			// Cascade-delete the CRM row, all linked projects, notes, and
			// activity log entries for this contact.
			$this->contacts->delete($contact->getId());

			$this->logger->debug(
				'CardDeletedListener: removed CRM contact',
				['contact_id' => $contact->getId(), 'vcard_uid' => $uid],
			);
		} catch (\Throwable $e) {
			$this->logger->debug('CardDeletedListener failed: ' . $e->getMessage(), ['exception' => $e]);
		}
	}

	private function extractUid(string $vcard): ?string {
		if (preg_match('/^UID[^:]*:(.+)$/mi', $vcard, $m)) {
			return trim($m[1]);
		}
		return null;
	}
}
