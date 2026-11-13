<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * V0008 — Contacts-panel integration tables.
 *
 * New table:
 *   reidel_tasks — per-contact task/reminder records
 *
 * New column on reidel_contacts_cache:
 *   lead_source    VARCHAR(100)  — origin channel (website, referral, …)
 *   pipeline_stage VARCHAR(50)   — current stage (if not on a project)
 */
class Version0008Date20260604000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// ── reidel_tasks ────────────────────────────────────────────────────────
		if (!$schema->hasTable('reidel_tasks')) {
			$table = $schema->createTable('reidel_tasks');

			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull'       => true,
				'unsigned'      => true,
			]);
			$table->addColumn('contact_cache_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('owner_user_id', Types::STRING, [
				'notnull' => true,
				'length'  => 64,
			]);
			$table->addColumn('title', Types::STRING, [
				'notnull' => true,
				'length'  => 500,
			]);
			$table->addColumn('notes', Types::TEXT, [
				'notnull' => false,
				'default' => null,
			]);
			$table->addColumn('status', Types::STRING, [
				'notnull' => true,
				'length'  => 30,
				'default' => 'pending',
			]);
			$table->addColumn('due_at', Types::INTEGER, [
				'notnull' => false,
				'default' => null,
				'unsigned' => true,
			]);
			$table->addColumn('created_at', Types::INTEGER, [
				'notnull'  => true,
				'unsigned' => true,
			]);
			$table->addColumn('completed_at', Types::INTEGER, [
				'notnull' => false,
				'default' => null,
				'unsigned' => true,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['contact_cache_id'], 'idx_tasks_contact');
			$table->addIndex(['owner_user_id'],    'idx_tasks_owner');
			$table->addIndex(['due_at'],            'idx_tasks_due');
			// Partial-style: open tasks sorted by due date
			$table->addIndex(['contact_cache_id', 'status', 'due_at'], 'idx_tasks_contact_open');
		}

		// ── reidel_contacts_cache — new columns ─────────────────────────────────
		$contactsTable = $schema->getTable('reidel_contacts_cache');

		if (!$contactsTable->hasColumn('lead_source')) {
			$contactsTable->addColumn('lead_source', Types::STRING, [
				'notnull' => false,
				'length'  => 100,
				'default' => null,
			]);
		}

		if (!$contactsTable->hasColumn('pipeline_stage')) {
			$contactsTable->addColumn('pipeline_stage', Types::STRING, [
				'notnull' => false,
				'length'  => 50,
				'default' => null,
			]);
		}

		return $schema;
	}
}
