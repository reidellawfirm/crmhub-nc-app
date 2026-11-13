<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\BackgroundJob;

use OCA\ReidelCrm\Service\GroundhoggSyncService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class SyncGroundhoggJob extends TimedJob {

	public function __construct(
		ITimeFactory $time,
		private GroundhoggSyncService $sync,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(3600); // hourly
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	protected function run(mixed $argument): void {
		try {
			// Pull last hour + 5min overlap
			$since = (new \DateTimeImmutable())->modify('-65 minutes');
			$result = $this->sync->importIncremental($since);
			$this->logger->info('GH incremental sync result: ' . json_encode($result));
		} catch (\Throwable $e) {
			$this->logger->error('GH sync job failed: ' . $e->getMessage(), ['exception' => $e]);
		}
	}
}
