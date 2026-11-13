<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Service;

use OCA\ReidelCrm\Db\Contact;
use OCA\ReidelCrm\Db\ContactMapper;
use OCA\ReidelCrm\Db\SyncLogEntry;
use OCA\ReidelCrm\Db\SyncLogMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use Psr\Log\LoggerInterface;

/**
 * Imports/refreshes Groundhogg contact records into reidel_contacts_cache.
 *
 * Conflict policy: GH is source-of-truth for email/first_name/last_name/phone
 * and the gh_* fields. NC-only fields (status, project links, notes,
 * activity entries) are never overwritten.
 */
class GroundhoggSyncService {

	public function __construct(
		private GroundhoggClient $client,
		private ContactMapper $contactMapper,
		private SyncLogMapper $syncLog,
		private ActivityService $activity,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Phase C2 — Push a CRM contact status/tag change back to Groundhogg.
	 *
	 * Only contacts with a known gh_contact_id are pushed.
	 * This maps the CRM status to a Groundhogg tag so the funnel state stays
	 * coherent in both systems.
	 *
	 * Status → Tag mapping:
	 *   active   → crm-active
	 *   inactive → crm-inactive
	 *   archived → crm-archived
	 *   prospect → crm-prospect
	 */
	public function pushContactUpdate(Contact $contact): void {
		$ghId = $contact->getGhContactId();
		if ($ghId === null || $ghId === 0) {
			return;
		}
		if (!$this->client->isConfigured()) {
			return;
		}

		// Map CRM status to a GH tag slug.
		$statusTagMap = [
			'active'   => 'crm-active',
			'inactive' => 'crm-inactive',
			'archived' => 'crm-archived',
			'prospect' => 'crm-prospect',
			'closed'   => 'crm-archived',
		];
		$status = $contact->getStatus();
		$tagSlug = $statusTagMap[$status] ?? null;

		// Remove all other CRM status tags and add the current one.
		$tagsAdd    = $tagSlug !== null ? [$tagSlug] : [];
		$tagsRemove = array_values(array_filter(
			array_unique(array_values($statusTagMap)),
			fn ($t) => $t !== $tagSlug,
		));

		$payload = [];
		if (!empty($tagsAdd)) {
			$payload['tags'] = ['add' => $tagsAdd, 'remove' => $tagsRemove];
		}
		// Sync first_name / last_name / email / phone in case they changed.
		$payload['first_name'] = $contact->getFirstName() ?? '';
		$payload['last_name']  = $contact->getLastName() ?? '';
		$payload['email']      = $contact->getEmail();

		$ok = $this->client->updateContact($ghId, $payload);

		$entry = new SyncLogEntry();
		$entry->setDirection('out');
		$entry->setEntityType('groundhogg_contact');
		$entry->setEntityId((string)$ghId);
		$entry->setStatus($ok ? 'success' : 'error');
		$entry->setMessage(json_encode(['contact_id' => $contact->getId(), 'status' => $status]));
		$entry->setCreatedAt(time());
		$this->syncLog->insert($entry);
	}

	public function importAll(): array {
		return $this->import(null);
	}

	public function importIncremental(?\DateTimeInterface $since = null): array {
		return $this->import($since);
	}

	private function import(?\DateTimeInterface $since): array {
		if (!$this->client->isConfigured()) {
			return ['imported' => 0, 'updated' => 0, 'errors' => 0, 'skipped_reason' => 'not_configured'];
		}
		$imported = $updated = $errors = 0;
		$now = new \DateTime();

		foreach ($this->client->listContacts($since) as $row) {
			try {
				[$wasNew, $contact] = $this->upsert($row, $now);
				$wasNew ? $imported++ : $updated++;
				$this->activity->log(
					contactCacheId: $contact->getId(),
					type: ActivityService::TYPE_SYNC,
					summary: $wasNew ? 'Imported from Groundhogg' : 'Updated from Groundhogg',
					source: 'groundhogg',
				);
			} catch (\Throwable $e) {
				$errors++;
				$this->logger->warning('GH upsert failed: ' . $e->getMessage(), ['exception' => $e, 'row' => $row]);
			}
		}

		$entry = new SyncLogEntry();
		$entry->setDirection('in');
		$entry->setEntityType('groundhogg_contacts');
		$entry->setStatus($errors > 0 ? 'partial' : 'success');
		$entry->setMessage(json_encode(compact('imported', 'updated', 'errors')));
		$entry->setCreatedAt(time());
		$this->syncLog->insert($entry);

		return ['imported' => $imported, 'updated' => $updated, 'errors' => $errors];
	}

	/**
	 * @return array{0:bool,1:Contact}
	 */
	private function upsert(array $row, \DateTimeInterface $syncedAt): array {
		$ghId = (int)($row['ID'] ?? $row['id'] ?? 0);
		if ($ghId === 0) {
			throw new \RuntimeException('Groundhogg row missing ID');
		}
		$email = (string)($row['email'] ?? '');
		$first = $row['first_name'] ?? null;
		$last = $row['last_name'] ?? null;
		$meta = $row['meta'] ?? [];
		$phone = $row['primary_phone'] ?? ($meta['primary_phone'] ?? null);
		$tags = $row['tags'] ?? [];
		$ownerId = isset($row['owner_id']) ? (string)$row['owner_id'] : null;
		$optin = isset($row['optin_status']) ? (int)$row['optin_status'] : null;
		$dateCreated = !empty($row['date_created']) ? new \DateTime((string)$row['date_created']) : null;

		$wasNew = false;
		try {
			$contact = $this->contactMapper->findByGhContactId($ghId);
		} catch (DoesNotExistException) {
			$wasNew = true;
			$contact = new Contact();
			$contact->setVcardUid('gh-' . $ghId);
			$contact->setCreatedAt(time());
		}

		$contact->setGhContactId($ghId);
		$contact->setEmail($email);
		$contact->setFirstName($first);
		$contact->setLastName($last);
		$contact->setPhone($phone);
		$contact->setOwnerId($ownerId);
		$contact->setGhOptinStatus($optin);
		$contact->setGhTagsJson(!empty($tags) ? json_encode($tags) : null);
		$contact->setGhMetaJson(!empty($meta) ? json_encode($meta) : null);
		$contact->setGhDateCreated($dateCreated);
		$contact->setGhLastSynced(\DateTime::createFromInterface($syncedAt));
		$contact->setUpdatedAt(time());

		$saved = $wasNew ? $this->contactMapper->insert($contact) : $this->contactMapper->update($contact);
		return [$wasNew, $saved];
	}
}
