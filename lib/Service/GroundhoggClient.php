<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Service;

use OCA\ReidelCrm\AppInfo\Application;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

/**
 * Thin REST wrapper around the Groundhogg WordPress REST API
 * (https://docs.groundhogg.io/docs/developers/rest-api/).
 */
class GroundhoggClient {

	public function __construct(
		private IAppConfig $appConfig,
		private ICrypto $crypto,
		private IClientService $clientService,
		private LoggerInterface $logger,
	) {
	}

	public function isConfigured(): bool {
		return $this->getBaseUrl() !== '' && $this->getApiKey() !== '';
	}

	/**
	 * @return iterable<array<string,mixed>>
	 */
	public function listContacts(?\DateTimeInterface $since = null, int $perPage = 100): iterable {
		if (!$this->isConfigured()) {
			return [];
		}
		$page = 1;
		$client = $this->clientService->newClient();
		while (true) {
			$query = ['per_page' => $perPage, 'page' => $page];
			if ($since !== null) {
				$query['after'] = $since->format(\DateTimeInterface::ATOM);
			}
			$url = $this->getBaseUrl() . '/wp-json/gh/v4/contacts?' . http_build_query($query);
			try {
				$resp = $client->get($url, [
					'headers' => $this->buildHeaders(),
					'timeout' => 30,
				]);
			} catch (\Throwable $e) {
				$this->logger->error('Groundhogg listContacts failed: ' . $e->getMessage(), ['exception' => $e]);
				return;
			}
			$body = json_decode((string)$resp->getBody(), true);
			$items = $body['contacts'] ?? $body['items'] ?? $body['data'] ?? (is_array($body) ? $body : []);
			if (empty($items)) {
				return;
			}
			foreach ($items as $item) {
				yield $item;
			}
			if (count($items) < $perPage) {
				return;
			}
			$page++;
		}
	}

	private function getBaseUrl(): string {
		return $this->appConfig->getValueString(Application::APP_ID, 'gh_base_url', '');
	}

	private function getApiKey(): string {
		$enc = $this->appConfig->getValueString(Application::APP_ID, 'gh_api_key', '');
		if ($enc === '') {
			return '';
		}
		try {
			return $this->crypto->decrypt($enc);
		} catch (\Throwable) {
			return '';
		}
	}

	private function buildHeaders(): array {
		return [
			'Authorization' => 'Basic ' . base64_encode($this->getApiKey()),
			'Accept' => 'application/json',
			'Content-Type' => 'application/json',
		];
	}

	/**
	 * Phase C2 — Groundhogg bidirectional sync.
	 *
	 * Push a partial contact update to Groundhogg via PATCH.
	 * Supported fields: tags_add, tags_remove, meta (key→value map).
	 *
	 * @param array<string,mixed> $data  Keys accepted by GH v4 PATCH /contacts/{id}
	 * @return bool  true on HTTP 2xx, false on error
	 */
	public function updateContact(int $ghId, array $data): bool {
		if (!$this->isConfigured()) {
			return false;
		}
		$url    = $this->getBaseUrl() . '/wp-json/gh/v4/contacts/' . $ghId;
		$client = $this->clientService->newClient();
		try {
			$resp = $client->patch($url, [
				'headers' => $this->buildHeaders(),
				'body'    => json_encode($data),
				'timeout' => 15,
			]);
			$statusCode = $resp->getStatusCode();
			if ($statusCode < 200 || $statusCode >= 300) {
				$this->logger->warning(
					'Groundhogg updateContact returned HTTP ' . $statusCode,
					['gh_id' => $ghId, 'data_keys' => array_keys($data)],
				);
				return false;
			}
			return true;
		} catch (\Throwable $e) {
			$this->logger->error(
				'Groundhogg updateContact failed: ' . $e->getMessage(),
				['exception' => $e, 'gh_id' => $ghId],
			);
			return false;
		}
	}
}
