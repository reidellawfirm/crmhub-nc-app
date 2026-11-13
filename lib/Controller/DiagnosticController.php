<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Controller;

use OCA\ReidelCrm\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IRequest;
use OCP\IURLGenerator;

/**
 * Diagnostic endpoints that let you confirm the Contacts integration
 * is wired up correctly on the PHP/Nextcloud side.
 *
 * Hit GET /apps/crmhub_nc_app/api/debug/contacts-integration and read the JSON.
 */
class DiagnosticController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IURLGenerator $urlGenerator,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	#[NoAdminRequired]
	public function contactsIntegration(): DataResponse {
		$appId = Application::APP_ID;

		// 1. Does the Contacts app event class exist?
		$eventClass = \OCA\Contacts\Event\LoadContactsOcaApiEvent::class;
		$eventExists = class_exists($eventClass);

		// 2. Does the BeforeTemplateRenderedEvent class exist?
		$btrClass = \OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent::class;
		$btrExists = class_exists($btrClass);

		// 3. Is the compiled JS bundle present?
		$jsFile = \OC::$SERVERROOT . '/apps/' . $appId . '/js/' . $appId . '-contacts-integration.js';
		$jsFileCustom = \OC::$SERVERROOT . '/custom_apps/' . $appId . '/js/' . $appId . '-contacts-integration.js';
		$jsExists = file_exists($jsFile) || file_exists($jsFileCustom);
		$jsPath   = file_exists($jsFile) ? $jsFile : ($jsFileCustom ?: 'NOT FOUND');

		// 4. Build the URL that NC would generate for the script
		$scriptUrl = $this->urlGenerator->linkTo($appId, 'js/' . $appId . '-contacts-integration.js');

		// 5. NC version
		$ncVersion = \OC_Util::getVersion();

		return new DataResponse([
			'status'  => 'ok',
			'checks'  => [
				'contacts_event_class_exists'   => $eventExists,
				'contacts_event_class'          => $eventClass,
				'btr_event_class_exists'        => $btrExists,
				'js_bundle_on_disk'             => $jsExists,
				'js_bundle_path'                => $jsPath,
				'js_script_url'                 => $scriptUrl,
				'nextcloud_version'             => implode('.', array_slice($ncVersion, 0, 3)),
			],
			'instructions' => [
				'1_check_script_in_html' => 'View page source at /apps/contacts and search for "contacts-integration"',
				'2_check_console'        => 'Open DevTools console and look for "[Reidel CRM] ✅ Step 1"',
				'3_if_no_step1'          => 'contacts_event_class_exists must be true OR btr_event_class_exists fallback must work',
				'4_if_step1_but_no_panel'=> 'Run DOM diagnostic paste in console (see docs)',
			],
		]);
	}
}
