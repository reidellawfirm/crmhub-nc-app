<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * V0004 — Performance indexes for production workloads.
 *
 * Adds a composite index `(status, due_date)` on reidel_projects so the
 * daily OverdueProjectJob can locate overdue active projects with a single
 * index seek rather than a full-table scan.
 *
 * Also adds `(deck_board_id)` index for DeckCardCreatedListener /
 * DeckBoardUpdatedListener lookups, and `(contact_cache_id)` on
 * reidel_notes if not already present (created by V0001 but listed
 * here for self-documentation).
 */
class Version0004Date20260514000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// ── reidel_projects ──────────────────────────────────────────────
		if ($schema->hasTable('reidel_projects')) {
			$table = $schema->getTable('reidel_projects');

			// Composite index for OverdueProjectJob:
			//   WHERE status = 'active' AND due_date IS NOT NULL AND due_date < ?
			if (!$table->hasIndex('rp_status_due_date')) {
				$table->addIndex(['status', 'due_date'], 'rp_status_due_date');
			}

			// Index for DeckCardCreatedListener / DeckBoardUpdatedListener:
			//   WHERE deck_board_id = ?
			if ($table->hasColumn('deck_board_id') && !$table->hasIndex('rp_deck_board_id')) {
				$table->addIndex(['deck_board_id'], 'rp_deck_board_id');
			}
		}

		// ── reidel_activity_log ──────────────────────────────────────────
		// Compound index for ActivityMapper::hasRecentEntry() dedup query:
		//   WHERE activity_type = ? AND summary = ? AND occurred_at >= ?
		//   AND (project_id = ? OR project_id IS NULL)
		// (Schema uses `activity_type` and `occurred_at` — V0001 names.)
		if ($schema->hasTable('reidel_activity_log')) {
			$table = $schema->getTable('reidel_activity_log');
			if ($table->hasColumn('activity_type')
				&& $table->hasColumn('occurred_at')
				&& !$table->hasIndex('ral_type_when')
			) {
				$table->addIndex(['activity_type', 'occurred_at'], 'ral_type_when');
			}
			// Project-scoped dedup lookups (mail listener, file upload listener)
			if ($table->hasColumn('project_id')
				&& $table->hasColumn('activity_type')
				&& !$table->hasIndex('ral_proj_type_when')
			) {
				$table->addIndex(['project_id', 'activity_type', 'occurred_at'], 'ral_proj_type_when');
			}
		}

		return $schema;
	}
}
