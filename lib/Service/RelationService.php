<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Service;

use OCA\ReidelCrm\Db\ContactMapper;
use OCA\ReidelCrm\Db\ProjectMapper;

/**
 * The "relational connector" core: given any one entity reference (contact id,
 * Files folder node id, or Deck card id) return the unified set of related
 * CRM records. Used by the SPA to render a single client view from any entry
 * point and by Windmill to look up ownership before archiving emails/files.
 */
class RelationService {

	public function __construct(
		private ContactMapper $contactMapper,
		private ProjectMapper $projectMapper,
	) {
	}

	public function lookup(?int $contactId = null, ?int $folderNodeId = null, ?int $deckCardId = null): array {
		$projects = [];
		$contact = null;

		if ($folderNodeId !== null) {
			$projects = $this->projectMapper->findByFolderNodeId($folderNodeId);
		} elseif ($deckCardId !== null) {
			$projects = $this->projectMapper->findByDeckCardId($deckCardId);
		} elseif ($contactId !== null) {
			$projects = $this->projectMapper->findByContactId($contactId);
		}

		if ($contactId !== null) {
			try {
				$contact = $this->contactMapper->find($contactId);
			} catch (\Throwable) {
				$contact = null;
			}
		} elseif (!empty($projects)) {
			try {
				$contact = $this->contactMapper->find($projects[0]->getContactCacheId());
			} catch (\Throwable) {
				$contact = null;
			}
		}

		return [
			'contact' => $contact,
			'projects' => $projects,
		];
	}
}
