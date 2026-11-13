<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Listener;

use OCA\ReidelCrm\Db\ActivityMapper;
use OCA\ReidelCrm\Service\ActivityService;
use OCA\ReidelCrm\Service\ContactCacheService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Instantly log a CRM email-sent activity when a user sends a message via the
 * NC Mail app, eliminating the 15-minute polling lag of MailPollingJob.
 *
 * Soft-dependency: this listener is only registered in Application::boot() when
 * class_exists(\OCA\Mail\Events\MessageSentEvent::class) is true, so the class
 * can reference the event directly in the type hint without an autoload failure
 * on systems without the Mail app.
 *
 * Deduplication: uses ActivityMapper::hasRecentEntry() with a 60-second window.
 * MailPollingJob will also pick this up but will skip it as a duplicate.
 *
 * @template-implements IEventListener<\OCA\Mail\Events\MessageSentEvent>
 */
class MailMessageSentListener implements IEventListener {

	/** Dedup window in seconds — wide enough to catch the next MailPollingJob cycle. */
	private const LOOKBACK_SECONDS = 1200; // 20 min

	public function __construct(
		private ContactCacheService $contacts,
		private ActivityService $activity,
		private ActivityMapper $activityMapper,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		// Soft-dep guard: at runtime the class must exist and the event must match.
		if (!class_exists(\OCA\Mail\Events\MessageSentEvent::class)) {
			return;
		}
		if (!$event instanceof \OCA\Mail\Events\MessageSentEvent) {
			return;
		}

		try {
			$account = $event->getAccount();
			$message = $event->getMessage();
			// getUserId() is available on IMailAccount.
			$userId = $account->getUserId();

			// Collect all recipient addresses and match against CRM contacts.
			$recipients = [];
			foreach (['getTo', 'getCc', 'getBcc'] as $getter) {
				if (!is_callable([$message, $getter])) {
					continue;
				}
				foreach ($message->$getter() ?? [] as $addr) {
					$email = is_string($addr) ? $addr : (is_callable([$addr, 'getEmail']) ? $addr->getEmail() : '');
					if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
						$recipients[] = $email;
					}
				}
			}

			$subject = is_callable([$message, 'getSubject']) ? (string)$message->getSubject() : '(no subject)';

			foreach (array_unique($recipients) as $recipientEmail) {
				$contact = $this->contacts->findByEmail($recipientEmail);
				if ($contact === null) {
					continue;
				}

				// Owner isolation.
				if ($contact->getOwnerId() !== null && $contact->getOwnerId() !== $userId) {
					continue;
				}

				$summaryLine = sprintf(
					'Email sent to %s — %s',
					$recipientEmail,
					mb_substr($subject, 0, 100),
				);

				// Deduplicate against both this listener and MailPollingJob output.
				if ($this->activityMapper->hasRecentEntry(
					null,
					ActivityService::TYPE_EMAIL_SENT,
					$summaryLine,
					self::LOOKBACK_SECONDS,
				)) {
					continue;
				}

				$this->activity->log(
					contactCacheId: $contact->getId(),
					type: ActivityService::TYPE_EMAIL_SENT,
					summary: $summaryLine,
					metadata: [
						'subject'           => mb_substr($subject, 0, 255),
						'counterpart_email' => $recipientEmail,
						'direction'         => 'out',
					],
					source: 'nextcloud',
					actorUserId: $userId,
				);
			}
		} catch (\Throwable $e) {
			$this->logger->warning(
				'MailMessageSentListener: failed to log activity: ' . $e->getMessage(),
				['exception' => $e],
			);
		}
	}
}
