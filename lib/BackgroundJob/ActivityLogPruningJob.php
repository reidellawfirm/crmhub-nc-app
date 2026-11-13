<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\BackgroundJob;

use OCA\ReidelCrm\AppInfo\Application;
use OCA\ReidelCrm\Db\ActivityMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

/**
 * Prunes old reidel_activity_log rows to prevent unbounded table growth.
 *
 * Runs once per day.  Retention period is configurable via the admin setting
 * `activity_log_retention_days` (default 180 days).  Deletion is performed
 * in safe batches of 500 rows so the DB is never locked for an extended period.
 *
 * The sync_log table has its own PurgeSyncLogJob with a 90-day retention;
 * activity_log entries are kept longer (180 d default) because they drive the
 * contact/project timeline views and the NC Activity history.
 */
class ActivityLogPruningJob extends TimedJob {

	/** Default retention in days when not configured by admin. */
	public const DEFAULT_RETENTION_DAYS = 180;

	public function __construct(
		ITimeFactory $time,
		private ActivityMapper $activityMapper,
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(24 * 3600);          // once per day
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	protected function run(mixed $argument): void {
		$retentionDays = (int)$this->appConfig->getValueString(
			Application::APP_ID,
			'activity_log_retention_days',
			(string)self::DEFAULT_RETENTION_DAYS,
		);

		// Sanity clamp: minimum 7 days, maximum 3 650 days (10 years).
		$retentionDays = max(7, min(3650, $retentionDays));
		$cutoff        = time() - ($retentionDays * 86400);

		try {
			$deleted = $this->activityMapper->purgeOlderThan($cutoff);
			if ($deleted > 0) {
				$this->logger->info(
					sprintf(
						'ActivityLogPruningJob: deleted %d rows older than %d days',
						$deleted,
						$retentionDays,
					),
				);
			}
		} catch (\Throwable $e) {
			$this->logger->error(
				'ActivityLogPruningJob failed: ' . $e->getMessage(),
				['exception' => $e],
			);
		}
	}
}
