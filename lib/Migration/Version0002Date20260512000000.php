<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Phase C1 — adds due_date (unix timestamp) and cal_event_uid (iCal UID)
 *             to reidel_projects so Calendar integration can store events.
 */
class Version0002Date20260512000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('reidel_projects')) {
			$t = $schema->getTable('reidel_projects');

			if (!$t->hasColumn('due_date')) {
				$t->addColumn('due_date', Types::BIGINT, ['notnull' => false, 'default' => null]);
			}
			if (!$t->hasColumn('cal_event_uid')) {
				$t->addColumn('cal_event_uid', Types::STRING, ['notnull' => false, 'length' => 255]);
			}
		}

		return $schema;
	}
}
