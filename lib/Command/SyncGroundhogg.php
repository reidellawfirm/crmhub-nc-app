<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Command;

use OCA\ReidelCrm\Service\GroundhoggSyncService;
use OC\Core\Command\Base;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SyncGroundhogg extends Base {

	public function __construct(private GroundhoggSyncService $sync) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('crmhub_nc_app:sync')
			->setDescription('Sync contacts from Groundhogg into the Reidel CRM cache')
			->addOption('full', null, InputOption::VALUE_NONE, 'Run full import (ignore last sync timestamp)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$result = $input->getOption('full')
			? $this->sync->importAll()
			: $this->sync->importIncremental((new \DateTimeImmutable())->modify('-1 day'));
		$output->writeln(json_encode($result, JSON_PRETTY_PRINT));
		return 0;
	}
}
