<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Listener;

use OCA\ReidelCrm\Service\ContactCacheService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Mirrors CardDAV vCard updates into reidel_contacts_cache when an existing
 * row already references that vCard UID. We do NOT auto-import every new
 * vCard — linking is opt-in via the SPA "Link vCard" action.
 *
 * Implements IEventListener<\OCA\DAV\Events\CardUpdatedEvent>
 */
class CardUpdatedListener implements IEventListener {

	public function __construct(
		private ContactCacheService $contacts,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		// Soft-typed because OCA\DAV may not be available at static-analysis time
		if (!is_object($event) || !method_exists($event, 'getCardData')) {
			return;
		}
		try {
			$raw = $event->getCardData();
			$cardData = is_array($raw) ? implode('', $raw) : (string)$raw;
			$uid = $this->extractUid($cardData);
			if ($uid === null) {
				return;
			}
			// Only update if we already track this vCard (opt-in linking)
			$existing = $this->contacts->findByVcardUidOrNull($uid);
			if ($existing === null) {
				return;
			}
			$addressbook = method_exists($event, 'getAddressBookId') ? (string)$event->getAddressBookId() : null;

			// Resolve the DAV principal owner so the activity event is scoped
			// to the correct user (not anonymous/system).
			$ownerUserId = null;
			if (method_exists($event, 'getAddressBookData')) {
				$abData = $event->getAddressBookData();
				$principal = $abData['principaluri'] ?? '';
				if (preg_match('#principals/users/(.+)$#', (string)$principal, $m)) {
					$ownerUserId = $m[1];
				}
			}
			// Fall back to the existing owner so we don't overwrite it with null.
			$ownerUserId = $ownerUserId ?? $existing->getOwnerId();

			$this->contacts->upsertFromVCard($uid, $cardData, $addressbook, $ownerUserId, null);
		} catch (\Throwable $e) {
			$this->logger->debug('CardUpdatedListener failed: ' . $e->getMessage());
		}
	}

	private function extractUid(string $vcard): ?string {
		if (preg_match('/^UID[^:]*:(.+)$/mi', $vcard, $m)) {
			return trim($m[1]);
		}
		return null;
	}
}
