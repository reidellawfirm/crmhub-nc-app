<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0001Date20260505000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// ── contacts_cache ────────────────────────────────────────────────
		if (!$schema->hasTable('reidel_contacts_cache')) {
			$t = $schema->createTable('reidel_contacts_cache');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 8]);
			$t->addColumn('vcard_uid', Types::STRING, ['notnull' => true, 'length' => 255]);
			$t->addColumn('vcard_addressbook_uri', Types::STRING, ['notnull' => false, 'length' => 255]);
			$t->addColumn('email', Types::STRING, ['notnull' => true, 'length' => 255, 'default' => '']);
			$t->addColumn('first_name', Types::STRING, ['notnull' => false, 'length' => 255]);
			$t->addColumn('last_name', Types::STRING, ['notnull' => false, 'length' => 255]);
			$t->addColumn('phone', Types::STRING, ['notnull' => false, 'length' => 50]);
			$t->addColumn('owner_id', Types::STRING, ['notnull' => false, 'length' => 64]);
			$t->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'active']);
			// Groundhogg sync fields (nullable until Phase 4)
			$t->addColumn('gh_contact_id', Types::BIGINT, ['notnull' => false]);
			$t->addColumn('gh_optin_status', Types::SMALLINT, ['notnull' => false]);
			$t->addColumn('gh_tags_json', Types::TEXT, ['notnull' => false]);
			$t->addColumn('gh_meta_json', Types::TEXT, ['notnull' => false]);
			$t->addColumn('gh_date_created', Types::DATETIME, ['notnull' => false]);
			$t->addColumn('gh_last_synced', Types::DATETIME, ['notnull' => false]);
			$t->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$t->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);

			$t->setPrimaryKey(['id']);
			$t->addUniqueIndex(['vcard_uid'], 'reidel_cc_uid');
			$t->addUniqueIndex(['gh_contact_id'], 'reidel_cc_gh');
			$t->addIndex(['email'], 'reidel_cc_email');
			$t->addIndex(['owner_id'], 'reidel_cc_owner');
			$t->addIndex(['status'], 'reidel_cc_status');
			$t->addIndex(['last_name'], 'reidel_cc_lname');
		}

		// ── projects ──────────────────────────────────────────────────────
		if (!$schema->hasTable('reidel_projects')) {
			$t = $schema->createTable('reidel_projects');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 8]);
			$t->addColumn('contact_cache_id', Types::BIGINT, ['notnull' => true]);
			$t->addColumn('project_type', Types::STRING, ['notnull' => false, 'length' => 100]);
			$t->addColumn('project_name', Types::STRING, ['notnull' => true, 'length' => 255, 'default' => '']);
			$t->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'active']);
			$t->addColumn('folder_node_id', Types::BIGINT, ['notnull' => false]);
			$t->addColumn('folder_path', Types::STRING, ['notnull' => false, 'length' => 500]);
			$t->addColumn('deck_board_id', Types::BIGINT, ['notnull' => false]);
			$t->addColumn('deck_card_id', Types::BIGINT, ['notnull' => false]);
			$t->addColumn('assigned_user_id', Types::STRING, ['notnull' => false, 'length' => 64]);
			$t->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$t->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);

			$t->setPrimaryKey(['id']);
			$t->addIndex(['contact_cache_id'], 'reidel_p_contact');
			$t->addIndex(['status'], 'reidel_p_status');
			$t->addIndex(['assigned_user_id'], 'reidel_p_assignee');
			$t->addIndex(['project_type'], 'reidel_p_type');
			$t->addIndex(['folder_node_id'], 'reidel_p_folder');
			$t->addIndex(['deck_card_id'], 'reidel_p_deckcard');
		}

		// ── notes ─────────────────────────────────────────────────────────
		if (!$schema->hasTable('reidel_notes')) {
			$t = $schema->createTable('reidel_notes');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 8]);
			$t->addColumn('contact_cache_id', Types::BIGINT, ['notnull' => true]);
			$t->addColumn('project_id', Types::BIGINT, ['notnull' => false]);
			$t->addColumn('author_user_id', Types::STRING, ['notnull' => true, 'length' => 64, 'default' => '']);
			$t->addColumn('content', Types::TEXT, ['notnull' => true]);
			$t->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);

			$t->setPrimaryKey(['id']);
			$t->addIndex(['contact_cache_id'], 'reidel_n_contact');
			$t->addIndex(['project_id'], 'reidel_n_project');
			$t->addIndex(['author_user_id'], 'reidel_n_author');
		}

		// ── activity_log ──────────────────────────────────────────────────
		if (!$schema->hasTable('reidel_activity_log')) {
			$t = $schema->createTable('reidel_activity_log');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 8]);
			$t->addColumn('contact_cache_id', Types::BIGINT, ['notnull' => true]);
			$t->addColumn('project_id', Types::BIGINT, ['notnull' => false]);
			$t->addColumn('activity_type', Types::STRING, ['notnull' => true, 'length' => 50]);
			$t->addColumn('summary', Types::STRING, ['notnull' => false, 'length' => 500]);
			$t->addColumn('metadata_json', Types::TEXT, ['notnull' => false]);
			$t->addColumn('source', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'nextcloud']);
			$t->addColumn('occurred_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);

			$t->setPrimaryKey(['id']);
			$t->addIndex(['contact_cache_id'], 'reidel_al_contact');
			$t->addIndex(['project_id'], 'reidel_al_project');
			$t->addIndex(['activity_type'], 'reidel_al_type');
			$t->addIndex(['source'], 'reidel_al_source');
			$t->addIndex(['occurred_at'], 'reidel_al_when');
		}

		// ── sync_log ──────────────────────────────────────────────────────
		if (!$schema->hasTable('reidel_sync_log')) {
			$t = $schema->createTable('reidel_sync_log');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 8]);
			$t->addColumn('direction', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'in']);
			$t->addColumn('entity_type', Types::STRING, ['notnull' => true, 'length' => 50]);
			$t->addColumn('entity_id', Types::STRING, ['notnull' => false, 'length' => 64]);
			$t->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'success']);
			$t->addColumn('message', Types::TEXT, ['notnull' => false]);
			$t->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);

			$t->setPrimaryKey(['id']);
			$t->addIndex(['entity_type'], 'reidel_sl_etype');
			$t->addIndex(['status'], 'reidel_sl_status');
			$t->addIndex(['created_at'], 'reidel_sl_when');
		}

		return $schema;
	}
}
