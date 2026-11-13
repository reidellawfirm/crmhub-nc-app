<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Controller;

use OCA\ReidelCrm\Db\ActivityMapper;
use OCA\ReidelCrm\Db\Contact;
use OCA\ReidelCrm\Db\ContactMapper;
use OCA\ReidelCrm\Db\NoteMapper;
use OCA\ReidelCrm\Db\ProjectMapper;
use OCA\ReidelCrm\Service\ActivityService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Bulk record management for unified CRM records: merge two contacts (with
 * cascading reassignment of projects/notes/activity), duplicate a contact, etc.
 */
class RecordOpsController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private ContactMapper $contactMapper,
		private ProjectMapper $projectMapper,
		private NoteMapper $noteMapper,
		private ActivityMapper $activityMapper,
		private ActivityService $activity,
		private IDBConnection $db,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function merge(): DataResponse {
		$fromId = (int)$this->request->getParam('fromContactId');
		$toId = (int)$this->request->getParam('toContactId');
		if ($fromId === 0 || $toId === 0 || $fromId === $toId) {
			return new DataResponse(['error' => 'fromContactId and toContactId required and distinct'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$from = $this->contactMapper->find($fromId);
			$to = $this->contactMapper->find($toId);
		} catch (\Throwable) {
			return new DataResponse(['error' => 'contact not found'], Http::STATUS_NOT_FOUND);
		}
		// Ownership: caller must own (or be allowed to access) both contacts.
		if (!$this->canAccess($from) || !$this->canAccess($to)) {
			return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}

		$this->db->beginTransaction();
		try {
			$projects = $this->projectMapper->reassignContact($fromId, $toId);
			$notes = $this->noteMapper->reassignContact($fromId, $toId);
			$activity = $this->activityMapper->reassignContact($fromId, $toId);
			$this->contactMapper->delete($from);
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$this->activity->log(
			contactCacheId: $toId,
			type: ActivityService::TYPE_RECORD_MERGED,
			summary: sprintf('Merged contact #%d into #%d', $fromId, $toId),
			metadata: ['projects' => $projects, 'notes' => $notes, 'activity' => $activity],
			source: 'manual',
		);

		return new DataResponse([
			'merged' => true,
			'reassigned' => ['projects' => $projects, 'notes' => $notes, 'activity' => $activity],
			'kept' => $to->jsonSerialize(),
		]);
	}

	#[NoAdminRequired]
	public function duplicate(int $id): DataResponse {
		try {
			$src = $this->contactMapper->find($id);
		} catch (\Throwable) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		if (!$this->canAccess($src)) {
			return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}
		// Create a fresh entity and copy fields — do NOT clone then null the PK,
		// as Entity::setId() does not accept null in all NC versions.
		$copy = new Contact();
		$copy->setVcardUid('crm-' . bin2hex(random_bytes(8)));
		$copy->setEmail($src->getEmail());
		$copy->setFirstName($src->getFirstName());
		$copy->setLastName($src->getLastName());
		$copy->setPhone($src->getPhone());
		$copy->setOwnerId($src->getOwnerId());
		$copy->setStatus($src->getStatus());
		$copy->setCreatedAt(time());
		$copy->setUpdatedAt(time());
		$saved = $this->contactMapper->insert($copy);
		return new DataResponse($saved->jsonSerialize(), Http::STATUS_CREATED);
	}

	/**
	 * Returns true if the current user may access the given contact.
	 * Contacts with no owner are accessible by any authenticated user.
	 */
	private function canAccess(Contact $contact): bool {
		$uid = $this->userSession->getUser()?->getUID();
		if ($uid === null) {
			return false;
		}
		return $contact->getOwnerId() === null || $contact->getOwnerId() === $uid;
	}
}
