<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Notification;

use OCA\ReidelCrm\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

/**
 * Converts machine-readable CRM notification subjects into localised
 * human-readable strings for the NC header bell and notification centre.
 *
 * Registered via Application::register() →
 *   $context->registerNotifierService(CrmNotifier::class)
 *
 * Supported subjects:
 *   project_overdue  — daily overdue reminder from OverdueProjectJob
 *   project_assigned — sent when a project is assigned to another user
 */
class CrmNotifier implements INotifier {

	public function __construct(
		private IFactory $l10nFactory,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return 'CRM Hub';
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new \InvalidArgumentException('Unsupported app: ' . $notification->getApp());
		}

		$l      = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$params = $notification->getSubjectParameters();
		$name   = $params['name'] ?? '';
		$id     = $notification->getObjectId();

		$crmBase = $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.page.index');

		switch ($notification->getSubject()) {
			case 'project_overdue':
				$notification->setParsedSubject(
					$l->t('Project "%s" is overdue (due %s)', [
						$name,
						$params['dueDate'] ?? '',
					]),
				);
				$notification->setLink($crmBase . '#/projects/' . $id);
				break;

			case 'project_assigned':
				$notification->setParsedSubject(
					$l->t('You were assigned to project "%s" by %s', [
						$name,
						$params['assignedBy'] ?? '',
					]),
				);
				$notification->setLink($crmBase . '#/projects/' . $id);
				break;

			default:
				throw new \InvalidArgumentException(
					'Unknown CRM notification subject: ' . $notification->getSubject(),
				);
		}

		$notification->setIcon(
			$this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->imagePath(Application::APP_ID, 'app.svg'),
			),
		);

		return $notification;
	}
}
