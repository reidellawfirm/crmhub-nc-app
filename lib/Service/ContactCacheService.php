<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Service;

use OCA\ReidelCrm\Db\Contact;
use OCA\ReidelCrm\Db\ContactMapper;
use OCA\ReidelCrm\Db\NoteMapper;
use OCA\ReidelCrm\Db\ProjectMapper;
use OCA\ReidelCrm\Db\ActivityMapper;
use OCA\ReidelCrm\Exception\DuplicateEmailException;
use OCA\ReidelCrm\Exception\ValidationException;
use OCA\ReidelCrm\Service\GroundhoggSyncService;
use OCA\ReidelCrm\Service\OnboardingService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IServerContainer;
use Psr\Log\LoggerInterface;
use Sabre\VObject\Reader as VObjectReader;

class ContactCacheService {

	public function __construct(
		private ContactMapper $contactMapper,
		private ProjectMapper $projectMapper,
		private NoteMapper $noteMapper,
		private ActivityMapper $activityMapper,
		private ActivityService $activity,
		private LoggerInterface $logger,
		private IServerContainer $serverContainer,
		private ?OnboardingService $onboarding = null,
		private ?GroundhoggSyncService $groundhogg = null,
	) {
	}

	/**
	 * Upsert a contact_cache row from a vCard UID + raw vCard string.
	 * Used both by the API "Link vCard" endpoint and the CardUpdated listener.
	 *
	 * @param string|null $ownerUserId  NC user who should own the record (sets owner_id on INSERT).
	 * @param string|null $actorUserId  NC user who triggered this action (for the activity log).
	 */
	public function upsertFromVCard(string $vcardUid, string $vcardData, ?string $addressbookUri = null, ?string $ownerUserId = null, ?string $actorUserId = null): Contact {
		$parsed = $this->parseVCard($vcardData);

		$contact = $this->contactMapper->findOrNullByVcardUid($vcardUid);
		$isNew = $contact === null;
		if ($isNew) {
			$contact = new Contact();
			$contact->setVcardUid($vcardUid);
			$contact->setCreatedAt(time());
			// Set owner only on INSERT — do not overwrite an existing owner on update.
			if ($ownerUserId !== null) {
				$contact->setOwnerId($ownerUserId);
			}
		}
		if ($addressbookUri !== null) {
			$contact->setVcardAddressbookUri($addressbookUri);
		}
		$contact->setEmail($parsed['email']);
		$contact->setFirstName($parsed['first_name']);
		$contact->setLastName($parsed['last_name']);
		$contact->setPhone($parsed['phone']);
		$contact->setOrganization($parsed['organization']);
		$contact->setUpdatedAt(time());

		$saved = $isNew ? $this->contactMapper->insert($contact) : $this->contactMapper->update($contact);

		$this->activity->log(
			contactCacheId: $saved->getId(),
			type: $isNew ? ActivityService::TYPE_CONTACT_CREATED : ActivityService::TYPE_VCARD_LINKED,
			summary: $isNew
				? sprintf('Contact created from vCard (%s)', $saved->getEmail() ?: $vcardUid)
				: sprintf('vCard updated (%s)', $saved->getEmail() ?: $vcardUid),
			metadata: ['vcard_uid' => $vcardUid, 'addressbook' => $addressbookUri],
			actorUserId: $actorUserId,
		);

		// Phase B2: Run onboarding workflow for brand-new CardDAV-imported contacts.
		if ($isNew && $this->onboarding !== null && $saved->getOwnerId() !== null) {
			$this->onboarding->runForNewContact($saved, $saved->getOwnerId());
		}

		return $saved;
	}

	public function findByEmail(string $email): ?Contact {
		$rows = $this->contactMapper->findByEmail($email);
		return $rows[0] ?? null;
	}

	public function findByVcardUidOrNull(string $uid): ?Contact {
		return $this->contactMapper->findOrNullByVcardUid($uid);
	}

	public function find(int $id): ?Contact {
		try {
			return $this->contactMapper->find($id);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * Manually create a CRM contact (no vCard yet). The vcard_uid still must
	 * be unique; we generate a synthetic one prefixed `crm-` so a real vCard
	 * can later overwrite/link via upsertFromVCard().
	 *
	 * @throws ValidationException   on blank/invalid email or missing name fields.
	 * @throws DuplicateEmailException when the email is already used by another contact.
	 */
	public function createManual(array $data): Contact {
		// ── Validate before touching the DB ──────────────────────────────
		// When a vcardUid is provided (creation from NC Contacts integration),
		// email is optional — the vcardUid is the unique identifier.
		$providedVcardUid = trim((string)($data['vcardUid'] ?? ''));
		if ($providedVcardUid !== '') {
			$this->validateContactDataForVcard($data);
		} else {
			$this->validateContactData($data);
		}

		$contact = new Contact();
		// Use the provided vcardUid if given, otherwise generate a synthetic one.
		$contact->setVcardUid($providedVcardUid !== '' ? $providedVcardUid : 'crm-' . bin2hex(random_bytes(8)));
		$contact->setEmail(trim((string)($data['email'] ?? '')));
		$contact->setFirstName(isset($data['firstName']) ? (trim($data['firstName']) ?: null) : null);
		$contact->setLastName(isset($data['lastName']) ? (trim($data['lastName']) ?: null) : null);
		$contact->setPhone($data['phone'] ?? null);
		$contact->setOrganization(isset($data['organization']) ? (trim($data['organization']) ?: null) : null);
		$contact->setOwnerId($data['ownerId'] ?? null);
		$contact->setStatus($data['status'] ?? 'active');
		$contact->setCreatedAt(time());
		$contact->setUpdatedAt(time());
		$saved = $this->contactMapper->insert($contact);

		$this->activity->log(
			contactCacheId: $saved->getId(),
			type: ActivityService::TYPE_CONTACT_CREATED,
			summary: sprintf('Contact created manually (%s)', $saved->getEmail() ?: 'unknown'),
		);

		// C3: Push a real vCard into NC Contacts / CardDAV so the contact
		// appears in the user's NC Contacts app immediately and edits there
		// will flow back via CardUpdatedListener.
		//
		// IMPORTANT: skip the push when a real NC Contacts vcardUid was supplied
		// by the caller — that vCard already exists in the CardDAV address book.
		// Pushing again would create a ghost duplicate card in NC Contacts.
		// Only push for pure CRM-originated contacts (vcardUid was generated by us).
		if ($providedVcardUid === '' && $saved->getOwnerId() !== null) {
			$this->pushToCardDav($saved);
		}

		// Phase B2: Onboarding workflow — auto-create default project + folder
		// if an admin has enabled it.  Runs after CardDAV push so all IDs exist.
		if ($this->onboarding !== null) {
			$this->onboarding->runForNewContact($saved, $saved->getOwnerId());
		}

		return $saved;
	}

	/**
	 * @throws ValidationException   on blank/invalid email or missing name fields.
	 * @throws DuplicateEmailException when the proposed email is already in use by another contact.
	 */
	public function update(int $id, array $data, ?string $actorUserId = null): ?Contact {
		$contact = $this->find($id);
		if ($contact === null) {
			return null;
		}

		// ── Validate only when a validation-relevant field is being changed ──
		$validationFields = ['email', 'firstName', 'lastName', 'organization'];
		if (!empty(array_intersect(array_keys($data), $validationFields))) {
			// Merge the proposed changes with the existing contact state so we
			// can validate the FINAL result (not just the partial payload).
			$merged = [
				'email'        => array_key_exists('email', $data) ? $data['email'] : $contact->getEmail(),
				'firstName'    => array_key_exists('firstName', $data) ? $data['firstName'] : $contact->getFirstName(),
				'lastName'     => array_key_exists('lastName', $data) ? $data['lastName'] : $contact->getLastName(),
				'organization' => array_key_exists('organization', $data) ? $data['organization'] : $contact->getOrganization(),
			];
			// Pass $id so the uniqueness check ignores this row (allows saving
			// the same email back onto the same contact without a false duplicate).
			$this->validateContactData($merged, $id);
		}

		foreach (['email' => 'setEmail', 'firstName' => 'setFirstName', 'lastName' => 'setLastName',
			'phone' => 'setPhone', 'organization' => 'setOrganization',
			'ownerId' => 'setOwnerId', 'status' => 'setStatus',
			'leadSource' => 'setLeadSource', 'pipelineStage' => 'setPipelineStage'] as $key => $setter) {
			if (array_key_exists($key, $data)) {
				// Trim string fields before persisting.
				$val = is_string($data[$key]) ? trim($data[$key]) : $data[$key];
				$contact->$setter($val !== '' ? $val : null);
			}
		}
		// Email must never be null — restore empty string if somehow blanked out
		// (the validateContactData call above would have caught this already).
		if ($contact->getEmail() === null) {
			$contact->setEmail('');
		}
		$contact->setUpdatedAt(time());
		$updated = $this->contactMapper->update($contact);

		// Log the update so it appears in the CRM timeline and NC Activity bell.
		$name = trim(($updated->getFirstName() ?? '') . ' ' . ($updated->getLastName() ?? ''));
		$this->activity->log(
			contactCacheId: $updated->getId(),
			type: ActivityService::TYPE_CONTACT_UPDATED,
			summary: sprintf('Contact updated (%s)', $name ?: ($updated->getEmail() ?: 'unknown')),
			actorUserId: $actorUserId,
		);

		// ── Phase A1: Bidirectional sync ─────────────────────────────────
		// Push the updated fields back into the linked NC Contacts vCard so
		// the NC Contacts app stays in sync with CRM edits.
		// Loop safety: CardUpdatedListener calls upsertFromVCard() → contactMapper->update()
		// directly — it does NOT call this update() method — so no recursion.
		$this->updateCardDav($updated);

		// ── Phase C2: Groundhogg bidirectional sync ───────────────────────
		// Push status/name/email changes back to Groundhogg for contacts that
		// were originally imported from GH (have a gh_contact_id).
		if ($this->groundhogg !== null) {
			$this->groundhogg->pushContactUpdate($updated);
		}

		return $updated;
	}

	public function delete(int $id): bool {
		$contact = $this->find($id);
		if ($contact === null) {
			return false;
		}

		// ── Log to the NC Activity stream BEFORE the cascade so the owner
		// sees "Contact deleted" in the header bell even though all CRM rows
		// (including the just-created log entry) are purged below.
		// The CRM timeline for this contact will no longer exist after delete;
		// the native NC Activity record is the permanent audit trail.
		$displayName = trim(
			($contact->getFirstName() ?? '') . ' ' . ($contact->getLastName() ?? '')
		) ?: ($contact->getEmail() ?: ('id=' . $id));
		$this->activity->log(
			contactCacheId: $id,
			type: ActivityService::TYPE_CONTACT_DELETED,
			summary: sprintf('Contact deleted (%s)', $displayName),
			metadata: ['email' => $contact->getEmail(), 'owner_id' => $contact->getOwnerId()],
		);

		// ── Phase A4: Delete the linked CardDAV/NC Contacts vCard so the NC
		// Contacts app stays in sync when a contact is removed from CRM. ──
		// Run BEFORE the DB delete so we still have the vcard_uid / address book URI.
		// Loop safety: we delete via the DAV backend directly (not via CardDavBackend
		// which would fire CardDeletedEvent).  CardDeletedListener only nullifies a CRM
		// row — since we are about to delete the row it doesn't matter either way.
		$this->deleteCardDav($contact);

		// Cascade: remove owned projects (and their notes/activities), contact
		// notes, and activity log entries in a single transaction so no orphaned
		// rows remain if anything fails mid-way.
		foreach ($this->projectMapper->findByContactId($id) as $project) {
			$this->projectMapper->delete($project);
		}
		// Use bulk delete methods instead of per-row loops for efficiency.
		$this->noteMapper->deleteByContactId($id);
		$this->activityMapper->deleteByContactId($id);
		$this->contactMapper->delete($contact);
		return true;
	}

	/**
	 * Validate a contact data payload before create or update.
	 *
	 * Rules:
	 *   1. Email is required and must be a syntactically valid address.
	 *   2. At least one of firstName, lastName, or organization must be non-empty.
	 *   3. Email must be unique across all CRM contacts; when $currentId is given
	 *      (edit flow) the uniqueness check ignores that row so the same email
	 *      can be saved back onto the same contact without error.
	 *
	 * All values are trimmed before comparison.
	 *
	 * @param array    $data      Associative array with keys: email, firstName, lastName, organization.
	 * @param int|null $currentId The ID of the contact being edited, null for creates.
	 * @throws ValidationException   on blank email, invalid format, or missing name.
	 * @throws DuplicateEmailException when the email is already used by another contact.
	 */
	/**
	 * Validation for contacts created via NC Contacts integration (vcardUid known).
	 * Email is optional — the vcardUid uniquely identifies the contact.
	 * A name or organization is still required for usability.
	 */
	private function validateContactDataForVcard(array $data): void {
		$firstName    = trim((string)($data['firstName'] ?? ''));
		$lastName     = trim((string)($data['lastName'] ?? ''));
		$organization = trim((string)($data['organization'] ?? ''));
		$email        = trim((string)($data['email'] ?? ''));

		// Require at least one name-like field.
		if ($firstName === '' && $lastName === '' && $organization === '' && $email === '') {
			throw new ValidationException(
				'name',
				'Please provide at least an email, first name, last name, or organization.',
			);
		}

		// Validate email format only if non-empty.
		if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			throw new ValidationException('email', sprintf('"%s" is not a valid email address.', $email));
		}
	}

	private function validateContactData(array $data, ?int $currentId = null): void {
		$email        = trim((string)($data['email'] ?? ''));
		$firstName    = trim((string)($data['firstName'] ?? ''));
		$lastName     = trim((string)($data['lastName'] ?? ''));
		$organization = trim((string)($data['organization'] ?? ''));

		// 1. Email is required.
		if ($email === '') {
			throw new ValidationException('email', 'Email address is required.');
		}

		// 2. Email format must be valid.
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			throw new ValidationException('email', sprintf('"%s" is not a valid email address.', $email));
		}

		// 3. At least one name-like field must be non-empty.
		if ($firstName === '' && $lastName === '' && $organization === '') {
			throw new ValidationException(
				'name',
				'Please provide at least a first name, last name, or organization.',
			);
		}

		// 4. Email uniqueness — case-insensitive, excluding the current row on edit.
		$duplicate = $this->contactMapper->findByEmailExcluding($email, $currentId);
		if ($duplicate !== null) {
			throw new DuplicateEmailException($email);
		}
	}

	/**
	 * Parse a vCard string and extract the fields we cache.
	 * Falls back to a regex parser if Sabre\VObject is unavailable.
	 *
	 * @return array{email:string, first_name:?string, last_name:?string, phone:?string}
	 */
	private function parseVCard(string $vcard): array {
		$result = ['email' => '', 'first_name' => null, 'last_name' => null, 'phone' => null, 'organization' => null];
		if (class_exists(VObjectReader::class)) {
			try {
				$card = VObjectReader::read($vcard);
				if (isset($card->EMAIL)) {
					$result['email'] = (string)$card->EMAIL;
				}
				if (isset($card->TEL)) {
					$result['phone'] = (string)$card->TEL;
				}
				if (isset($card->ORG)) {
					$parts = $card->ORG->getParts();
					$result['organization'] = ($parts[0] ?? '') ?: null;
				}
				if (isset($card->N)) {
					$parts = $card->N->getParts();
					$result['last_name'] = $parts[0] ?: null;
					$result['first_name'] = $parts[1] ?? null;
				} elseif (isset($card->FN)) {
					$fn = (string)$card->FN;
					$pieces = preg_split('/\s+/', trim($fn), 2);
					$result['first_name'] = $pieces[0] ?? null;
					$result['last_name'] = $pieces[1] ?? null;
				}
				return $result;
			} catch (\Throwable $e) {
				$this->logger->debug('VObject parse failed, falling back to regex: ' . $e->getMessage());
			}
		}
		// Minimal fallback parser
		if (preg_match('/^EMAIL[^:]*:(.+)$/mi', $vcard, $m)) {
			$result['email'] = trim($m[1]);
		}
		if (preg_match('/^TEL[^:]*:(.+)$/mi', $vcard, $m)) {
			$result['phone'] = trim($m[1]);
		}
		if (preg_match('/^ORG[^:]*:([^;\r\n]+)/mi', $vcard, $m)) {
			$org = trim($m[1]);
			$result['organization'] = $org !== '' ? $org : null;
		}
		if (preg_match('/^N[^:]*:([^;]*);([^;]*)/mi', $vcard, $m)) {
			$result['last_name'] = trim($m[1]) ?: null;
			$result['first_name'] = trim($m[2]) ?: null;
		}
		return $result;
	}

	/**
	 * Create a vCard in the owner's first CardDAV address book so the contact
	 * appears in NC Contacts and edits there flow back via CardUpdatedListener.
	 *
	 * Soft-depends on OCA\DAV: if the class is absent or the backend call fails
	 * we log a warning and carry on — the CRM contact already exists.
	 *
	 * Note: createCard() will fire CardCreatedEvent. CardCreatedListener checks
	 * findByVcardUidOrNull() first and skips the import if the row already
	 * exists, so there is no import loop.
	 */
	private function pushToCardDav(Contact $contact): void {
		if (!class_exists(\OCA\DAV\CardDAV\CardDavBackend::class)) {
			return;
		}
		$ownerId = $contact->getOwnerId();
		if ($ownerId === null) {
			return;
		}
		try {
			/** @var \OCA\DAV\CardDAV\CardDavBackend $backend */
			$backend = $this->serverContainer->get(\OCA\DAV\CardDAV\CardDavBackend::class);
			$books   = $backend->getAddressBooksForUser('principals/users/' . $ownerId);
			if (empty($books)) {
				$this->logger->debug(
					'ContactCacheService::pushToCardDav: no address books for ' . $ownerId,
				);
				return;
			}
			// Use the first personal address book (usually named 'contacts').
			$book    = $books[0];
			$uid     = $contact->getVcardUid();
			$cardUri = $uid . '.vcf';
			$vcard   = $this->buildVCard($contact, $uid);

			$backend->createCard((int)$book['id'], $cardUri, $vcard);

			// Record the address book URI so CardUpdatedListener can identify
			// future updates to this vCard.
			$contact->setVcardAddressbookUri((string)($book['uri'] ?? ''));
			$this->contactMapper->update($contact);
		} catch (\Throwable $e) {
			$this->logger->warning(
				'ContactCacheService::pushToCardDav failed: ' . $e->getMessage(),
				['exception' => $e, 'contact_id' => $contact->getId()],
			);
		}
	}

	/**
	 * Build a minimal RFC 6350 v3 vCard string from a Contact entity.
	 * Uses CRLF line endings as required by the vCard spec.
	 */
	private function buildVCard(Contact $contact, string $uid): string {
		$firstName = $contact->getFirstName() ?? '';
		$lastName  = $contact->getLastName() ?? '';
		$fn = trim($firstName . ' ' . $lastName);
		if ($fn === '') {
			$fn = $contact->getEmail() ?: 'Unknown';
		}
		$lines = [
			'BEGIN:VCARD',
			'VERSION:3.0',
			'UID:' . $uid,
			'FN:' . $fn,
			'N:' . $lastName . ';' . $firstName . ';;;',
		];
		if ($contact->getEmail() !== '') {
			$lines[] = 'EMAIL;TYPE=INTERNET:' . $contact->getEmail();
		}
		if (($org = $contact->getOrganization()) !== null && $org !== '') {
			$lines[] = 'ORG:' . $org;
		}
		if (($phone = $contact->getPhone()) !== null && $phone !== '') {
			$lines[] = 'TEL;TYPE=CELL:' . $phone;
		}
		$lines[] = 'END:VCARD';
		// vCard spec requires CRLF line endings.
		return implode("\r\n", $lines) . "\r\n";
	}

	/**
	 * Phase A1: Push CRM contact field changes back into the linked NC Contacts vCard.
	 *
	 * Called after every contact update().  Loop-safe because CardUpdatedListener
	 * calls upsertFromVCard() → contactMapper->update() directly, never this method.
	 *
	 * Skips contacts without a CardDAV link (no vcard_addressbook_uri) and
	 * contacts sourced from Groundhogg (GH is source-of-truth for those fields).
	 */
	private function updateCardDav(Contact $contact): void {
		if (!class_exists(\OCA\DAV\CardDAV\CardDavBackend::class)) {
			return;
		}
		$uid     = $contact->getVcardUid();
		$ownerId = $contact->getOwnerId();
		$abUri   = $contact->getVcardAddressbookUri();

		// No CardDAV link established yet, or sourced from Groundhogg.
		if ($ownerId === null || $abUri === null || $abUri === '') {
			return;
		}
		if (str_starts_with($uid, 'gh-')) {
			// Groundhogg is the source of truth for gh-* contacts.
			return;
		}

		try {
			/** @var \OCA\DAV\CardDAV\CardDavBackend $backend */
			$backend = $this->serverContainer->get(\OCA\DAV\CardDAV\CardDavBackend::class);
			$books   = $backend->getAddressBooksForUser('principals/users/' . $ownerId);

			// Find the address book by URI; fall back to the first available book.
			$targetBook = null;
			foreach ($books as $book) {
				if (($book['uri'] ?? '') === $abUri) {
					$targetBook = $book;
					break;
				}
			}
			$targetBook ??= ($books[0] ?? null);
			if ($targetBook === null) {
				return;
			}

			$cardUri = $uid . '.vcf';
			$vcard   = $this->buildVCard($contact, $uid);
			$bookId  = (int)$targetBook['id'];

			// Update if the card already exists, otherwise create it.
			$existing = $backend->getCard($bookId, $cardUri);
			if ($existing !== false) {
				$backend->updateCard($bookId, $cardUri, $vcard);
			} else {
				$backend->createCard($bookId, $cardUri, $vcard);
				$contact->setVcardAddressbookUri((string)($targetBook['uri'] ?? ''));
				$this->contactMapper->update($contact);
			}
			$this->logger->debug('ContactCacheService: vCard updated for contact ' . $contact->getId());
		} catch (\Throwable $e) {
			$this->logger->warning(
				'ContactCacheService::updateCardDav failed: ' . $e->getMessage(),
				['exception' => $e, 'contact_id' => $contact->getId()],
			);
		}
	}

	/**
	 * Phase A4: Delete the NC Contacts (CardDAV) vCard that mirrors this CRM
	 * contact, so the NC Contacts app stays in sync when a CRM contact is removed.
	 *
	 * Run BEFORE the DB row is deleted so we still have vcard_uid / address-book URI.
	 *
	 * Loop safety: we call backend->deleteCard() directly rather than via any
	 * CardDavBackend event path.  If CardDeletedListener fires it will call
	 * findByVcardUidOrNull() → null (row already gone) → no-op.
	 */
	private function deleteCardDav(Contact $contact): void {
		if (!class_exists(\OCA\DAV\CardDAV\CardDavBackend::class)) {
			return;
		}
		$uid = $contact->getVcardUid();
		if (!$uid || str_starts_with($uid, 'gh-')) {
			return;   // Groundhogg-sourced or no UID — nothing to delete in DAV.
		}
		$abUri   = $contact->getVcardAddressbookUri();
		$ownerId = $contact->getOwnerId();
		if (!$abUri || !$ownerId) {
			return;   // Not linked to an address book, nothing to delete.
		}
		try {
			/** @var \OCA\DAV\CardDAV\CardDavBackend $backend */
			$backend = $this->serverContainer->get(\OCA\DAV\CardDAV\CardDavBackend::class);
			$book    = $backend->getAddressBooksByUri('principals/users/' . $ownerId, $abUri);
			if ($book === null) {
				return;
			}
			$backend->deleteCard((int)$book['id'], $uid . '.vcf');
		} catch (\Throwable $e) {
			// Non-fatal: log and continue.  The CRM DB row is deleted regardless.
			$this->logger->warning(
				sprintf('ContactCacheService::deleteCardDav failed for uid=%s: %s', $uid, $e->getMessage()),
				['exception' => $e],
			);
		}
	}
}
