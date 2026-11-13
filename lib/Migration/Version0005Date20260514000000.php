<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * V0005 — Add UNIQUE(email) to reidel_contacts_cache.
 *
 * "Email is the primary contact identifier" (CRM architecture requirement).
 * The service layer (ContactCacheService::validateContactData) already enforces
 * this at runtime. This migration adds the DB-level constraint as a last-resort
 * guard and replaces the non-unique reidel_cc_email index with a unique one.
 *
 * Safety:
 *   - preSchemaChange() counts existing duplicate emails. If duplicates are
 *     found the schema migration is skipped and a prominent warning is emitted.
 *     The service-layer uniqueness check still prevents NEW duplicates from
 *     being created. Admins can re-run the migration after cleaning up data
 *     (e.g. via the "Merge contacts" feature).
 *   - The unique index is case-insensitive on MySQL/MariaDB (default collation)
 *     and the service layer normalises emails (trimmed, lowercase comparison)
 *     on all platforms, so there is no platform inconsistency.
 */
class Version0005Date20260514000000 extends SimpleMigrationStep {

	private bool $skipUniqueIndex = false;

	public function __construct(private readonly IDBConnection $db) {
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Count duplicate emails using the QueryBuilder (no getTablePrefix() call needed).
		// Two queries: total non-null emails vs distinct lower-cased emails.
		// If total > distinct, duplicates exist and we skip the UNIQUE index.
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->selectAlias($qb->createFunction('COUNT(*)'), 'total')
				->from('reidel_contacts_cache')
				->where($qb->expr()->isNotNull('email'))
				->andWhere($qb->expr()->neq(
					$qb->createFunction('TRIM(email)'),
					$qb->createNamedParameter(''),
				));
			$res = $qb->executeQuery();
			$total = (int)($res->fetch()['total'] ?? 0);
			$res->closeCursor();

			$qb2 = $this->db->getQueryBuilder();
			$qb2->selectAlias($qb2->createFunction('COUNT(DISTINCT LOWER(TRIM(email)))'), 'uniq')
				->from('reidel_contacts_cache')
				->where($qb2->expr()->isNotNull('email'))
				->andWhere($qb2->expr()->neq(
					$qb2->createFunction('TRIM(email)'),
					$qb2->createNamedParameter(''),
				));
			$res2 = $qb2->executeQuery();
			$uniq = (int)($res2->fetch()['uniq'] ?? 0);
			$res2->closeCursor();

			$dupCount = ($total > $uniq) ? ($total - $uniq) : 0;
		} catch (\Throwable) {
			// Table doesn't exist yet (fresh install) — no duplicates.
			$dupCount = 0;
		}

		if ($dupCount > 0) {
			$this->skipUniqueIndex = true;
			$output->warning(sprintf(
				'[ReidelCRM] Skipping UNIQUE(email) index: %d duplicate email(s) found in reidel_contacts_cache. '
				. 'Use the "Merge contacts" tool to resolve duplicates, then re-run migrations.',
				$dupCount,
			));
		}
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		if ($this->skipUniqueIndex) {
			return null;
		}

		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('reidel_contacts_cache')) {
			return null;
		}

		$table = $schema->getTable('reidel_contacts_cache');

		// Nothing to do if the unique index is already present.
		if ($table->hasIndex('reidel_cc_email_uq')) {
			return null;
		}

		// Drop the non-unique predecessor index so MySQL doesn't complain about
		// a redundant index on the same column after we add the unique one.
		if ($table->hasIndex('reidel_cc_email')) {
			$table->dropIndex('reidel_cc_email');
		}

		// Add UNIQUE index. This covers the single-column case.
		// Per-owner uniqueness (owner_id, email) was considered but rejected:
		// "One email = one CRM contact" means global uniqueness is required —
		// the same external contact should not appear twice under different NC users.
		$table->addUniqueIndex(['email'], 'reidel_cc_email_uq');

		return $schema;
	}
}
