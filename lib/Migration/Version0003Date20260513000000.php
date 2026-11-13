<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * V0003 — Production-hardening schema changes:
 *
 *  1. reidel_contacts_cache.organization  VARCHAR(255) nullable
 *     — stores the org/company name from the vCard ORG field so it is
 *       preserved in bidirectional sync.
 *
 *  2. reidel_activity_log composite index (contact_cache_id, id DESC)
 *     — dramatically speeds up the dashboard widget query
 *       (ORDER BY a.id DESC, WHERE a.id > :sinceId, JOIN on contact_cache_id)
 *       which was previously doing a full table scan + sort per user.
 *
 *  All changes are idempotent (hasColumn / hasIndex guards).
 */
class Version0003Date20260513000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// ── 1. contacts_cache.organization column ─────────────────────────
		if ($schema->hasTable('reidel_contacts_cache')) {
			$t = $schema->getTable('reidel_contacts_cache');
			if (!$t->hasColumn('organization')) {
				$t->addColumn('organization', Types::STRING, [
					'notnull' => false,
					'length'  => 255,
					'default' => null,
				]);
			}
			// Performance index on updated_at for freshness-ordered pagination
			if (!$t->hasIndex('reidel_cc_updated')) {
				$t->addIndex(['updated_at'], 'reidel_cc_updated');
			}
		}

		// ── 2. activity_log composite index ───────────────────────────────
		// SQLite and MySQL both support composite indexes; the (contact_cache_id, id)
		// pair satisfies both the JOIN predicate and the ORDER BY id DESC + WHERE id > N
		// in a single index scan, eliminating the expensive filesort.
		if ($schema->hasTable('reidel_activity_log')) {
			$t = $schema->getTable('reidel_activity_log');
			if (!$t->hasIndex('reidel_al_contact_id')) {
				$t->addIndex(['contact_cache_id', 'id'], 'reidel_al_contact_id');
			}
			// Composite for project timeline queries
			if (!$t->hasIndex('reidel_al_project_id')) {
				$t->addIndex(['project_id', 'id'], 'reidel_al_project_id');
			}
		}

		return $schema;
	}
}
