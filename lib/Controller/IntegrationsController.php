<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Controller;

use OCA\ReidelCrm\AppInfo\Application;
use OCA\ReidelCrm\Db\ProjectMapper;
use OCA\ReidelCrm\Service\ActivityService;
use OCA\ReidelCrm\Service\ContactCacheService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\IAppConfig;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * REST surface for Windmill workflows. All routes are public (no NC user
 * session) but protected by bearer-token auth in WindmillAuthMiddleware.
 */
class IntegrationsController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private ContactCacheService $contacts,
		private ProjectMapper $projects,
		private ActivityService $activity,
		private IRootFolder $rootFolder,
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	public function emailMatch(): DataResponse {
		$from = (string)$this->request->getParam('from', '');
		$to = (string)$this->request->getParam('to', '');
		$candidates = array_filter([$from, $to]);
		foreach ($candidates as $email) {
			$contact = $this->contacts->findByEmail($email);
			if ($contact !== null) {
				$projects = $this->projects->findByContactId($contact->getId());
				$active = null;
				foreach ($projects as $p) {
					if ($p->getStatus() === 'active') {
						$active = $p;
						break;
					}
				}
				return new DataResponse([
					'matched' => true,
					'contact' => $contact->jsonSerialize(),
					'project' => ($active ?? $projects[0] ?? null)?->jsonSerialize(),
				]);
			}
		}
		return new DataResponse(['matched' => false]);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	public function emailArchive(): DataResponse {
		$contactId = (int)$this->request->getParam('contact_cache_id', 0);
		$projectId = (int)$this->request->getParam('project_id', 0);
		$subject = (string)$this->request->getParam('subject', 'message');
		$rawB64 = (string)$this->request->getParam('raw_eml_base64', '');
		$direction = (string)$this->request->getParam('direction', 'received');
		$occurredAt = $this->request->getParam('occurred_at');

		if ($contactId === 0 || $projectId === 0 || $rawB64 === '') {
			return new DataResponse(['error' => 'contact_cache_id, project_id and raw_eml_base64 required'], Http::STATUS_BAD_REQUEST);
		}
		// Guard against huge payloads: 50 MB binary cap (base64 inflates ~33%,
		// so the encoded string limit is ~68 MB of text).
		if (strlen($rawB64) > 68_000_000) {
			return new DataResponse(['error' => 'raw_eml_base64 exceeds 50 MB limit'], Http::STATUS_REQUEST_ENTITY_TOO_LARGE);
		}

		try {
			$project = $this->projects->find($projectId);
		} catch (\Throwable) {
			return new DataResponse(['error' => 'project not found'], Http::STATUS_NOT_FOUND);
		}
		$folderNodeId = $project->getFolderNodeId();
		if ($folderNodeId === null) {
			return new DataResponse(['error' => 'project has no linked folder'], Http::STATUS_CONFLICT);
		}

		try {
			$nodes = $this->rootFolder->getById($folderNodeId);
			if (empty($nodes)) {
				return new DataResponse(['error' => 'folder node not found'], Http::STATUS_NOT_FOUND);
			}
			/** @var \OCP\Files\Folder $folder */
			$folder = $nodes[0];

			$subdirName = $this->appConfig->getValueString(Application::APP_ID, 'email_archive_subfolder', 'Emails');
			$monthDir = date('Y-m');
			$emailsRoot = $folder->nodeExists($subdirName) ? $folder->get($subdirName) : $folder->newFolder($subdirName);
			$monthFolder = $emailsRoot->nodeExists($monthDir) ? $emailsRoot->get($monthDir) : $emailsRoot->newFolder($monthDir);

			$safeSubject = preg_replace('/[^A-Za-z0-9_\- ]+/', '_', $subject) ?? 'message';
			$fileName = sprintf('%s_%s.eml', date('Ymd_His'), mb_substr($safeSubject, 0, 80));
			$content = base64_decode($rawB64, true);
			if ($content === false) {
				return new DataResponse(['error' => 'raw_eml_base64 not valid base64'], Http::STATUS_BAD_REQUEST);
			}
			$file = $monthFolder->newFile($fileName, $content);
		} catch (NotPermittedException $e) {
			return new DataResponse(['error' => 'permission denied: ' . $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (\Throwable $e) {
			$this->logger->error('emailArchive failed: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$type = $direction === 'sent' ? ActivityService::TYPE_EMAIL_SENT : ActivityService::TYPE_EMAIL_RECEIVED;
		$this->activity->log(
			contactCacheId: $contactId,
			type: $type,
			summary: sprintf('Email %s: %s', $direction, $subject),
			projectId: $projectId,
			metadata: ['file_id' => $file->getId(), 'path' => $file->getPath()],
			source: 'windmill',
			occurredAt: $occurredAt !== null ? (int)$occurredAt : null,
		);

		return new DataResponse(['archived' => true, 'file_id' => $file->getId(), 'path' => $file->getPath()]);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	public function funnelEvent(): DataResponse {
		$contactId = (int)$this->request->getParam('contact_cache_id', 0);
		$funnel = (string)$this->request->getParam('funnel', '');
		$step = (string)$this->request->getParam('step', '');
		$action = (string)$this->request->getParam('action', '');
		if ($contactId === 0 || $funnel === '') {
			return new DataResponse(['error' => 'contact_cache_id and funnel required'], Http::STATUS_BAD_REQUEST);
		}
		$entry = $this->activity->log(
			contactCacheId: $contactId,
			type: ActivityService::TYPE_FUNNEL_EVENT,
			summary: sprintf('Funnel %s — %s (%s)', $funnel, $step ?: 'step', $action ?: 'event'),
			metadata: ['funnel' => $funnel, 'step' => $step, 'action' => $action],
			source: 'windmill',
		);
		return new DataResponse($entry->jsonSerialize());
	}
}
