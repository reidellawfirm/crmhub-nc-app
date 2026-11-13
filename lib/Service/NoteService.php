<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Service;

use OCA\ReidelCrm\Db\Note;
use OCA\ReidelCrm\Db\NoteMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class NoteService {

	public function __construct(
		private NoteMapper $mapper,
		private ActivityService $activity,
	) {
	}

	public function find(int $id): ?Note {
		try {
			return $this->mapper->find($id);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function create(int $contactId, ?int $projectId, string $authorUserId, string $content): Note {
		$note = new Note();
		$note->setContactCacheId($contactId);
		$note->setProjectId($projectId);
		$note->setAuthorUserId($authorUserId);
		$note->setContent($content);
		$note->setCreatedAt(time());
		$saved = $this->mapper->insert($note);

		$this->activity->log(
			contactCacheId: $contactId,
			type: ActivityService::TYPE_NOTE_ADDED,
			summary: 'Note added',
			projectId: $projectId,
			actorUserId: $authorUserId,
		);
		return $saved;
	}

	/** @return Note[] */
	public function listForContact(int $contactId): array {
		return $this->mapper->findByContactId($contactId);
	}

	/** @return Note[] */
	public function listForProject(int $projectId): array {
		return $this->mapper->findByProjectId($projectId);
	}

	public function delete(int $id): bool {
		try {
			$note = $this->mapper->find($id);
		} catch (DoesNotExistException) {
			return false;
		}
		$this->mapper->delete($note);
		return true;
	}
}
