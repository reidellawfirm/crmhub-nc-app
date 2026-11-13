<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\BackgroundJob;

use OCA\ReidelCrm\Db\SyncLogMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class PurgeSyncLogJob extends TimedJob {

	public const RETENTION_SECONDS = 90 * 24 * 3600;

	public function __construct(
		ITimeFactory $time,
		private SyncLogMapper $syncLog,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(24 * 3600);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	protected function run(mixed $argument): void {
		try {
			$cutoff = time() - self::RETENTION_SECONDS;
			$deleted = $this->syncLog->purgeOlderThan($cutoff);
			if ($deleted > 0) {
				$this->logger->info("Purged {$deleted} old sync_log rows");
			}
		} catch (\Throwable $e) {
			$this->logger->error('PurgeSyncLogJob failed: ' . $e->getMessage(), ['exception' => $e]);
		}
	}
}
