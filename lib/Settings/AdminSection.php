<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Settings;

use OCA\ReidelCrm\AppInfo\Application;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class AdminSection implements IIconSection {

	public function __construct(
		private IL10N $l,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getID(): string {
		return 'crmhub_nc_app';
	}

	public function getName(): string {
		return $this->l->t('Reidel CRM Hub');
	}

	public function getPriority(): int {
		return 75;
	}

	public function getIcon(): string {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'app.svg');
	}
}
