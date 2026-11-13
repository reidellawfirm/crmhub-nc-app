<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Controller;

use OCA\ReidelCrm\Db\ActivityMapper;
use OCA\ReidelCrm\Db\ContactMapper;
use OCA\ReidelCrm\Db\NoteMapper;
use OCA\ReidelCrm\Db\ProjectMapper;
use OCA\ReidelCrm\Db\Task;
use OCA\ReidelCrm\Db\TaskMapper;
use OCA\ReidelCrm\Exception\ValidationException;
use OCA\ReidelCrm\Service\ActivityService;
use OCA\ReidelCrm\Service\ContactCacheService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class ContactsApiController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private ContactMapper $contactMapper,
		private ContactCacheService $contacts,
		private ProjectMapper $projectMapper,
		private NoteMapper $noteMapper,
		private ActivityMapper $activityMapper,
		private TaskMapper $taskMapper,
		private ActivityService $activityService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function index(?string $q = null, ?string $status = null, int $limit = 50, int $offset = 0): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		// Cap to 200 per page to protect against accidental large fetches.
		$limit = max(1, min(200, $limit));
		$rows  = $this->contactMapper->search($q, $status, $limit, $offset, $userId);
		$total = $this->contactMapper->countSearch($q, $status, $userId);
		return new DataResponse([
			'items'   => array_map(fn ($c) => $c->jsonSerialize(), $rows),
			'total'   => $total,
			'limit'   => $limit,
			'offset'  => $offset,
			'hasMore' => ($offset + count($rows)) < $total,
		]);
	}

	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		$c = $this->contacts->find($id);
		if ($c === null) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		if (!$this->canAccess($c->getOwnerId())) {
			return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse($c->jsonSerialize());
	}

	#[NoAdminRequired]
	public function create(): DataResponse {
		// Always assign the authenticated session user as owner.
		// Never trust a client-supplied ownerId — doing so would allow any user
		// to create contacts owned by another user (IDOR / privilege escalation).
		$currentUserId = $this->userSession->getUser()?->getUID();

		// NC 30: decode the JSON request body explicitly.  IRequest::getParam()
		// works for JSON POST bodies via decodeContent(), but reading php://input
		// directly is more robust and avoids edge cases with middleware ordering.
		$body = [];
		$rawInput = file_get_contents('php://input');
		if ($rawInput !== false && $rawInput !== '') {
			$decoded = json_decode($rawInput, true);
			if (is_array($decoded)) {
				$body = $decoded;
			}
		}
		// Fall back to getParam() for URL-encoded forms or missing body.
		$p = fn(string $key) => $body[$key] ?? $this->request->getParam($key);

		$email    = trim((string)($p('email') ?? ''));
		$vcardUid = trim((string)($p('vcardUid') ?? ''));

		// When creating from Contacts integration, email may be empty.
		// Require at least one identifier (email OR vcardUid).
		if ($email === '' && $vcardUid === '') {
			return new DataResponse(
				['error' => 'validation', 'field' => 'email', 'message' => 'Email or vcardUid required'],
				Http::STATUS_UNPROCESSABLE_ENTITY,
			);
		}

		// Duplicate check: prevent creating a second CRM record for the same contact.
		if ($email !== '') {
			$existing = $this->contacts->findByEmail($email);
			if ($existing !== null) {
				return new DataResponse(
					['error' => 'duplicate', 'message' => 'CRM record already exists for this email'],
					Http::STATUS_CONFLICT,
				);
			}
		}
		if ($vcardUid !== '') {
			// findByVcardUidOrNull returns null when not found; findByVcardUid throws.
			$existing = $this->contacts->findByVcardUidOrNull($vcardUid);
			if ($existing !== null) {
				return new DataResponse(
					['error' => 'duplicate', 'message' => 'CRM record already exists for this contact'],
					Http::STATUS_CONFLICT,
				);
			}
		}

		$data = [
			'email'        => $email,
			'firstName'    => $p('firstName'),
			'lastName'     => $p('lastName'),
			'phone'        => $p('phone'),
			'organization' => $p('organization'),
			'ownerId'      => $currentUserId,
			'status'       => $p('status') ?? 'lead',
			'vcardUid'     => $vcardUid ?: null,
		];
		try {
			$created = $this->contacts->createManual($data);
		} catch (ValidationException $e) {
			return new DataResponse(
				['error' => 'validation', 'field' => $e->getField(), 'message' => $e->getMessage()],
				Http::STATUS_UNPROCESSABLE_ENTITY,
			);
		}
		return new DataResponse($created->jsonSerialize(), Http::STATUS_CREATED);
	}

	#[NoAdminRequired]
	public function update(int $id): DataResponse {
		$actorUserId = $this->userSession->getUser()?->getUID();
		// Ownership check before any mutation.
		$existing = $this->contacts->find($id);
		if ($existing === null) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		if (!$this->canAccess($existing->getOwnerId())) {
			return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}
		// Prefer direct JSON body parse over getParams() for the same robustness
		// reason as create() — NC 30 middleware ordering edge cases.
		$body = [];
		$rawInput = file_get_contents('php://input');
		if ($rawInput !== false && $rawInput !== '') {
			$decoded = json_decode($rawInput, true);
			if (is_array($decoded)) {
				$body = $decoded;
			}
		}
		if (empty($body)) {
			$body = $this->request->getParams();
		}
		unset($body['id']);
		try {
			$updated = $this->contacts->update($id, $body, $actorUserId);
		} catch (ValidationException $e) {
			return new DataResponse(
				['error' => 'validation', 'field' => $e->getField(), 'message' => $e->getMessage()],
				Http::STATUS_UNPROCESSABLE_ENTITY,
			);
		}
		if ($updated === null) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		return new DataResponse($updated->jsonSerialize());
	}

	#[NoAdminRequired]
	public function destroy(int $id): DataResponse {
		$existing = $this->contacts->find($id);
		if ($existing === null) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		if (!$this->canAccess($existing->getOwnerId())) {
			return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}
		$ok = $this->contacts->delete($id);
		return new DataResponse(['deleted' => $ok], $ok ? Http::STATUS_OK : Http::STATUS_NOT_FOUND);
	}

	#[NoAdminRequired]
	public function linkVcard(): DataResponse {
		$uid   = (string)$this->request->getParam('vcardUid');
		$vcard = (string)$this->request->getParam('vcardData');
		$book  = $this->request->getParam('addressbookUri');
		if ($uid === '' || $vcard === '') {
			return new DataResponse(['error' => 'vcardUid and vcardData are required'], Http::STATUS_BAD_REQUEST);
		}
		$currentUserId = $this->userSession->getUser()?->getUID();
		// Pass current user as both the owner (for new records) and the actor (for the activity log).
		$contact = $this->contacts->upsertFromVCard($uid, $vcard, $book, $currentUserId, $currentUserId);
		return new DataResponse($contact->jsonSerialize());
	}

	#[NoAdminRequired]
	public function listVcards(): DataResponse {
		// Placeholder: enumeration of CardDAV vCards is performed via the
		// standard /remote.php/dav/addressbooks/ endpoint by the SPA. Returning
		// the cached rows here lets the SPA suggest matches by email.
		$userId = $this->userSession->getUser()?->getUID();
		return new DataResponse(array_map(
			fn ($c) => $c->jsonSerialize(),
			$this->contactMapper->search(null, null, 500, 0, $userId),
		));
	}

	#[NoAdminRequired]
	public function activity(int $id, int $limit = 20): DataResponse {
		$c = $this->contacts->find($id);
		if ($c === null) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		if (!$this->canAccess($c->getOwnerId())) {
			return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}
		$rows = $this->activityMapper->findByContactId($id, $limit);
		return new DataResponse(array_map(fn ($a) => $a->jsonSerialize(), $rows));
	}

	#[NoAdminRequired]
	public function summary(int $id): DataResponse {
		$c = $this->contacts->find($id);
		if ($c === null) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		if (!$this->canAccess($c->getOwnerId())) {
			return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}
		$projects = $this->projectMapper->findByContactId($id);
		$notes = $this->noteMapper->findByContactId($id);
		$activity = $this->activityMapper->findByContactId($id, 20);
		return new DataResponse([
			'contact' => $c->jsonSerialize(),
			'projects' => array_map(fn ($p) => $p->jsonSerialize(), $projects),
			'notesCount' => count($notes),
			'activityCount' => count($activity),
			'linkedFolderCount' => count(array_filter($projects, fn ($p) => !empty($p->getFolderPath()))),
			'linkedDeckCount' => count(array_filter($projects, fn ($p) => $p->getDeckCardId() !== null)),
			'lastContactedAt' => count($activity) ? max(array_map(fn ($a) => $a->getOccurredAt() ?? 0, $activity)) : null,
		]);
	}

	#[NoAdminRequired]
	public function findByVcardUid(): DataResponse {
		$uid = (string)$this->request->getParam('vcardUid');
		if ($uid === '') {
			return new DataResponse(['error' => 'vcardUid required'], Http::STATUS_BAD_REQUEST);
		}
		$contact = $this->contacts->findByVcardUidOrNull($uid);
		if ($contact === null) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		if (!$this->canAccess($contact->getOwnerId())) {
			return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse($contact->jsonSerialize());
	}

	#[NoAdminRequired]
	public function findByEmail(): DataResponse {
		$email = (string)$this->request->getParam('email');
		if ($email === '') {
			return new DataResponse(['error' => 'email required'], Http::STATUS_BAD_REQUEST);
		}
		$contact = $this->contacts->findByEmail($email);
		if ($contact === null) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		if (!$this->canAccess($contact->getOwnerId())) {
			return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse($contact->jsonSerialize());
	}

	#[NoAdminRequired]
	public function contactsIntegration(): DataResponse {
		$email    = (string)$this->request->getParam('email');
		$vcardUid = (string)$this->request->getParam('vcardUid');

		// Require at least one identifier.
		if ($email === '' && $vcardUid === '') {
			return new DataResponse(['error' => 'email or vcardUid required'], Http::STATUS_BAD_REQUEST);
		}

		// vcardUid is the most reliable identifier (from the URL); try it first.
		// Fall back to email for contacts that have no vcardUid link yet.
		$contact = null;
		if ($vcardUid !== '') {
			$contact = $this->contacts->findByVcardUidOrNull($vcardUid);
		}
		if ($contact === null && $email !== '') {
			$contact = $this->contacts->findByEmail($email);
		}

		if ($contact === null) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		if (!$this->canAccess($contact->getOwnerId())) {
			return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}
		$projects = $this->projectMapper->findByContactId($contact->getId());
		$notes = $this->noteMapper->findByContactId($contact->getId());
		$activity = $this->activityMapper->findByContactId($contact->getId(), 20);
		return new DataResponse([
			'contact' => $contact->jsonSerialize(),
			'projects' => array_map(fn ($p) => $p->jsonSerialize(), $projects),
			'notes' => array_map(fn ($n) => $n->jsonSerialize(), $notes),
			'activity' => array_map(fn ($a) => $a->jsonSerialize(), $activity),
		]);
	}

	/**
	 * Returns recent CRM activities across all contacts owned by the current
	 * user. Used by the global ActivityFeed view and enriches each row with
	 * the contact display name so the timeline can show it directly.
	 *
	 * @param int      $limit  Maximum number of rows to return (capped at 100).
	 * @param int|null $since  When provided, only return entries with id > $since.
	 *                         The frontend stores the highest id seen and passes it
	 *                         on the next poll to avoid re-fetching existing rows.
	 */
	#[NoAdminRequired]
	public function recent(int $limit = 50, ?int $since = null): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse([], Http::STATUS_UNAUTHORIZED);
		}
		$sinceId = ($since !== null && $since > 0) ? $since : null;
		$entries = $this->activityMapper->findRecentForOwner($userId, min($limit, 100), $sinceId);
		// Batch-resolve contact display names to avoid N+1 queries in the frontend.
		$contactIds = array_unique(array_map(fn ($e) => $e->getContactCacheId(), $entries));
		$nameMap = [];
		foreach ($contactIds as $cid) {
			try {
				$c = $this->contactMapper->find($cid);
				$name = trim(($c->getFirstName() ?? '') . ' ' . ($c->getLastName() ?? ''));
				$nameMap[$cid] = $name !== '' ? $name : ($c->getEmail() ?: ('contact #' . $cid));
			} catch (\Throwable) {
				$nameMap[$cid] = 'contact #' . $cid;
			}
		}
		return new DataResponse(array_map(static function ($e) use ($nameMap) {
			$row = $e->jsonSerialize();
			$row['contactName'] = $nameMap[$e->getContactCacheId()] ?? null;
			return $row;
		}, $entries));
	}

	/**
	 * POST /api/contacts/{id}/activity
	 * Log a manual activity entry (call, meeting, note, email) from the Contacts panel.
	 */
	#[NoAdminRequired]
	public function createActivity(int $id): DataResponse {
		$c = $this->contacts->find($id);
		if ($c === null) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		if (!$this->canAccess($c->getOwnerId())) {
			return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}

		$body = $this->parseJsonBody();
		$type    = trim((string)($body['activityType'] ?? $body['activity_type'] ?? 'note'));
		$summary = trim((string)($body['summary'] ?? ''));

		if ($type === '' || $summary === '') {
			return new DataResponse(
				['error' => 'validation', 'message' => 'activityType and summary are required'],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}

		$entry = $this->activityService->log(
			contactCacheId: $id,
			type:           $type,
			summary:        $summary,
			source:         'manual',
			occurredAt:     time(),
		);

		return new DataResponse($entry->jsonSerialize(), Http::STATUS_CREATED);
	}

	/**
	 * GET /api/contacts/{id}/tasks
	 * Return open tasks for a contact (used by the Contacts panel TasksPanel).
	 */
	#[NoAdminRequired]
	public function getTasks(int $id): DataResponse {
		$c = $this->contacts->find($id);
		if ($c === null) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		if (!$this->canAccess($c->getOwnerId())) {
			return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}
		$includeCompleted = (bool)$this->request->getParam('include_completed', false);
		$tasks = $this->taskMapper->findByContactId($id, $includeCompleted);
		return new DataResponse(array_map(fn ($t) => $t->jsonSerialize(), $tasks));
	}

	/**
	 * POST /api/contacts/{id}/tasks
	 * Create a task for a contact from the Contacts panel QuickActionsBar.
	 */
	#[NoAdminRequired]
	public function createTask(int $id): DataResponse {
		$c = $this->contacts->find($id);
		if ($c === null) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		if (!$this->canAccess($c->getOwnerId())) {
			return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}

		$body  = $this->parseJsonBody();
		$title = trim((string)($body['title'] ?? ''));
		if ($title === '') {
			return new DataResponse(
				['error' => 'validation', 'message' => 'title is required'],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}

		$userId = $this->userSession->getUser()?->getUID() ?? '';

		$task = new Task();
		$task->setContactCacheId($id);
		$task->setOwnerUserId($userId);
		$task->setTitle($title);
		$task->setNotes(isset($body['notes']) ? trim((string)$body['notes']) : null);
		$task->setStatus('pending');
		$task->setDueAt(isset($body['dueAt']) ? (int)$body['dueAt'] : null);
		$task->setCreatedAt(time());

		$created = $this->taskMapper->insert($task);

		// Log activity for the task creation
		$this->activityService->log(
			contactCacheId: $id,
			type:           'task_created',
			summary:        'Task created: ' . $title,
			metadata:       ['task_id' => $created->getId()],
			source:         'manual',
			occurredAt:     time(),
		);

		return new DataResponse($created->jsonSerialize(), Http::STATUS_CREATED);
	}

	/**
	 * PUT /api/tasks/{id}
	 * Update a task (primarily used to mark it done from the Contacts panel).
	 */
	#[NoAdminRequired]
	public function updateTask(int $id): DataResponse {
		try {
			$task = $this->taskMapper->find($id);
		} catch (DoesNotExistException) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}

		$userId = $this->userSession->getUser()?->getUID();
		if ($task->getOwnerUserId() !== $userId) {
			return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}

		$body   = $this->parseJsonBody();
		$status = (string)($body['status'] ?? $task->getStatus());

		$task->setStatus($status);
		if ($status === 'done' && $task->getCompletedAt() === null) {
			$task->setCompletedAt(time());
		}
		if (isset($body['title']))  $task->setTitle(trim((string)$body['title']));
		if (isset($body['dueAt']))  $task->setDueAt((int)$body['dueAt'] ?: null);
		if (isset($body['notes']))  $task->setNotes(trim((string)$body['notes']));

		$updated = $this->taskMapper->update($task);
		return new DataResponse($updated->jsonSerialize());
	}

	/**
	 * POST /api/contacts/{id}/move-stage
	 * Move a contact to a new pipeline stage (updates all open projects + contact record).
	 */
	#[NoAdminRequired]
	public function moveStage(int $id): DataResponse {
		$c = $this->contacts->find($id);
		if ($c === null) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		if (!$this->canAccess($c->getOwnerId())) {
			return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}

		$body  = $this->parseJsonBody();
		$stage = trim((string)($body['stage'] ?? ''));
		if ($stage === '') {
			return new DataResponse(
				['error' => 'validation', 'message' => 'stage is required'],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}

		$userId = $this->userSession->getUser()?->getUID() ?? '';

		// Update the contact's own pipeline_stage field
		$this->contacts->update($id, ['pipelineStage' => $stage], $userId);

		// Also update all open projects for this contact
		$projects = $this->projectMapper->findByContactId($id);
		foreach ($projects as $project) {
			if (!in_array($project->getStatus(), ['archived', 'closed', 'won', 'lost'], true)) {
				$project->setPipelineStage($stage);
				$this->projectMapper->update($project);
			}
		}

		// Log the stage change
		$this->activityService->log(
			contactCacheId: $id,
			type:           'stage_changed',
			summary:        'Pipeline stage moved to: ' . $stage,
			metadata:       ['stage' => $stage],
			source:         'manual',
			occurredAt:     time(),
		);

		return new DataResponse(['stage' => $stage, 'contact_id' => $id]);
	}

	/**
	 * Decode JSON request body, falling back to getParams() for form posts.
	 */
	private function parseJsonBody(): array {
		$raw = file_get_contents('php://input');
		if ($raw !== false && $raw !== '') {
			$decoded = json_decode($raw, true);
			if (is_array($decoded)) {
				return $decoded;
			}
		}
		return $this->request->getParams();
	}

	/**
	 * Returns true when the current session user may read or write a contact
	 * with the given owner_id.
	 *
	 * Contacts with no owner (e.g. Groundhogg imports before user-ID mapping)
	 * are accessible by any authenticated user.  Contacts owned by a different
	 * NC user are forbidden (403).
	 */
	private function canAccess(?string $ownerId): bool {
		$uid = $this->userSession->getUser()?->getUID();
		if ($uid === null) {
			return false; // should not happen under #[NoAdminRequired]
		}
		return $ownerId === null || $ownerId === $uid;
	}
}
