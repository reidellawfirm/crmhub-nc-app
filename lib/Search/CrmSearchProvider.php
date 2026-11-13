<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Search;

use OCA\ReidelCrm\AppInfo\Application;
use OCA\ReidelCrm\Db\ContactMapper;
use OCA\ReidelCrm\Db\NoteMapper;
use OCA\ReidelCrm\Db\ProjectMapper;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

class CrmSearchProvider implements IProvider {

	public function __construct(
		private readonly ContactMapper $contactMapper,
		private readonly ProjectMapper $projectMapper,
		private readonly NoteMapper $noteMapper,
		private readonly IURLGenerator $urlGenerator,
	) {}

	public function getId(): string {
		return Application::APP_ID . '_search';
	}

	public function getName(): string {
		return 'CRM Hub';
	}

	/**
	 * Order relative to other providers.
	 * Nextcloud Files = 0, Contacts app ≈ 25 – appear right after Contacts.
	 */
	public function getOrder(string $route, array $routeParameters): int {
		return 30;
	}

	public function search(IUser $user, ISearchQuery $query): SearchResult {
		$term = trim($query->getTerm());
		if ($term === '') {
			return SearchResult::complete($this->getName(), []);
		}

		$ownerId = $user->getUID();
		$limit   = $query->getLimit();
		$offset  = (int)($query->getCursor() ?? 0);
		$perType = (int)max(5, (int)ceil($limit / 3));
		$baseUrl = $this->urlGenerator->linkToRouteAbsolute('crmhub_nc_app.page.index');

		$entries = [];

		// ── Contacts ────────────────────────────────────────────────────────
		$contacts = $this->contactMapper->search(
			query: $term,
			status: null,
			limit: $perType,
			offset: $offset,
			ownerId: $ownerId,
		);
		foreach ($contacts as $contact) {
			$fullName = trim(($contact->getFirstName() ?? '') . ' ' . ($contact->getLastName() ?? ''));
			if ($fullName === '') {
				$fullName = $contact->getEmail();
			}
			$subline = $contact->getEmail();
			if ($contact->getPhone() !== null && $contact->getPhone() !== '') {
				$subline .= ' · ' . $contact->getPhone();
			}
			$entries[] = new SearchResultEntry(
				thumbnailUrl: '',
				title: $fullName,
				subline: $subline,
				resourceUrl: $baseUrl . '#/contacts/' . $contact->getId(),
				icon: 'icon-contacts',
				rounded: true,
			);
		}

		// ── Projects ─────────────────────────────────────────────────────────
		$projects = $this->projectMapper->searchByTerm($term, $ownerId, $perType, $offset);
		foreach ($projects as $project) {
			$typeLabel = $project->getProjectType() ?? $project->getStatus();
			$entries[] = new SearchResultEntry(
				thumbnailUrl: '',
				title: $project->getProjectName(),
				subline: 'Project · ' . $typeLabel,
				resourceUrl: $baseUrl . '#/projects/' . $project->getId(),
				icon: 'icon-calendar-dark',
				rounded: false,
			);
		}

		// ── Notes ────────────────────────────────────────────────────────────
		$notes = $this->noteMapper->searchByContent($term, $ownerId, $perType, $offset);
		foreach ($notes as $note) {
			$content = $note->getContent();
			$snippet = mb_strlen($content) > 80
				? mb_substr($content, 0, 80) . '…'
				: $content;
			$entries[] = new SearchResultEntry(
				thumbnailUrl: '',
				title: 'CRM Note',
				subline: $snippet,
				resourceUrl: $baseUrl . '#/contacts/' . $note->getContactCacheId(),
				icon: 'icon-comment',
				rounded: false,
			);
		}

		return SearchResult::complete($this->getName(), $entries);
	}
}
