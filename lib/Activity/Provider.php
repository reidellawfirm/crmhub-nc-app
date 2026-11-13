<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Activity;

use OCA\ReidelCrm\AppInfo\Application;
use OCA\ReidelCrm\Service\ActivityService;
use OCP\Activity\IEvent;
use OCP\Activity\IProvider as IActivityProvider;
use OCP\IL10N;
use OCP\IURLGenerator;

class Provider implements IActivityProvider {

	/** Human-readable labels for each CRM event type */
	private const TYPE_LABELS = [
		ActivityService::TYPE_CONTACT_CREATED  => 'Contact created',
		ActivityService::TYPE_CONTACT_UPDATED  => 'Contact updated',
		ActivityService::TYPE_VCARD_LINKED     => 'Contact linked from vCard',
		ActivityService::TYPE_PROJECT_CREATED  => 'Project created',
		ActivityService::TYPE_PROJECT_UPDATED  => 'Project updated',
		ActivityService::TYPE_PROJECT_CLOSED   => 'Project closed',
		ActivityService::TYPE_NOTE_ADDED       => 'Note added',
		ActivityService::TYPE_FILE_UPLOADED    => 'File uploaded',
		ActivityService::TYPE_FOLDER_CREATED   => 'Project folder created',
		ActivityService::TYPE_FOLDER_RENAMED   => 'Project folder renamed',
		ActivityService::TYPE_FOLDER_DELETED   => 'Project folder deleted',
		ActivityService::TYPE_DECK_UPDATE      => 'Deck card updated',
		ActivityService::TYPE_DECK_ARCHIVED    => 'Deck card archived',
		ActivityService::TYPE_TAG_CHANGED      => 'Tag changed',
		ActivityService::TYPE_EMAIL_SENT       => 'Email sent',
		ActivityService::TYPE_EMAIL_RECEIVED   => 'Email received',
		ActivityService::TYPE_EMAIL_MATCHED    => 'Email matched to contact',
		ActivityService::TYPE_FUNNEL_EVENT     => 'Funnel event',
		ActivityService::TYPE_SYNC             => 'Sync',
		ActivityService::TYPE_RECORD_MERGED    => 'Records merged',
		ActivityService::TYPE_ONBOARDING       => 'Client onboarding started',
		ActivityService::TYPE_CALENDAR_EVENT   => 'Calendar event created',
		ActivityService::TYPE_OVERDUE          => 'Project overdue',
		ActivityService::TYPE_CONTACT_DELETED  => 'Contact deleted',
	];

	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function parse($language, IEvent $event, ?IEvent $previousEvent = null): IEvent {
		if ($event->getApp() !== Application::APP_ID) {
			throw new \InvalidArgumentException();
		}

		$params  = $event->getSubjectParameters();
		$subject = $event->getSubject();            // the TYPE_* constant string
		$summary = (string)($params['summary'] ?? '');

		// Map the machine-readable type to a human label for the activity page
		$typeLabel = isset(self::TYPE_LABELS[$subject])
			? $this->l10n->t(self::TYPE_LABELS[$subject])
			: $this->l10n->t('CRM activity');

		// Primary line: the free-text summary (or the label as fallback)
		$parsedSubject = $summary !== '' ? $summary : $typeLabel;

		$event->setParsedSubject($parsedSubject);

		// Show the type label as a subtitle so the tile is self-explanatory
		// even when the summary is identical across rows.
		if ($summary !== '' && $typeLabel !== $parsedSubject) {
			$event->setParsedMessage($typeLabel);
		}

		$event->setIcon(
			$this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->imagePath(Application::APP_ID, 'app.svg'),
			),
		);

		// Deep-link the bell / activity-page entry to the relevant entity.
		// Project-scoped events link to the project view; contact-only events
		// link to the contact view.
		$params    = $event->getSubjectParameters();
		$projectId = isset($params['project_id']) ? (int)$params['project_id'] : null;
		$objectId  = $event->getObjectId();

		if ($projectId !== null && $projectId > 0) {
			$event->setLink(
				$this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.page.index')
				. '#/projects/' . $projectId,
			);
		} elseif ($objectId !== '' && $objectId !== '0') {
			$event->setLink(
				$this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.page.index')
				. '#/contacts/' . $objectId,
			);
		}

		return $event;
	}
}
