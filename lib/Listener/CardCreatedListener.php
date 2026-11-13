<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Listener;

use OCA\ReidelCrm\Service\ContactCacheService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Automatically imports every new NC Contacts vCard into reidel_contacts_cache.
 *
 * This is the counterpart to CardUpdatedListener (which is opt-in / update-only).
 * Here we import on CREATE so that contacts managed purely in NC Contacts always
 * appear in the CRM without any manual "Link vCard" action.
 *
 * If the CRM row already exists for this UID (created by the C3 reverse-sync in
 * ContactCacheService::createManual()), we skip the import — subsequent updates
 * are handled by CardUpdatedListener.
 *
 * Implements IEventListener<\OCA\DAV\Events\CardCreatedEvent>
 */
class CardCreatedListener implements IEventListener {

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

			// If a CRM row already exists for this UID the vCard was pushed by
			// the CRM itself (C3 reverse-sync). Skip to prevent a phantom
			// "contact_created" activity entry and a redundant upsert.
			if ($this->contacts->findByVcardUidOrNull($uid) !== null) {
				return;
			}

			// Resolve the DAV principal to get the NC user who owns this address book.
			$ownerUserId = null;
			if (method_exists($event, 'getAddressBookData')) {
				$abData = $event->getAddressBookData();
				$principal = $abData['principaluri'] ?? '';
				if (preg_match('#principals/users/(.+)$#', (string)$principal, $m)) {
					$ownerUserId = $m[1];
				}
			}

			$addressbook = method_exists($event, 'getAddressBookId')
				? (string)$event->getAddressBookId()
				: null;

			// Import — creates the CRM row and fires TYPE_CONTACT_CREATED activity.
			$this->contacts->upsertFromVCard(
				$uid,
				$cardData,
				$addressbook,
				$ownerUserId,
				$ownerUserId,  // actor = the owner who just created the vCard
			);
		} catch (\Throwable $e) {
			$this->logger->debug('CardCreatedListener failed: ' . $e->getMessage(), ['exception' => $e]);
		}
	}

	private function extractUid(string $vcard): ?string {
		if (preg_match('/^UID[^:]*:(.+)$/mi', $vcard, $m)) {
			return trim($m[1]);
		}
		return null;
	}
}
