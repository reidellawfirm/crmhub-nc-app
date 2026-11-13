<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Listener;

use OCA\ReidelCrm\AppInfo\Application;
use OCA\Contacts\Event\LoadContactsOcaApiEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * Inject CRM integration script into the Nextcloud Contacts app.
 *
 * Fired by the Contacts app when it loads its frontend.
 * We add our compiled JS/CSS bundle so the Vue panel can be injected
 * into the Contacts detail view without modifying any NC core files.
 */
class LoadContactsOcaApiListener implements IEventListener {

	public function handle(Event $event): void {
		if (!($event instanceof LoadContactsOcaApiEvent)) {
			return;
		}

		// webpack-vue-config prefixes the output as "{appId}-{entry}.js"
		Util::addScript(Application::APP_ID, Application::APP_ID . '-contacts-integration');
	}
}
