<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Controller;

use OCA\ReidelCrm\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Util;

class PageController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IInitialState $initialState,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		Util::addScript(Application::APP_ID, Application::APP_ID . '-main');

		$user = $this->userSession->getUser();
		$this->initialState->provideInitialState('current_user_id', $user?->getUID());

		return new TemplateResponse(Application::APP_ID, 'main');
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function hugo(): TemplateResponse {
		Util::addScript(Application::APP_ID, Application::APP_ID . '-main');

		$user = $this->userSession->getUser();
		$this->initialState->provideInitialState('current_user_id', $user?->getUID());
		$this->initialState->provideInitialState('start_route', '/hugo/projects');

		return new TemplateResponse(Application::APP_ID, 'main');
	}
}
