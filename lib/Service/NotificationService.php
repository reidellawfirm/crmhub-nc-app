<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Service;

use OCA\ReidelCrm\AppInfo\Application;
use OCA\ReidelCrm\Db\Project;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;

/**
 * Delivers Nextcloud native push notifications for CRM events.
 *
 * These appear in the NC header bell icon and, when enabled, as email
 * digests via NC's own notification email system.  The corresponding
 * INotifier (CrmNotifier) converts the machine-readable subject keys into
 * localised human-readable strings.
 *
 * All methods are best-effort: failures are logged at WARNING level and
 * silently swallowed so they never break the CRM workflow.
 */
class NotificationService {

	public function __construct(
		private INotificationManager $notificationManager,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Notify the project owner/assignee that a project is overdue.
	 * Typically called by OverdueProjectJob once per day per project.
	 */
	public function notifyOverdue(Project $project, string $userId): void {
		try {
			$notification = $this->notificationManager->createNotification();
			$notification
				->setApp(Application::APP_ID)
				->setUser($userId)
				->setDateTime(new \DateTime())
				->setObject('project', (string)$project->getId())
				->setSubject('project_overdue', [
					'name'    => $project->getProjectName(),
					'dueDate' => date('Y-m-d', (int)$project->getDueDate()),
				]);
			$this->notificationManager->notify($notification);
		} catch (\Throwable $e) {
			$this->logger->warning(
				'NotificationService::notifyOverdue failed: ' . $e->getMessage(),
				['exception' => $e, 'project_id' => $project->getId()],
			);
		}
	}

	/**
	 * Notify $assignedTo that they have been assigned to $project.
	 * Self-assignment ($assignedTo === $assignedBy) is silently skipped.
	 */
	public function notifyAssigned(Project $project, string $assignedTo, string $assignedBy): void {
		if ($assignedTo === $assignedBy) {
			return;
		}
		try {
			$notification = $this->notificationManager->createNotification();
			$notification
				->setApp(Application::APP_ID)
				->setUser($assignedTo)
				->setDateTime(new \DateTime())
				->setObject('project', (string)$project->getId())
				->setSubject('project_assigned', [
					'name'       => $project->getProjectName(),
					'assignedBy' => $assignedBy,
				]);
			$this->notificationManager->notify($notification);
		} catch (\Throwable $e) {
			$this->logger->warning(
				'NotificationService::notifyAssigned failed: ' . $e->getMessage(),
				['exception' => $e, 'project_id' => $project->getId()],
			);
		}
	}

	/**
	 * Dismiss all pending CRM notifications for a project (e.g. on completion).
	 * Safe to call even if no notifications are outstanding.
	 */
	public function dismissProjectNotifications(int $projectId, string $userId): void {
		try {
			$notification = $this->notificationManager->createNotification();
			$notification
				->setApp(Application::APP_ID)
				->setUser($userId)
				->setObject('project', (string)$projectId);
			$this->notificationManager->markProcessed($notification);
		} catch (\Throwable $e) {
			$this->logger->debug(
				'NotificationService::dismiss failed: ' . $e->getMessage(),
			);
		}
	}
}
