<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Activity;

use OCA\ReidelCrm\AppInfo\Application;
use OCP\Activity\ActivitySettings;
use OCP\IL10N;

class Setting extends ActivitySettings {

	public function __construct(private IL10N $l10n) {
	}

	public function getIdentifier(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l10n->t('Reidel CRM events');
	}

	public function getGroupIdentifier(): string {
		return Application::APP_ID;
	}

	public function getGroupName(): string {
		return $this->l10n->t('Reidel CRM Hub');
	}

	public function getPriority(): int {
		return 70;
	}

	public function canChangeMail(): bool {
		return true;
	}

	public function canChangeNotification(): bool {
		return true;
	}

	public function isDefaultEnabledMail(): bool {
		return false;
	}

	public function isDefaultEnabledNotification(): bool {
		// Enable by default so CRM events are visible in the header bell
		// without requiring each user to manually opt-in.
		return true;
	}
}
