<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Controller;

use OCA\ReidelCrm\Db\ActivityMapper;
use OCA\ReidelCrm\Db\ContactMapper;
use OCA\ReidelCrm\Db\NoteMapper;
use OCA\ReidelCrm\Db\ProjectMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Single endpoint that returns all data needed to render the CRM dashboard.
 * Designed to be called once on mount and refreshed by a bus event or timer.
 * All queries use owner-scoped SQL with no N+1 risk.
 */
class DashboardApiController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private ContactMapper $contactMapper,
		private ProjectMapper $projectMapper,
		private NoteMapper $noteMapper,
		private ActivityMapper $activityMapper,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Returns a single payload containing all dashboard data:
	 *   - KPI counters  (totalContacts, activeProjects, notesCount, activitiesToday)
	 *   - projectStatus map (status → count)
	 *   - recentContacts array (last 6 by updated_at)
	 *   - recentActivity array (last 8 by id desc)
	 */
	#[NoAdminRequired]
	public function summary(): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['error' => 'unauthenticated'], Http::STATUS_UNAUTHORIZED);
		}

		// ── 5 efficient owner-scoped queries — zero N+1 ─────────────────
		$totalContacts   = $this->contactMapper->countForOwner($userId);
		$notesCount      = $this->noteMapper->countForOwner($userId);
		$activitiesToday = $this->activityMapper->countTodayForOwner($userId);
		$projectStatus   = $this->projectMapper->countByStatusForOwner($userId);
		$activeProjects  = $projectStatus['active'] ?? 0;

		$recentContacts = array_map(
			fn ($c) => $c->jsonSerialize(),
			$this->contactMapper->findRecentForOwner($userId, 6),
		);

		$recentActivity = array_map(
			fn ($a) => $a->jsonSerialize(),
			$this->activityMapper->findRecentForOwner($userId, 8),
		);

		return new DataResponse([
			'kpi' => [
				'totalContacts'   => $totalContacts,
				'activeProjects'  => $activeProjects,
				'notesCount'      => $notesCount,
				'activitiesToday' => $activitiesToday,
			],
			'projectStatus'  => $projectStatus,
			'recentContacts' => $recentContacts,
			'recentActivity' => $recentActivity,
		]);
	}
}
