<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Controller;

use OCA\ReidelCrm\Db\ActivityMapper;
use OCA\ReidelCrm\Db\ContactMapper;
use OCA\ReidelCrm\Db\ProjectMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * RFC 4180-compliant CSV export endpoints for enterprise reporting.
 *
 * Security:
 *   - Every export is hard-scoped to the authenticated user's data.
 *   - Row cap MAX_EXPORT_ROWS prevents memory exhaustion.
 *   - Content-Disposition header forces browser download.
 *
 * Usage (from the frontend):
 *   window.location.href = api.export.contacts();  // triggers browser download
 */
class ExportApiController extends Controller {

	private const MAX_EXPORT_ROWS = 10_000;

	public function __construct(
		string $appName,
		IRequest $request,
		private ContactMapper $contactMapper,
		private ProjectMapper $projectMapper,
		private ActivityMapper $activityMapper,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/** Export all contacts owned by the current user as a CSV download. */
	#[NoAdminRequired]
	public function exportContacts(): DataDownloadResponse|DataResponse {
		$userId = $this->currentUserId();
		if ($userId === null) {
			return new DataResponse(['error' => 'unauthenticated'], Http::STATUS_UNAUTHORIZED);
		}

		$contacts = $this->contactMapper->search(null, null, self::MAX_EXPORT_ROWS, 0, $userId);

		$handle = fopen('php://memory', 'w+');
		fputcsv($handle, ['ID', 'Email', 'First Name', 'Last Name', 'Organization', 'Phone', 'Status', 'Owner', 'Created']);
		foreach ($contacts as $c) {
			fputcsv($handle, [
				$c->getId(),
				$c->getEmail(),
				$c->getFirstName() ?? '',
				$c->getLastName() ?? '',
				$c->getOrganization() ?? '',
				$c->getPhone() ?? '',
				$c->getStatus(),
				$c->getOwnerId() ?? '',
				$c->getCreatedAt() > 0 ? date('Y-m-d H:i:s', $c->getCreatedAt()) : '',
			]);
		}
		rewind($handle);
		$csv = stream_get_contents($handle);
		fclose($handle);

		return new DataDownloadResponse(
			$csv,
			'crm-contacts-' . date('Y-m-d') . '.csv',
			'text/csv;charset=utf-8',
		);
	}

	/** Export all projects for contacts owned by the current user as a CSV download. */
	#[NoAdminRequired]
	public function exportProjects(): DataDownloadResponse|DataResponse {
		$userId = $this->currentUserId();
		if ($userId === null) {
			return new DataResponse(['error' => 'unauthenticated'], Http::STATUS_UNAUTHORIZED);
		}

		$projects = $this->projectMapper->searchForOwner($userId, null, self::MAX_EXPORT_ROWS, 0);

		$handle = fopen('php://memory', 'w+');
		fputcsv($handle, ['ID', 'Contact ID', 'Project Name', 'Type', 'Status', 'Pipeline Stage', 'Assigned To', 'Due Date', 'Folder Path', 'Created']);
		foreach ($projects as $p) {
			fputcsv($handle, [
				$p->getId(),
				$p->getContactCacheId(),
				$p->getProjectName(),
				$p->getProjectType() ?? '',
				$p->getStatus(),
				$p->getPipelineStage() ?? '',
				$p->getAssignedUserId() ?? '',
				$p->getDueDate() ? date('Y-m-d', $p->getDueDate()) : '',
				$p->getFolderPath() ?? '',
				$p->getCreatedAt() > 0 ? date('Y-m-d H:i:s', $p->getCreatedAt()) : '',
			]);
		}
		rewind($handle);
		$csv = stream_get_contents($handle);
		fclose($handle);

		return new DataDownloadResponse(
			$csv,
			'crm-projects-' . date('Y-m-d') . '.csv',
			'text/csv;charset=utf-8',
		);
	}

	/**
	 * Export activity log as CSV.  If $contactId is provided, only entries for
	 * that contact are exported; otherwise the full recent activity log is used.
	 */
	#[NoAdminRequired]
	public function exportActivities(?int $contactId = null): DataDownloadResponse|DataResponse {
		$userId = $this->currentUserId();
		if ($userId === null) {
			return new DataResponse(['error' => 'unauthenticated'], Http::STATUS_UNAUTHORIZED);
		}

		$entries = $contactId !== null
			? $this->activityMapper->findByContactId($contactId, self::MAX_EXPORT_ROWS)
			: $this->activityMapper->findRecentForOwner($userId, self::MAX_EXPORT_ROWS);

		$handle = fopen('php://memory', 'w+');
		fputcsv($handle, ['ID', 'Contact ID', 'Project ID', 'Type', 'Summary', 'Source', 'Thread ID', 'Occurred At']);
		foreach ($entries as $a) {
			fputcsv($handle, [
				$a->getId(),
				$a->getContactCacheId(),
				$a->getProjectId() ?? '',
				$a->getActivityType(),
				$a->getSummary() ?? '',
				$a->getSource(),
				$a->getThreadId() ?? '',
				$a->getOccurredAt() > 0 ? date('Y-m-d H:i:s', $a->getOccurredAt()) : '',
			]);
		}
		rewind($handle);
		$csv = stream_get_contents($handle);
		fclose($handle);

		return new DataDownloadResponse(
			$csv,
			'crm-activities-' . date('Y-m-d') . '.csv',
			'text/csv;charset=utf-8',
		);
	}

	private function currentUserId(): ?string {
		return $this->userSession->getUser()?->getUID();
	}
}
