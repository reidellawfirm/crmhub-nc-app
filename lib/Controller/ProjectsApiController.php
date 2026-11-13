<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Controller;

use OCA\ReidelCrm\Db\ActivityMapper;
use OCA\ReidelCrm\Db\ContactMapper;
use OCA\ReidelCrm\Db\Project;
use OCA\ReidelCrm\Db\ProjectMapper;
use OCA\ReidelCrm\Service\ProjectService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class ProjectsApiController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private ProjectMapper $mapper,
		private ProjectService $projects,
		private IUserSession $userSession,
		private ActivityMapper $activityMapper,
		private ContactMapper $contactMapper,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function index(?string $status = null, int $limit = 100, int $offset = 0): DataResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		$rows = $this->mapper->searchForOwner($uid, $status, $limit, $offset);
		return new DataResponse(array_map(fn ($p) => $p->jsonSerialize(), $rows));
	}

	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		$p = $this->projects->find($id);
		if ($p === null) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		if (!$this->isOwnedProject($p)) {
			return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse($p->jsonSerialize());
	}

	#[NoAdminRequired]
	public function forContact(int $contactId): DataResponse {
		if (!$this->isOwnedContactId($contactId)) {
			return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse(array_map(
			fn ($p) => $p->jsonSerialize(),
			$this->projects->listForContact($contactId),
		));
	}

	#[NoAdminRequired]
	public function create(int $contactId): DataResponse {
		if (!$this->isOwnedContactId($contactId)) {
			return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}
		$data = $this->request->getParams();
		$saved = $this->projects->create($contactId, $data, $this->userSession->getUser()?->getUID());
		return new DataResponse($saved->jsonSerialize(), Http::STATUS_CREATED);
	}

	#[NoAdminRequired]
	public function update(int $id): DataResponse {
		$p = $this->projects->find($id);
		if ($p === null) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		if (!$this->isOwnedProject($p)) {
			return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}
		$data = $this->request->getParams();
		unset($data['id']);
		$updated = $this->projects->update($id, $data, $this->userSession->getUser()?->getUID());
		return $updated === null
			? new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND)
			: new DataResponse($updated->jsonSerialize());
	}

	#[NoAdminRequired]
	public function destroy(int $id): DataResponse {
		$p = $this->projects->find($id);
		if ($p === null) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		if (!$this->isOwnedProject($p)) {
			return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}
		$ok = $this->projects->delete($id);
		return new DataResponse(['deleted' => $ok], $ok ? Http::STATUS_OK : Http::STATUS_NOT_FOUND);
	}

	/**
	 * Returns activity log entries scoped to a single project.
	 * This is the proper endpoint for the ProjectDetail activity timeline —
	 * it avoids the frontend needing to fetch all contact activities and filter.
	 */
	#[NoAdminRequired]
	public function projectActivity(int $id, int $limit = 50): DataResponse {
		$p = $this->projects->find($id);
		if ($p === null) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		if (!$this->isOwnedProject($p)) {
			return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}
		$rows = $this->activityMapper->findByProjectId($id, min($limit, 100));
		return new DataResponse(array_map(fn ($a) => $a->jsonSerialize(), $rows));
	}

	/** Returns true if the current user owns (or may access) the contact that owns this project. */
	private function isOwnedProject(Project $project): bool {
		return $this->isOwnedContactId($project->getContactCacheId());
	}

	/**
	 * Returns true if the current user may access contacts (and therefore
	 * children) of the given contact_cache_id.  Unowned contacts (null owner)
	 * are accessible by any authenticated user.
	 */
	private function isOwnedContactId(int $contactId): bool {
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
