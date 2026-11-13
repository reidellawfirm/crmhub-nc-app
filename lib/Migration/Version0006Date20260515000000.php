<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * V0006 — Normalise folder_path values in reidel_crm_projects.
 *
 * Background:
 *   resolveFolderPath() previously called Node::getPath() which returns the
 *   internal Nextcloud storage path:  /userId/files/Documents/sad/ewe
 *   The correct user-visible virtual path is:  /Documents/sad/ewe
 *
 * This migration strips the leading /{userId}/files segment from every
 * folder_path row that still contains the internal prefix so that
 * NC Files URLs (dir=/Documents/sad/ewe) work correctly.
 */
class Version0006Date20260515000000 extends SimpleMigrationStep {

	public function __construct(private readonly IDBConnection $db) {
	}

	/**
	 * No schema changes — data-only migration.
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		return null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Guard: if the table doesn't exist (e.g. fresh install where V0001 hasn't
		// run yet, or a test environment) there is nothing to normalise.
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('reidel_crm_projects')) {
			$output->info('V0006: reidel_crm_projects table not found — skipping.');
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'folder_path')
			->from('reidel_crm_projects')
			->where($qb->expr()->isNotNull('folder_path'));

		$result = $qb->executeQuery();
		$fixed  = 0;

		while ($row = $result->fetch()) {
			$original = (string)$row['folder_path'];

			// Detect paths that still carry the /{userId}/files prefix.
			// Pattern: starts with /something/files/ or equals /something/files
			if (!preg_match('#^/[^/]+/files(?=/|$)#', $original)) {
				continue; // already clean
			}

			// Strip the prefix — result is /Documents/sad/ewe or /
			$cleaned = (string)preg_replace('#^/[^/]+/files(?=/|$)#', '', $original);
			if ($cleaned === '') {
				$cleaned = '/';
			}

			$upd = $this->db->getQueryBuilder();
			$upd->update('reidel_crm_projects')
				->set('folder_path', $upd->createNamedParameter($cleaned))
				->where($upd->expr()->eq('id', $upd->createNamedParameter((int)$row['id'])));
			$upd->executeStatement();
			$fixed++;
		}
		$result->closeCursor();

		if ($fixed > 0) {
			$output->info(
				"V0006: normalised {$fixed} project folder path(s) "
				. "(removed internal /userId/files prefix).",
			);
		} else {
			$output->info('V0006: no folder_path rows required normalisation.');
		}
	}
}
