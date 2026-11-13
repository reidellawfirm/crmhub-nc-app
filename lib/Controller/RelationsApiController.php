<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Controller;

use OCA\ReidelCrm\Service\RelationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class RelationsApiController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private RelationService $relations,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function lookup(?int $contact = null, ?int $folder = null, ?int $deck_card = null): DataResponse {
		$uid = $this->userSession->getUser()?->getUID();
		if ($uid === null) {
			return new DataResponse(['error' => 'unauthenticated'], Http::STATUS_UNAUTHORIZED);
		}
		$result = $this->relations->lookup($contact, $folder, $deck_card);

		// Owner isolation: if the resolved contact belongs to another user, return empty result.
		$resolvedContact = $result['contact'];
		if ($resolvedContact !== null) {
			$ownerId = $resolvedContact->getOwnerId();
			if ($ownerId !== null && $ownerId !== $uid) {
				return new DataResponse(['contact' => null, 'projects' => []]);
			}
		}

		return new DataResponse([
			'contact' => $result['contact']?->jsonSerialize(),
			'projects' => array_map(fn ($p) => $p->jsonSerialize(), $result['projects']),
		]);
	}
}
