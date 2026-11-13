<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Settings;

use OCA\ReidelCrm\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\Settings\ISettings;

class Admin implements ISettings {

	public function __construct(
		private IAppConfig $appConfig,
		private IInitialState $initialState,
	) {
	}

	public function getForm(): TemplateResponse {
		$this->initialState->provideInitialState('crmhub_nc_app-config', [
			'windmill_token_set' => $this->appConfig->getValueString(Application::APP_ID, 'windmill_token', '') !== '',
			'gh_base_url' => $this->appConfig->getValueString(Application::APP_ID, 'gh_base_url', ''),
			'gh_api_key_set' => $this->appConfig->getValueString(Application::APP_ID, 'gh_api_key', '') !== '',
			'email_archive_subfolder' => $this->appConfig->getValueString(Application::APP_ID, 'email_archive_subfolder', 'Emails'),
			'default_project_folder' => $this->appConfig->getValueString(Application::APP_ID, 'default_project_folder', 'CRM Projects'),
			'onboarding_enabled' => $this->appConfig->getValueString(Application::APP_ID, 'onboarding_enabled', 'no'),
			'onboarding_project_template' => $this->appConfig->getValueString(Application::APP_ID, 'onboarding_project_template', 'New Matter'),
			'onboarding_project_type' => $this->appConfig->getValueString(Application::APP_ID, 'onboarding_project_type', ''),
			'onboarding_auto_folder' => $this->appConfig->getValueString(Application::APP_ID, 'onboarding_auto_folder', 'no'),
			'onboarding_deck_board' => $this->appConfig->getValueString(Application::APP_ID, 'onboarding_deck_board', ''),
		]);
		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection(): string {
		return 'crmhub_nc_app';
	}

	public function getPriority(): int {
		return 10;
	}
}
