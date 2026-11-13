<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\BackgroundJob;

use OCA\ReidelCrm\AppInfo\Application;
use OCA\ReidelCrm\Db\ContactMapper;
use OCA\ReidelCrm\Db\ProjectMapper;
use OCA\ReidelCrm\Service\CalendarService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

/**
 * Weekly catch-up job: reconciles CRM project due dates with NC Calendar.
 *
 * The real-time path (CalendarService called from ProjectService on save)
 * covers most cases.  This job heals divergence caused by:
 *   - Calendar events deleted externally
 *   - Server restarts during project saves
 *   - Projects with due dates created before the Calendar integration existed
 *
 * Schedule: once per week (TIME_INSENSITIVE — can run any time of day).
 * Runs separately from OverdueProjectJob (daily) to avoid overlap.
 *
 * Can be disabled via app config:  occ config:app:set crmhub_nc_app cal_sync_enabled --value=0
 */
class CalendarSyncJob extends TimedJob {

	private const BATCH_LIMIT = 500;

	public function __construct(
		ITimeFactory $time,
		private ProjectMapper $projectMapper,
		private ContactMapper $contactMapper,
		private CalendarService $calendarService,
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(7 * 24 * 3600);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	protected function run(mixed $argument): void {
		if ($this->appConfig->getValueString(Application::APP_ID, 'cal_sync_enabled', '1') !== '1') {
			$this->logger->debug('CalendarSyncJob: disabled via config, skipping');
			return;
		}

		if (!class_exists(\OCA\DAV\CalDAV\CalDavBackend::class)) {
			// DAV app not loaded — nothing to sync.
			return;
		}

		try {
			$projects = $this->projectMapper->findAllActiveWithDueDate(self::BATCH_LIMIT);
			$synced   = 0;
			$errors   = 0;

			foreach ($projects as $project) {
				try {
					$contact = $this->contactMapper->find($project->getContactCacheId());
					$ownerId = $contact->getOwnerId();
					if ($ownerId === null || $ownerId === '') {
						continue;
					}
					$this->calendarService->upsertProjectEvent($project, $ownerId);
					$synced++;
				} catch (\Throwable $e) {
					$errors++;
					$this->logger->debug(
						'CalendarSyncJob: project ' . $project->getId() . ' skipped — ' . $e->getMessage(),
					);
				}
			}

			$this->logger->info(
				sprintf('CalendarSyncJob: %d events synced, %d errors', $synced, $errors),
			);
		} catch (\Throwable $e) {
			$this->logger->error('CalendarSyncJob failed: ' . $e->getMessage(), ['exception' => $e]);
		}
	}
}
