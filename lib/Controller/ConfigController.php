<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Controller;

use OCA\ReidelCrm\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\Security\ICrypto;

class ConfigController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IAppConfig $appConfig,
		private ICrypto $crypto,
	) {
		parent::__construct($appName, $request);
	}

	public function getConfig(): DataResponse {
		$tokenExpiresAt = (int)$this->appConfig->getValueString(Application::APP_ID, 'windmill_token_expires_at', '0');
		return new DataResponse([
			'windmill_token_set'     => $this->appConfig->getValueString(Application::APP_ID, 'windmill_token', '') !== '',
			'windmill_token_expires_at' => $tokenExpiresAt,
			'windmill_token_expired' => $tokenExpiresAt > 0 && time() > $tokenExpiresAt,
			'gh_base_url'            => $this->appConfig->getValueString(Application::APP_ID, 'gh_base_url', ''),
			'gh_api_key_set'         => $this->appConfig->getValueString(Application::APP_ID, 'gh_api_key', '') !== '',
			'email_archive_subfolder' => $this->appConfig->getValueString(Application::APP_ID, 'email_archive_subfolder', 'Emails'),
			'default_project_folder' => $this->appConfig->getValueString(Application::APP_ID, 'default_project_folder', 'CRM Projects'),
			'onboarding_enabled'     => $this->appConfig->getValueString(Application::APP_ID, 'onboarding_enabled', 'no'),
			'onboarding_project_template' => $this->appConfig->getValueString(Application::APP_ID, 'onboarding_project_template', 'New Matter'),
			'onboarding_project_type'     => $this->appConfig->getValueString(Application::APP_ID, 'onboarding_project_type', ''),
			'onboarding_auto_folder'      => $this->appConfig->getValueString(Application::APP_ID, 'onboarding_auto_folder', 'no'),
			'onboarding_deck_board'       => $this->appConfig->getValueString(Application::APP_ID, 'onboarding_deck_board', ''),
			// Calendar integration health (set by CalendarService on failures)
			'cal_error_count'        => (int)$this->appConfig->getValueString(Application::APP_ID, 'cal_error_count', '0'),
			'cal_last_error'         => $this->appConfig->getValueString(Application::APP_ID, 'cal_last_error', ''),
			'cal_last_error_at'      => (int)$this->appConfig->getValueString(Application::APP_ID, 'cal_last_error_at', '0'),
			// Pruning / automation settings
			'activity_log_retention_days' => (int)$this->appConfig->getValueString(Application::APP_ID, 'activity_log_retention_days', (string)\OCA\ReidelCrm\BackgroundJob\ActivityLogPruningJob::DEFAULT_RETENTION_DAYS),
			'overdue_threshold_days'      => (int)$this->appConfig->getValueString(Application::APP_ID, 'overdue_threshold_days', '0'),
			// Deck reverse-provisioning
			'deck_reverse_provisioning'   => $this->appConfig->getValueString(Application::APP_ID, 'deck_reverse_provisioning', 'no'),
			'deck_reverse_board_id'       => (int)$this->appConfig->getValueString(Application::APP_ID, 'deck_reverse_board_id', '0'),
		]);
	}

	/**
	 * Rotate the Windmill bearer token.  Generates a new secure random token,
	 * encrypts and stores it, sets the expiry timestamp, and returns the
	 * plaintext exactly once.  The plaintext is NOT stored — subsequent calls
	 * to getConfig() only return windmill_token_set = true.
	 *
	 * @param int $ttlDays  How many days before the token expires (0 = never)
	 */
	public function rotateToken(int $ttlDays = 0): DataResponse {
		$plaintext = bin2hex(random_bytes(32));
		$this->appConfig->setValueString(Application::APP_ID, 'windmill_token', $this->crypto->encrypt($plaintext));

		$ttlDays   = max(0, min(3650, $ttlDays));
		$expiresAt = $ttlDays > 0 ? time() + ($ttlDays * 86400) : 0;
		$this->appConfig->setValueString(Application::APP_ID, 'windmill_token_expires_at', (string)$expiresAt);

		return new DataResponse([
			'token'      => $plaintext,    // Return once — caller must store it securely.
			'expires_at' => $expiresAt,
		]);
	}

	public function setConfig(): DataResponse {
		$windmillToken = $this->request->getParam('windmill_token');
		$ghBaseUrl = $this->request->getParam('gh_base_url');
		$ghApiKey = $this->request->getParam('gh_api_key');
		$archiveSub = $this->request->getParam('email_archive_subfolder');

		if ($windmillToken !== null && $windmillToken !== '') {
			$this->appConfig->setValueString(Application::APP_ID, 'windmill_token', $this->crypto->encrypt($windmillToken));
		}
		if ($ghBaseUrl !== null) {
			$this->appConfig->setValueString(Application::APP_ID, 'gh_base_url', rtrim($ghBaseUrl, '/'));
		}
		if ($ghApiKey !== null && $ghApiKey !== '') {
			$this->appConfig->setValueString(Application::APP_ID, 'gh_api_key', $this->crypto->encrypt($ghApiKey));
		}
		if ($archiveSub !== null) {
			$this->appConfig->setValueString(Application::APP_ID, 'email_archive_subfolder', trim($archiveSub, '/') ?: 'Emails');
		}

		// Phase B1/B2: Folder provisioning + onboarding settings
		$defaultFolder = $this->request->getParam('default_project_folder');
		if ($defaultFolder !== null) {
			$this->appConfig->setValueString(Application::APP_ID, 'default_project_folder', trim($defaultFolder, '/') ?: 'CRM Projects');
		}
		$onboardingEnabled = $this->request->getParam('onboarding_enabled');
		if ($onboardingEnabled !== null) {
			$this->appConfig->setValueString(Application::APP_ID, 'onboarding_enabled', $onboardingEnabled === 'yes' ? 'yes' : 'no');
		}
		$onboardingTemplate = $this->request->getParam('onboarding_project_template');
		if ($onboardingTemplate !== null) {
			$this->appConfig->setValueString(Application::APP_ID, 'onboarding_project_template', $onboardingTemplate ?: 'New Matter');
		}
		$onboardingType = $this->request->getParam('onboarding_project_type');
		if ($onboardingType !== null) {
			$this->appConfig->setValueString(Application::APP_ID, 'onboarding_project_type', $onboardingType);
		}
		$onboardingAutoFolder = $this->request->getParam('onboarding_auto_folder');
		if ($onboardingAutoFolder !== null) {
			$this->appConfig->setValueString(Application::APP_ID, 'onboarding_auto_folder', $onboardingAutoFolder === 'yes' ? 'yes' : 'no');
		}
		$onboardingDeckBoard = $this->request->getParam('onboarding_deck_board');
		if ($onboardingDeckBoard !== null) {
			$this->appConfig->setValueString(Application::APP_ID, 'onboarding_deck_board', $onboardingDeckBoard);
		}

		// Pruning / automation settings
		$retentionDays = $this->request->getParam('activity_log_retention_days');
		if ($retentionDays !== null) {
			$days = max(7, min(3650, (int)$retentionDays));
			$this->appConfig->setValueString(Application::APP_ID, 'activity_log_retention_days', (string)$days);
		}
		$overdueDays = $this->request->getParam('overdue_threshold_days');
		if ($overdueDays !== null) {
			$this->appConfig->setValueString(Application::APP_ID, 'overdue_threshold_days', (string)max(0, min(365, (int)$overdueDays)));
		}

		// Deck reverse-provisioning
		$deckReverse = $this->request->getParam('deck_reverse_provisioning');
		if ($deckReverse !== null) {
			$this->appConfig->setValueString(Application::APP_ID, 'deck_reverse_provisioning', $deckReverse === 'yes' ? 'yes' : 'no');
		}
		$deckBoardId = $this->request->getParam('deck_reverse_board_id');
		if ($deckBoardId !== null) {
			$this->appConfig->setValueString(Application::APP_ID, 'deck_reverse_board_id', (string)(int)$deckBoardId);
		}

		return $this->getConfig();
	}
}
