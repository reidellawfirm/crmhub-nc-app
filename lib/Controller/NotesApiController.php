<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Controller;

use OCA\ReidelCrm\Db\ContactMapper;
use OCA\ReidelCrm\Db\ProjectMapper;
use OCA\ReidelCrm\Service\NoteService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class NotesApiController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private NoteService $notes,
		private IUserSession $userSession,
		private ContactMapper $contactMapper,
		private ProjectMapper $projectMapper,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function forContact(int $contactId): DataResponse {
		if (!$this->canAccessContact($contactId)) {
			return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse(array_map(fn ($n) => $n->jsonSerialize(), $this->notes->listForContact($contactId)));
	}

	#[NoAdminRequired]
	public function forProject(int $projectId): DataResponse {
		try {
			$project = $this->projectMapper->find($projectId);
		} catch (\Throwable) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		if (!$this->canAccessContact($project->getContactCacheId())) {
			return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse(array_map(fn ($n) => $n->jsonSerialize(), $this->notes->listForProject($projectId)));
	}

	#[NoAdminRequired]
	public function create(): DataResponse {
		$contactId = (int)$this->request->getParam('contactCacheId');
		$projectId = $this->request->getParam('projectId');
		$content = (string)$this->request->getParam('content');
		if ($contactId === 0 || $content === '') {
			return new DataResponse(['error' => 'contactCacheId and content are required'], Http::STATUS_BAD_REQUEST);
		}
		if (!$this->canAccessContact($contactId)) {
			return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}
		$author = $this->userSession->getUser()?->getUID() ?? '';
		$saved = $this->notes->create($contactId, $projectId !== null ? (int)$projectId : null, $author, $content);
		return new DataResponse($saved->jsonSerialize(), Http::STATUS_CREATED);
	}

	#[NoAdminRequired]
	public function destroy(int $id): DataResponse {
		$note = $this->notes->find($id);
		if ($note === null) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		if (!$this->canAccessContact($note->getContactCacheId())) {
			return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}
		$ok = $this->notes->delete($id);
		return new DataResponse(['deleted' => $ok], $ok ? Http::STATUS_OK : Http::STATUS_NOT_FOUND);
	}

	/**
	 * Returns true if the current user may access the given contact (and its
	 * child notes, projects, activity).  Contacts with no owner are accessible
	 * by any authenticated user; contacts owned by another user are not.
	 */
	private function canAccessContact(int $contactId): bool {
		$uid = $this->userSession->getUser()?->getUID();
		if ($uid === null) {
			return false;
		}
		try {
			$contact = $this->contactMapper->find($contactId);
			return $contact->getOwnerId() === null || $contact->getOwnerId() === $uid;
		} catch (\Throwable) {
			return false;
		}
	}
}
