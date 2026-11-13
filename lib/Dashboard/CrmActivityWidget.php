<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Dashboard;

use OCA\ReidelCrm\AppInfo\Application;
use OCA\ReidelCrm\Db\ActivityMapper;
use OCA\ReidelCrm\Db\ContactMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Dashboard\IAPIWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\Model\WidgetItem;
use OCP\IL10N;
use OCP\IURLGenerator;

class CrmActivityWidget implements IAPIWidget, IIconWidget {

	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private ActivityMapper $activity,
		private ContactMapper $contacts,
	) {
	}

	public function getId(): string {
		return Application::APP_ID . '-activity';
	}

	public function getTitle(): string {
		return $this->l10n->t('Recent client activity');
	}

	public function getOrder(): int {
		return 20;
	}

	public function getIconClass(): string {
		return 'icon-reidel-crm';
	}

	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app.svg')
		);
	}

	public function getUrl(): ?string {
		return $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.page.index');
	}

	public function load(): void {
	}

	public function getItems(string $userId, ?string $since = null, int $limit = 7): array {
		// Convert the NC Dashboard sinceId (our row-ID string) back to an integer.
		// Only filter when a valid positive ID is provided to avoid fetching
		// everything on the very first load ($since = null) while still being
		// efficient on subsequent incremental polls.
		$sinceId = ($since !== null && ctype_digit($since) && (int)$since > 0)
			? (int)$since
			: null;

		$items = [];
		foreach ($this->activity->findRecentForOwner($userId, $limit, $sinceId) as $entry) {
			$contactName = '';
			try {
				$c = $this->contacts->find($entry->getContactCacheId());
				$contactName = trim(($c->getFirstName() ?? '') . ' ' . ($c->getLastName() ?? '')) ?: ($c->getEmail() ?: '');
			} catch (DoesNotExistException) {
				$contactName = '#' . $entry->getContactCacheId();
			} catch (\Throwable) {
				// ignore
			}

			// Use the row's integer ID as sinceId — guaranteed unique and
			// monotonically increasing.  NC Dashboard uses this to fetch only
			// new items on subsequent polls (?sinceIds[widget_id]=N).
			$items[] = new WidgetItem(
				$contactName ?: 'CRM',
				(string)($entry->getSummary() ?? ''),
				// Deep-link to the specific contact in the CRM SPA.  The router
				// uses hash history, so we append the fragment to the app root.
				$this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.page.index')
					. '#/contacts/' . $entry->getContactCacheId(),
				$this->getIconUrl(),
				(string)$entry->getId(),   // sinceId = unique row ID (not timestamp)
			);
		}
		return $items;
	}
}
