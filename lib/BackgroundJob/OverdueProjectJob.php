<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\BackgroundJob;

use OCA\ReidelCrm\AppInfo\Application;
use OCA\ReidelCrm\Db\ActivityMapper;
use OCA\ReidelCrm\Db\ProjectMapper;
use OCA\ReidelCrm\Service\ActivityService;
use OCA\ReidelCrm\Service\NotificationService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

/**
 * Scans active CRM projects with a past due_date and logs a TYPE_OVERDUE
 * reminder activity on each, once per day.  The activity appears in the
 * contact timeline so sales reps are notified via the NC Activity bell.
 *
 * Configuration:
 *  - `overdue_threshold_days` — how many days past due before flagging
 *    (default 0 = flag on the due date itself).
 *
 * Deduplication: uses ActivityMapper::hasRecentEntry() with a 23-hour window
 * so each overdue project only gets one reminder per day even if the job
 * somehow runs twice.
 */
class OverdueProjectJob extends TimedJob {

	/** Default threshold — flag on the exact due date. */
	public const DEFAULT_THRESHOLD_DAYS = 0;

	/** Dedup window: slightly under 24 h so daily runs always produce one entry. */
	private const DEDUP_WINDOW_SECONDS = 23 * 3600;

	/** Maximum projects processed in a single run to avoid memory issues. */
	private const BATCH_LIMIT = 200;

	public function __construct(
		ITimeFactory $time,
		private ProjectMapper $projectMapper,
		private ActivityMapper $activityMapper,
		private ActivityService $activity,
		private NotificationService $notificationService,
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(24 * 3600);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	protected function run(mixed $argument): void {
		$thresholdDays = (int)$this->appConfig->getValueString(
			Application::APP_ID,
			'overdue_threshold_days',
			(string)self::DEFAULT_THRESHOLD_DAYS,
		);
		$thresholdDays = max(0, min(365, $thresholdDays));
		$dueBefore     = time() - ($thresholdDays * 86400);

		try {
			$projects = $this->projectMapper->findOverdue($dueBefore, self::BATCH_LIMIT);
			$logged   = 0;

			foreach ($projects as $project) {
				$summaryLine = sprintf(
					'Project "%s" is overdue (due %s)',
					$project->getProjectName(),
					date('Y-m-d', (int)$project->getDueDate()),
				);

				// Deduplicate: skip if we already logged this reminder today.
				if ($this->activityMapper->hasRecentEntry(
					$project->getId(),
					ActivityService::TYPE_OVERDUE,
					$summaryLine,
					self::DEDUP_WINDOW_SECONDS,
				)) {
					continue;
				}

				$this->activity->log(
					contactCacheId: $project->getContactCacheId(),
					type: ActivityService::TYPE_OVERDUE,
					projectId: $project->getId(),
					summary: $summaryLine,
					metadata: [
						'due_date'        => $project->getDueDate(),
						'threshold_days'  => $thresholdDays,
					],
				);
				// Push NC bell notification to the assigned user (if set).
				$assignee = $project->getAssignedUserId();
				if ($assignee !== null && $assignee !== '') {
					$this->notificationService->notifyOverdue($project, $assignee);
				}
				$logged++;
			}

			if ($logged > 0) {
				$this->logger->info(
					sprintf('OverdueProjectJob: logged %d overdue reminders', $logged),
				);
			}
		} catch (\Throwable $e) {
			$this->logger->error(
				'OverdueProjectJob failed: ' . $e->getMessage(),
				['exception' => $e],
			);
		}
	}
}
