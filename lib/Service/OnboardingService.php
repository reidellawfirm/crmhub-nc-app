<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Service;

use OCA\ReidelCrm\AppInfo\Application;
use OCA\ReidelCrm\Db\Contact;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

/**
 * Phase B2 — Contact Onboarding Workflow.
 *
 * When enabled by an admin, automatically provisions a default project (and
 * optionally a Files folder and a Deck card) the moment a new CRM contact is
 * created — either manually or via CardDAV import.
 *
 * Configuration keys (all under app_id = crmhub_nc_app):
 *   onboarding_enabled          yes|no   (default no)
 *   onboarding_project_template string   (default "New Matter")
 *   onboarding_project_type     string   (default null / empty)
 *   onboarding_auto_folder      yes|no   (default no)
 *   onboarding_deck_board       string   (board ID, optional)
 */
class OnboardingService {

	public function __construct(
		private ProjectService $projectService,
		private ActivityService $activity,
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Run the onboarding workflow for a newly created contact.
	 * Silently no-ops if onboarding is disabled in admin settings.
	 *
	 * @param Contact     $contact     The newly created contact entity.
	 * @param string|null $actorUserId The NC user who triggered the creation.
	 */
	public function runForNewContact(Contact $contact, ?string $actorUserId): void {
		if ($this->appConfig->getValueString(Application::APP_ID, 'onboarding_enabled', 'no') !== 'yes') {
			return;
		}

		$this->logger->debug(
			'OnboardingService: running for contact ' . $contact->getId(),
		);

		try {
			$projectName = $this->appConfig->getValueString(
				Application::APP_ID, 'onboarding_project_template', 'New Matter',
			);
			if ($projectName === '') {
				$projectName = 'New Matter';
			}
			$projectType = $this->appConfig->getValueString(
				Application::APP_ID, 'onboarding_project_type', '',
			);
			$deckBoard = $this->appConfig->getValueString(
				Application::APP_ID, 'onboarding_deck_board', '',
			);
			$autoFolder = $this->appConfig->getValueString(
				Application::APP_ID, 'onboarding_auto_folder', 'no',
			) === 'yes';

			$data = [
				'projectName'     => $projectName,
				'projectType'     => $projectType !== '' ? $projectType : null,
				'status'          => 'active',
				'autoCreateFolder' => $autoFolder,
			];
			if ($deckBoard !== '') {
				$data['deckBoardId'] = (int)$deckBoard;
			}

			$project = $this->projectService->create($contact->getId(), $data, $actorUserId);

			// Log a dedicated onboarding activity entry so it's visible as a
			// distinct milestone in the contact's timeline.
			$this->activity->log(
				contactCacheId: $contact->getId(),
				type: ActivityService::TYPE_ONBOARDING,
				summary: sprintf(
					'Client onboarding started — project "%s" provisioned',
					$project->getProjectName(),
				),
				projectId: $project->getId(),
				metadata: [
					'auto_folder' => $autoFolder,
					'deck_board'  => $deckBoard !== '' ? (int)$deckBoard : null,
				],
				actorUserId: $actorUserId,
			);
		} catch (\Throwable $e) {
			// Non-fatal: the contact already exists; only the project provisioning
			// failed.  Log and continue so the contact creation succeeds.
			$this->logger->warning(
				'OnboardingService failed for contact ' . $contact->getId() . ': ' . $e->getMessage(),
				['exception' => $e],
			);
		}
	}
}
