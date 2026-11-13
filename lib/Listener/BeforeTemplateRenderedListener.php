<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Listener;

use OCA\ReidelCrm\AppInfo\Application;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IRequest;
use OCP\Util;

/**
 * Injects the CRM Contacts-integration script whenever the Nextcloud Contacts
 * app renders its full-page template.
 *
 * Registered via IRegistrationContext::registerEventListener() in register()
 * (not boot()) so it fires even under NC 30's lazy-boot system where an app's
 * boot() is only called when its own routes are used.
 */
class BeforeTemplateRenderedListener implements IEventListener {

	public function __construct(private IRequest $request) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof BeforeTemplateRenderedEvent)) {
			return;
		}

		$uri = $this->request->getRequestUri();

		if (str_contains($uri, '/apps/contacts')) {
			// webpack-vue-config prefixes output as "{appId}-{entry}.js"
			// so the script name must include the appId prefix.
			Util::addScript(Application::APP_ID, Application::APP_ID . '-contacts-integration');
		}
	}
}
