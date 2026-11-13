<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Controller;

use OCA\ReidelCrm\Db\SyncLogMapper;
use OCA\ReidelCrm\Service\GroundhoggSyncService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class SyncController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private GroundhoggSyncService $sync,
		private SyncLogMapper $syncLog,
	) {
		parent::__construct($appName, $request);
	}

	public function runIncremental(): DataResponse {
		$since = (new \DateTimeImmutable())->modify('-1 day');
		return new DataResponse($this->sync->importIncremental($since));
	}

	public function runFull(): DataResponse {
		return new DataResponse($this->sync->importAll());
	}

	public function log(int $limit = 100): DataResponse {
		return new DataResponse(array_map(fn ($e) => $e->jsonSerialize(), $this->syncLog->findRecent($limit)));
	}
}
