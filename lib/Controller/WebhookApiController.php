<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Controller;

use OCA\ReidelCrm\Db\Webhook;
use OCA\ReidelCrm\Db\WebhookMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * CRUD API for outbound webhook endpoint configuration.
 *
 * Security:
 *   - All operations are owner-scoped (can only access own webhooks).
 *   - Secret is stored server-side and never returned to the client.
 *   - URL validation enforced on create/update.
 */
class WebhookApiController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private WebhookMapper $webhookMapper,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/** List all webhooks belonging to the current user. */
	#[NoAdminRequired]
	public function index(): DataResponse {
		$userId = $this->currentUserId();
		if ($userId === null) {
			return new DataResponse([], Http::STATUS_UNAUTHORIZED);
		}
		return new DataResponse(array_map(
			fn(Webhook $wh) => $wh->jsonSerialize(),
			$this->webhookMapper->findByOwner($userId),
		));
	}

	/** Create a new webhook endpoint. */
	#[NoAdminRequired]
	public function create(
		string $label,
		string $url,
		array $events,
		?string $secret = null,
	): DataResponse {
		$userId = $this->currentUserId();
		if ($userId === null) {
			return new DataResponse([], Http::STATUS_UNAUTHORIZED);
		}
		if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'https://')) {
			return new DataResponse(
				['error' => 'url must be a valid HTTPS URL'],
				Http::STATUS_UNPROCESSABLE_ENTITY,
			);
		}
		$label  = substr(trim($label), 0, 255);
		$events = array_values(array_filter(array_map('strval', $events)));

		$wh = new Webhook();
		$wh->setOwnerId($userId);
		$wh->setLabel($label ?: 'Webhook');
		$wh->setUrl($url);
		$wh->setEvents($events);
		$wh->setSecret($secret !== '' ? $secret : null);
		$wh->setIsActive(1);
		$wh->setFailCount(0);
		$wh->setCreatedAt(time());

		$saved = $this->webhookMapper->insert($wh);
		return new DataResponse($saved->jsonSerialize(), Http::STATUS_CREATED);
	}

	/** Update label, url, events, secret, or isActive for own webhook. */
	#[NoAdminRequired]
	public function update(
		int $id,
		?string $label = null,
		?string $url = null,
		?array $events = null,
		?string $secret = null,
		?bool $isActive = null,
	): DataResponse {
		$userId = $this->currentUserId();
		if ($userId === null) {
			return new DataResponse([], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$wh = $this->webhookMapper->find($id);
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		// Ownership check
		if ($wh->getOwnerId() !== $userId) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		if ($url !== null) {
			if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'https://')) {
				return new DataResponse(
					['error' => 'url must be a valid HTTPS URL'],
					Http::STATUS_UNPROCESSABLE_ENTITY,
				);
			}
			$wh->setUrl($url);
		}
		if ($label !== null) {
			$wh->setLabel(substr(trim($label), 0, 255));
		}
		if ($events !== null) {
			$wh->setEvents(array_values(array_filter(array_map('strval', $events))));
		}
		if ($secret !== null) {
			$wh->setSecret($secret !== '' ? $secret : null);
		}
		if ($isActive !== null) {
			$wh->setIsActive($isActive ? 1 : 0);
			if ($isActive) {
				$wh->setFailCount(0); // reset fail count when manually re-enabling
			}
		}

		$updated = $this->webhookMapper->update($wh);
		return new DataResponse($updated->jsonSerialize());
	}

	/** Delete an owned webhook. */
	#[NoAdminRequired]
	public function destroy(int $id): DataResponse {
		$userId = $this->currentUserId();
		if ($userId === null) {
			return new DataResponse([], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$wh = $this->webhookMapper->find($id);
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($wh->getOwnerId() !== $userId) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		$this->webhookMapper->delete($wh);
		return new DataResponse([]);
	}

	private function currentUserId(): ?string {
		return $this->userSession->getUser()?->getUID();
	}
}
