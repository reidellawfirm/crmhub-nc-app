<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\BackgroundJob;

use OCA\ReidelCrm\Db\ActivityMapper;
use OCA\ReidelCrm\Db\ContactMapper;
use OCA\ReidelCrm\Service\ActivityService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IServerContainer;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * Phase C3 — NC Mail background polling.
 *
 * Runs every 15 minutes.  For each active NC user that has NC Mail accounts
 * configured, polls recent messages (last 20 minutes), matches senders and
 * recipients against CRM contacts by email address, and logs matching emails
 * as CRM activity entries.
 *
 * Soft-depends on OCA\Mail:
 *   – If the Mail app is not installed the job does nothing.
 *   – Uses \OCA\Mail\Service\IMailManager if available (NC Mail ≥ 1.10).
 *   – Falls back gracefully on any exception per-user so one broken mail
 *     account does not stop processing for other users.
 *
 * Loop safety: the job only reads from Mail and writes to reidel_activity_log.
 * It does NOT update NC Mail or trigger any mail-sending, so there is no
 * feedback loop.
 */
class MailPollingJob extends TimedJob {

	/** Polling interval in seconds (15 minutes). */
	private const INTERVAL = 900;

	/** How far back to look for new messages (slightly longer than interval). */
	private const LOOKBACK_SECONDS = 1080; // 18 minutes

	public function __construct(
		ITimeFactory $time,
		private IUserManager $userManager,
		private ContactMapper $contactMapper,
		private ActivityMapper $activityMapper,
		private ActivityService $activity,
		private IServerContainer $serverContainer,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(self::INTERVAL);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	protected function run(mixed $argument): void {
		// Soft-depend on NC Mail — skip entirely if not installed.
		if (!interface_exists(\OCA\Mail\Service\IMailManager::class)
			&& !class_exists(\OCA\Mail\Service\MailManager::class)) {
			return;
		}

		try {
			/** @var \OCA\Mail\Service\IMailManager $mailManager */
			$mailManager = $this->serverContainer->get(\OCA\Mail\Service\IMailManager::class);
		} catch (\Throwable) {
			try {
				/** @var \OCA\Mail\Service\IMailManager $mailManager */
				$mailManager = $this->serverContainer->get(\OCA\Mail\Service\MailManager::class);
			} catch (\Throwable $e) {
				$this->logger->debug('MailPollingJob: NC Mail not available — ' . $e->getMessage());
				return;
			}
		}

		$since = new \DateTime('@' . (time() - self::LOOKBACK_SECONDS));

		// Iterate all active users and poll their mail accounts.
		$this->userManager->callForAllUsers(function (\OCP\IUser $user) use ($mailManager, $since): void {
			try {
				$this->pollUser($mailManager, $user->getUID(), $since);
			} catch (\Throwable $e) {
				$this->logger->debug(
					'MailPollingJob: error processing user ' . $user->getUID() . ': ' . $e->getMessage(),
					['exception' => $e],
				);
			}
		});
	}

	/**
	 * Poll recent messages for a single user and log any that match CRM contacts.
	 *
	 * @param \OCA\Mail\Service\IMailManager $mailManager
	 */
	private function pollUser(mixed $mailManager, string $userId, \DateTime $since): void {
		// Get the user entity for NC Mail API calls.
		$ncUser = $this->userManager->get($userId);
		if ($ncUser === null) {
			return;
		}

		// getAccounts() is available in NC Mail ≥ 1.10.
		if (!method_exists($mailManager, 'getAccounts')) {
			return;
		}

		/** @var \OCA\Mail\Model\IMAPMessage[] $messages */
		$accounts = $mailManager->getAccounts($ncUser);
		if (empty($accounts)) {
			return;
		}

		foreach ($accounts as $account) {
			try {
				$this->pollAccount($mailManager, $account, $userId, $since);
			} catch (\Throwable $e) {
				$this->logger->debug(
					'MailPollingJob: account error for user ' . $userId . ': ' . $e->getMessage(),
				);
			}
		}
	}

	/** @param \OCA\Mail\Account $account */
	private function pollAccount(mixed $mailManager, mixed $account, string $userId, \DateTime $since): void {
		if (!method_exists($mailManager, 'getMailboxes')) {
			return;
		}
		$mailboxes = $mailManager->getMailboxes($account);

		foreach ($mailboxes as $mailbox) {
			// Only poll INBOX and Sent to avoid scanning entire mail stores.
			$name = strtolower((string)($mailbox->getName() ?? ''));
			$isSent = str_contains($name, 'sent') || str_contains($name, 'gesendet') || str_contains($name, 'outbox');
			if ($name !== 'inbox' && !$isSent) {
				continue;
			}

			try {
				if (!method_exists($mailManager, 'getMessages')) {
					continue;
				}
				// getMessages(account, mailbox, cursor, limit) — signature may vary by version.
				$messages = $mailManager->getMessages($account, $mailbox, null, 20);
				foreach ($messages as $message) {
					$this->matchMessageToContact($message, $userId, $isSent);
				}
			} catch (\Throwable $e) {
				$this->logger->debug('MailPollingJob: mailbox ' . $name . ' error: ' . $e->getMessage());
			}
		}
	}

	/** @param mixed $message — \OCA\Mail\Model\IMAPMessage or similar */
	private function matchMessageToContact(mixed $message, string $userId, bool $isSent): void {
		// Extract sender / recipient email.
		$fromRaw = is_callable([$message, 'getFrom']) ? $message->getFrom() : null;
		$toRaw   = is_callable([$message, 'getTo'])   ? $message->getTo()   : null;

		// Resolve the other-party email depending on direction.
		$counterpartEmail = $isSent
			? $this->extractFirstEmail($toRaw)
			: $this->extractFirstEmail($fromRaw);

		if ($counterpartEmail === null || $counterpartEmail === '') {
			return;
		}

		// Filter messages older than the lookback window.
		$sentAt = null;
		if (is_callable([$message, 'getSentAt'])) {
			$sentAt = $message->getSentAt();
		} elseif (is_callable([$message, 'getDate'])) {
			$sentAt = $message->getDate();
		}
		if ($sentAt instanceof \DateTime || $sentAt instanceof \DateTimeInterface) {
			if ($sentAt->getTimestamp() < time() - self::LOOKBACK_SECONDS) {
				return;
			}
		}

		// Look up the CRM contact by email.
		$contacts = $this->contactMapper->findByEmail($counterpartEmail);
		if (empty($contacts)) {
			return;
		}

		$subject = is_callable([$message, 'getSubject']) ? (string)$message->getSubject() : '(no subject)';
		$type    = $isSent ? ActivityService::TYPE_EMAIL_SENT : ActivityService::TYPE_EMAIL_RECEIVED;

		// Extract attachment count for richer metadata.
		$attachmentCount = 0;
		if (is_callable([$message, 'getAttachments'])) {
			try { $attachmentCount = count($message->getAttachments()); } catch (\Throwable) {}
		} elseif (is_callable([$message, 'hasAttachments'])) {
			$attachmentCount = $message->hasAttachments() ? 1 : 0;
		}

		foreach ($contacts as $contact) {
			// Owner isolation: only log for the user who owns the contact.
			if ($contact->getOwnerId() !== null && $contact->getOwnerId() !== $userId) {
				continue;
			}

			// Build the canonical summary string used for deduplication AND storage.
			$summaryLine = sprintf(
				'%s: %s — %s',
				$isSent ? 'Email sent to' : 'Email received from',
				$counterpartEmail,
				mb_substr($subject, 0, 100),
			);

			// Deduplicate: skip if an identical entry was logged within the
			// lookback window.  Pass projectId = null (IS NULL) so mail activities
			// without a project link are correctly matched and never duplicated
			// across consecutive poll cycles.
			if ($this->activityMapper->hasRecentEntry(
				null,
				$type,
				$summaryLine,
				self::LOOKBACK_SECONDS,
			)) {
				continue;
			}

			$this->activity->log(
				contactCacheId: $contact->getId(),
				type: $type,
				summary: $summaryLine,
				metadata: [
					'subject'           => mb_substr($subject, 0, 255),
					'counterpart_email' => $counterpartEmail,
					'direction'         => $isSent ? 'out' : 'in',
					'attachments'       => $attachmentCount,
				],
				source: 'nextcloud',
				actorUserId: $userId,
			);
		}
	}

	/** Extract the first email address from a From/To address object. */
	private function extractFirstEmail(mixed $addresses): ?string {
		if ($addresses === null) {
			return null;
		}
		// NC Mail returns AddressList, plain string, or array depending on version.
		if (is_string($addresses)) {
			if (preg_match('/[\w.+-]+@[\w.-]+\.\w+/', $addresses, $m)) {
				return strtolower($m[0]);
			}
			return null;
		}
		if (is_array($addresses)) {
			$first = $addresses[0] ?? null;
			return $first !== null ? $this->extractFirstEmail($first) : null;
		}
		// AddressList / Address object
		if (method_exists($addresses, 'getEmail')) {
			return strtolower((string)$addresses->getEmail());
		}
		if (method_exists($addresses, 'first')) {
			return $this->extractFirstEmail($addresses->first());
		}
		if (method_exists($addresses, 'toArray')) {
			return $this->extractFirstEmail($addresses->toArray());
		}
		if (method_exists($addresses, '__toString')) {
			return $this->extractFirstEmail((string)$addresses);
		}
		return null;
	}
}
