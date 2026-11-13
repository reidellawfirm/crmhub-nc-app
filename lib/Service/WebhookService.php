<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Service;

use OCA\ReidelCrm\Db\WebhookMapper;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

/**
 * Delivers outbound webhook payloads to configured HTTP endpoints.
 *
 * Delivery model:
 *   - Synchronous with a 5-second timeout (prevents long-tail slowdowns).
 *   - Owner-isolated: only fires webhooks belonging to the entity owner.
 *   - After MAX_FAIL_COUNT consecutive failures the webhook is deactivated.
 *   - HMAC-SHA256 signature in X-CRM-Signature header when a secret is set.
 *   - TLS verification always enforced (OWASP A02).
 *
 * Payload shape:
 *   { "event": "<type>", "timestamp": <unix>, "data": { ... } }
 */
class WebhookService {

	private const MAX_FAIL_COUNT = 5;
	private const TIMEOUT_SECONDS = 5;

	public function __construct(
		private WebhookMapper $webhookMapper,
		private IClientService $clientService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Dispatch $event to all active webhooks owned by $ownerId that subscribe
	 * to this event type (or to the wildcard '*').
	 *
	 * @param string $ownerId  NC user who owns the CRM entity triggering the event
	 * @param string $event    CRM activity type string (e.g. 'contact_created')
	 * @param array  $payload  Structured data to include as the 'data' key
	 */
	public function dispatch(string $ownerId, string $event, array $payload): void {
		try {
			$webhooks = $this->webhookMapper->findActiveForEvent($event);
		} catch (\Throwable $e) {
			// Table doesn't exist yet (before V0007 migration) — silent no-op.
			$this->logger->debug('WebhookService: could not load webhooks — ' . $e->getMessage());
			return;
		}

		if (empty($webhooks)) {
			return;
		}

		$body = json_encode([
			'event'     => $event,
			'timestamp' => time(),
			'data'      => $payload,
		]);
		if ($body === false) {
			return;
		}

		$client = $this->clientService->newClient();

		foreach ($webhooks as $wh) {
			// Owner isolation: never fire a webhook for another user's entity.
			if ($wh->getOwnerId() !== $ownerId) {
				continue;
			}

			$headers = [
				'Content-Type' => 'application/json',
				'User-Agent'   => 'ReidelCRM-Webhook/1.0',
			];

			// HMAC-SHA256 signature so the receiver can verify authenticity.
			if ($wh->getSecret() !== null && $wh->getSecret() !== '') {
				$headers['X-CRM-Signature'] = 'sha256=' . hash_hmac('sha256', $body, $wh->getSecret());
			}

			try {
				$response = $client->post($wh->getUrl(), [
					'headers' => $headers,
					'body'    => $body,
					'timeout' => self::TIMEOUT_SECONDS,
					'verify'  => true, // always enforce TLS — OWASP A02
				]);
				$status = $response->getStatusCode();
				$wh->setLastCalledAt(time());
				$wh->setLastHttpStatus($status);
				$wh->setFailCount(($status >= 200 && $status < 300) ? 0 : $wh->getFailCount() + 1);
			} catch (\Throwable $e) {
				$this->logger->warning(
					'WebhookService: delivery failed for ' . $wh->getUrl() . ' — ' . $e->getMessage(),
					['event' => $event, 'webhook_id' => $wh->getId()],
				);
				$wh->setLastCalledAt(time());
				$wh->setLastHttpStatus(0);
				$wh->setFailCount($wh->getFailCount() + 1);
			}

			if ($wh->getFailCount() >= self::MAX_FAIL_COUNT) {
				$wh->setIsActive(0);
				$this->logger->warning(
					'WebhookService: deactivated webhook ' . $wh->getId()
					. ' after ' . self::MAX_FAIL_COUNT . ' consecutive failures',
					['url' => $wh->getUrl()],
				);
			}

			try {
				$this->webhookMapper->update($wh);
			} catch (\Throwable) {
				// Non-critical — status update failure must not break the workflow.
			}
		}
	}
}
