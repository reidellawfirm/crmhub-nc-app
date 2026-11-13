<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Activity;

use OCA\ReidelCrm\AppInfo\Application;
use OCP\Activity\IFilter;
use OCP\IL10N;
use OCP\IURLGenerator;

/**
 * Registers a dedicated "CRM Hub" tab in the Nextcloud Activity page.
 *
 * After this filter is registered the following URLs become active:
 *   /apps/activity/crmhub_nc_app   – all CRM activities for the current user
 *
 * The header bell already shows CRM events as long as the Setting class has
 * isDefaultEnabledNotification() = true.  This Filter only controls the
 * /apps/activity page sidebar tab.
 */
class Filter implements IFilter {

	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
	) {
	}

	/**
	 * The URL segment: /apps/activity/crmhub_nc_app
	 */
	public function getIdentifier(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l10n->t('CRM Hub');
	}

	/**
	 * Priority 50 places the tab between Files (0) and the generic ones (>60).
	 */
	public function getPriority(): int {
		return 50;
	}

	public function getIcon(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app.svg'),
		);
	}

	/**
	 * Return only the types that belong to this app (i.e. keep 'crmhub_nc_app',
	 * drop everything else).  The activity app passes the full list of all
	 * registered type identifiers and expects us to filter it.
	 *
	 * @param string[] $types
	 * @return string[]
	 */
	public function filterTypes(array $types): array {
		return array_values(array_filter($types, static function (string $t): bool {
			return $t === Application::APP_ID;
		}));
	}

	/**
	 * Only show events whose app equals our own APP_ID.
	 * Returning an empty array would mean "show all apps" – we want only ours.
	 *
	 * @return string[]
	 */
	public function allowedApps(): array {
		return [Application::APP_ID];
	}
}
