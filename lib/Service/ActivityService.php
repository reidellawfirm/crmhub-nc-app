<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Service;

use OCA\ReidelCrm\AppInfo\Application;
use OCA\ReidelCrm\Db\ActivityEntry;
use OCA\ReidelCrm\Db\ActivityMapper;
use OCA\ReidelCrm\Db\ContactMapper;
use OCP\Activity\IManager as IActivityManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Writes structured activity entries to reidel_activity_log AND publishes
 * them into the native Nextcloud Activity stream so they appear in the header
 * bell icon, the /apps/activity/ feed, and email digests.
 */
class ActivityService {

	public const TYPE_CONTACT_CREATED = 'contact_created';
	public const TYPE_CONTACT_UPDATED = 'contact_updated';
	public const TYPE_CONTACT_DELETED = 'contact_deleted';
	public const TYPE_VCARD_LINKED = 'vcard_linked';
	public const TYPE_PROJECT_CREATED = 'project_created';
	public const TYPE_PROJECT_UPDATED = 'project_updated';
	public const TYPE_PROJECT_CLOSED = 'project_closed';
	public const TYPE_NOTE_ADDED = 'note_added';
	public const TYPE_FILE_UPLOADED = 'file_uploaded';
	public const TYPE_FOLDER_CREATED = 'folder_created';
	public const TYPE_FOLDER_RENAMED = 'folder_renamed';
	public const TYPE_FOLDER_DELETED = 'folder_deleted';
	public const TYPE_DECK_UPDATE = 'deck_update';
	public const TYPE_DECK_ARCHIVED = 'deck_archived';
	public const TYPE_TAG_CHANGED = 'tag_changed';
	public const TYPE_EMAIL_SENT = 'email_sent';
	public const TYPE_EMAIL_RECEIVED = 'email_received';
	public const TYPE_EMAIL_MATCHED = 'email_matched';
	public const TYPE_FUNNEL_EVENT = 'funnel_event';
	public const TYPE_SYNC = 'sync';
	public const TYPE_RECORD_MERGED = 'record_merged';
	public const TYPE_ONBOARDING = 'onboarding';
	public const TYPE_CALENDAR_EVENT = 'calendar_event';
	public const TYPE_OVERDUE = 'overdue';

	public function __construct(
		private ActivityMapper $mapper,
		private ContactMapper $contactMapper,
		private IActivityManager $activityManager,
		private LoggerInterface $logger,
		private IUserSession $userSession,
	) {
	}

	/**
	 * @param int         $contactCacheId  CRM contact row ID
	 * @param string      $type            One of ActivityService::TYPE_* constants
	 * @param string      $summary         Human-readable one-liner (max 500 chars)
	 * @param int|null    $projectId       Related project ID, if any
	 * @param array       $metadata        Arbitrary key→value context stored as JSON
	 * @param string      $source          'nextcloud' | 'windmill' | 'groundhogg'
	 * @param int|null    $occurredAt      Unix timestamp; defaults to now
	 * @param string|null $actorUserId     NC user who triggered the action (null = system)
	 */
	public function log(
		int $contactCacheId,
		string $type,
		string $summary,
		?int $projectId = null,
		array $metadata = [],
		string $source = 'nextcloud',
		?int $occurredAt = null,
		?string $actorUserId = null,
	): ActivityEntry {
		$timestamp = $occurredAt ?? time();

		// ── 1. Write to CRM's own activity log ────────────────────────────
		$entry = new ActivityEntry();
		$entry->setContactCacheId($contactCacheId);
		$entry->setProjectId($projectId);
		$entry->setActivityType($type);
		$entry->setSummary(mb_substr($summary, 0, 500));
		$entry->setMetadataJson($metadata ? json_encode($metadata) : null);
		$entry->setSource($source);
		$entry->setOccurredAt($timestamp);
		try {
			$entry = $this->mapper->insert($entry);
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to log CRM activity: ' . $e->getMessage(), ['exception' => $e]);
			return $entry;
		}

		// ── 2. Mirror into native NC Activity stream ───────────────────────
		// Resolve the NC user who owns this contact so the event appears in
		// their personal activity feed and header notification bell.
		$affectedUser = $this->resolveOwnerUserId($contactCacheId);
		if ($affectedUser !== null) {
			try {
				$ncEvent = $this->activityManager->generateEvent();
				$ncEvent->setApp(Application::APP_ID)
					->setType(Application::APP_ID)
					->setAuthor($actorUserId ?? $affectedUser)
					->setAffectedUser($affectedUser)
					->setSubject($type, array_merge(
						['summary' => $summary],
						$projectId !== null ? ['project_id' => $projectId] : [],
						$metadata,
					))
					->setObject('crm_contact', $contactCacheId, $summary)
					->setTimestamp($timestamp);
				$this->activityManager->publish($ncEvent);
			} catch (\Throwable $e) {
				// Best-effort: never let NC Activity publishing break CRM flow
				$this->logger->warning(
					'Failed to publish NC activity event: ' . $e->getMessage(),
					['exception' => $e, 'type' => $type, 'contact' => $contactCacheId],
				);
			}
		}

		return $entry;
	}

	/**
	 * Look up the NC user ID that owns this contact record.
	 *
	 * Priority:
	 *  1. contact.owner_id  — explicit owner set on the record
	 *  2. IUserSession      — the currently logged-in user (web request context)
	 *  3. null              — background job / CLI with no session → skip NC publish
	 */
	private function resolveOwnerUserId(int $contactCacheId): ?string {
		try {
			$contact = $this->contactMapper->find($contactCacheId);
			$ownerId = $contact->getOwnerId();
			if ($ownerId !== null) {
				return $ownerId;
			}
		} catch (DoesNotExistException) {
			// Contact not found — fall through to session user
		} catch (\Throwable $e) {
			$this->logger->debug('resolveOwnerUserId failed: ' . $e->getMessage());
			// Fall through to session user
		}

		// No explicit owner: use whoever is making the web request.
		// In background jobs / CLI this returns null and NC publish is skipped.
		return $this->userSession->getUser()?->getUID();
	}
}
