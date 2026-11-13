<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Service;

use OCA\ReidelCrm\AppInfo\Application;
use OCA\ReidelCrm\Db\Project;
use OCA\ReidelCrm\Db\ProjectMapper;
use OCP\IAppConfig;
use OCP\IServerContainer;
use Psr\Log\LoggerInterface;

/**
 * Phase C1 — NC Calendar integration.
 *
 * Creates and maintains a VEVENT in the contact owner's default NC Calendar
 * for each CRM project that has a due_date set.  The event UID is stored in
 * project.cal_event_uid so future updates or deletions can find it.
 *
 * Soft-depends on OCA\DAV (CalDavBackend).  If the DAV app is absent the
 * service silently no-ops so the CRM still functions without Calendar.
 *
 * Loop safety: CalendarObjectUpdated events are emitted by CalDavBackend but
 * we do not register a listener for calendar events, so there is no recursion.
 */
class CalendarService {

	public function __construct(
		private ProjectMapper $projectMapper,
		private ActivityService $activity,
		private IServerContainer $serverContainer,
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Create or update a calendar event for the project's due date.
	 * The event is an all-day VEVENT on the due_date day.
	 */
	public function upsertProjectEvent(Project $project, string $ownerId): void {
		if (!class_exists(\OCA\DAV\CalDAV\CalDavBackend::class)) {
			return;
		}
		if ($project->getDueDate() === null) {
			return;
		}

		try {
			$backend   = $this->getBackend();
			$calendarId = $this->resolveDefaultCalendarId($backend, $ownerId);
			if ($calendarId === null) {
				return;
			}

			$uid      = $project->getCalEventUid() ?? $this->generateEventUid($project);
			$objectUri = $uid . '.ics';
			$icsData  = $this->buildIcsEvent($uid, $project);

			$existing = $backend->getCalendarObject($calendarId, $objectUri);
			if ($existing !== false && $existing !== null) {
				$backend->updateCalendarObject($calendarId, $objectUri, $icsData);
			} else {
				$backend->createCalendarObject($calendarId, $objectUri, $icsData);
			}

			// Persist the UID so we can update/delete the event later.
			if ($project->getCalEventUid() !== $uid) {
				$project->setCalEventUid($uid);
				$project->setUpdatedAt(time());
				$this->projectMapper->update($project);
			}

			$this->activity->log(
				contactCacheId: $project->getContactCacheId(),
				type: ActivityService::TYPE_CALENDAR_EVENT,
				summary: sprintf(
					'Calendar event set for project "%s" (due %s)',
					$project->getProjectName(),
					date('Y-m-d', $project->getDueDate()),
				),
				projectId: $project->getId(),
				metadata: ['cal_event_uid' => $uid, 'due_date' => $project->getDueDate()],
			);

			// ── Admin visibility: clear error state on success ─────────────
			if ((int)$this->appConfig->getValueString(Application::APP_ID, 'cal_error_count', '0') > 0) {
				$this->appConfig->setValueString(Application::APP_ID, 'cal_error_count', '0');
				$this->appConfig->setValueString(Application::APP_ID, 'cal_last_error', '');
				$this->appConfig->setValueString(Application::APP_ID, 'cal_last_error_at', '0');
			}
		} catch (\Throwable $e) {
			// ── Admin visibility: record failure count and message ──────────
			$count = (int)$this->appConfig->getValueString(Application::APP_ID, 'cal_error_count', '0');
			$this->appConfig->setValueString(Application::APP_ID, 'cal_error_count', (string)($count + 1));
			$this->appConfig->setValueString(Application::APP_ID, 'cal_last_error', mb_substr($e->getMessage(), 0, 500));
			$this->appConfig->setValueString(Application::APP_ID, 'cal_last_error_at', (string)time());

			$this->logger->warning(
				'CalendarService::upsertProjectEvent failed: ' . $e->getMessage(),
				['exception' => $e, 'project_id' => $project->getId()],
			);
		}
	}

	/**
	 * Remove the calendar event when a project's due_date is cleared.
	 */
	public function deleteProjectEvent(Project $project, string $ownerId): void {
		if (!class_exists(\OCA\DAV\CalDAV\CalDavBackend::class)) {
			return;
		}
		$uid = $project->getCalEventUid();
		if ($uid === null || $uid === '') {
			return;
		}

		try {
			$backend    = $this->getBackend();
			$calendarId = $this->resolveDefaultCalendarId($backend, $ownerId);
			if ($calendarId === null) {
				return;
			}
			$objectUri = $uid . '.ics';
			$existing  = $backend->getCalendarObject($calendarId, $objectUri);
			if ($existing !== false && $existing !== null) {
				$backend->deleteCalendarObject($calendarId, $objectUri);
			}
			$project->setCalEventUid(null);
			$project->setUpdatedAt(time());
			$this->projectMapper->update($project);
		} catch (\Throwable $e) {
			$this->logger->warning(
				'CalendarService::deleteProjectEvent failed: ' . $e->getMessage(),
				['exception' => $e, 'project_id' => $project->getId()],
			);
		}
	}

	// ── Helpers ────────────────────────────────────────────────────────────

	/** @var \OCA\DAV\CalDAV\CalDavBackend|null */
	private mixed $backendCache = null;

	private function getBackend(): \OCA\DAV\CalDAV\CalDavBackend {
		if ($this->backendCache === null) {
			$this->backendCache = $this->serverContainer->get(\OCA\DAV\CalDAV\CalDavBackend::class);
		}
		return $this->backendCache;
	}

	private function resolveDefaultCalendarId(\OCA\DAV\CalDAV\CalDavBackend $backend, string $ownerId): ?int {
		$principal  = 'principals/users/' . $ownerId;
		$calendars  = $backend->getCalendarsForUser($principal);
		if (empty($calendars)) {
			$this->logger->debug('CalendarService: no calendars found for ' . $ownerId);
			return null;
		}
		// Prefer the calendar named 'personal' or 'default', else use the first.
		foreach ($calendars as $cal) {
			$uri = strtolower((string)($cal['uri'] ?? ''));
			if ($uri === 'personal' || $uri === 'default') {
				return (int)$cal['id'];
			}
		}
		return (int)$calendars[0]['id'];
	}

	private function generateEventUid(Project $project): string {
		return sprintf(
			'crm-project-%d-%s@%s',
			$project->getId(),
			bin2hex(random_bytes(4)),
			Application::APP_ID,
		);
	}

	/**
	 * Build a minimal iCalendar (RFC 5545) VEVENT string.
	 * The event is an all-day event on the due_date.
	 */
	private function buildIcsEvent(string $uid, Project $project): string {
		$dueDate = $project->getDueDate();
		// All-day: DATE format YYYYMMDD; DTEND = due_date + 1 day
		$dtStart = date('Ymd', $dueDate);
		$dtEnd   = date('Ymd', $dueDate + 86400);
		$now     = gmdate('Ymd\THis\Z');
		$summary = $this->escapeIcsText($project->getProjectName());

		return "BEGIN:VCALENDAR\r\n"
			. "VERSION:2.0\r\n"
			. "PRODID:-//ReidelCRM//CRM Hub//EN\r\n"
			. "BEGIN:VEVENT\r\n"
			. "UID:{$uid}\r\n"
			. "DTSTAMP:{$now}\r\n"
			. "DTSTART;VALUE=DATE:{$dtStart}\r\n"
			. "DTEND;VALUE=DATE:{$dtEnd}\r\n"
			. "SUMMARY:{$summary}\r\n"
			. "DESCRIPTION:CRM project due date — managed by Reidel CRM Hub.\r\n"
			. "X-CRM-PROJECT-ID:{$project->getId()}\r\n"
			. "END:VEVENT\r\n"
			. "END:VCALENDAR\r\n";
	}

	/** Escape text values per RFC 5545 §3.3.11. */
	private function escapeIcsText(string $text): string {
		return str_replace(
			['\\', ';', ',', "\n"],
			['\\\\', '\;', '\,', '\n'],
			$text,
		);
	}
}
