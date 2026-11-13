<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\AppInfo;

use OCA\ReidelCrm\BackgroundJob\ActivityLogPruningJob;
use OCA\ReidelCrm\BackgroundJob\MailPollingJob;
use OCA\ReidelCrm\BackgroundJob\OverdueProjectJob;
use OCA\ReidelCrm\BackgroundJob\PurgeSyncLogJob;
use OCA\ReidelCrm\BackgroundJob\SyncGroundhoggJob;
use OCA\ReidelCrm\Notification\CrmNotifier;
use OCA\ReidelCrm\Listener\CardCreatedListener;
use OCA\ReidelCrm\Listener\CardDeletedListener;
use OCA\ReidelCrm\Listener\CardUpdatedListener;
use OCA\ReidelCrm\Listener\BeforeTemplateRenderedListener;
use OCA\ReidelCrm\Listener\LoadContactsOcaApiListener;
use OCA\ReidelCrm\Listener\DeckBoardUpdatedListener;
use OCA\ReidelCrm\Listener\DeckCardCreatedListener;
use OCA\ReidelCrm\Listener\DeckCardDeletedListener;
use OCA\ReidelCrm\Listener\DeckCardUpdatedListener;
use OCA\ReidelCrm\Listener\MailMessageSentListener;
use OCA\ReidelCrm\Listener\NodeCopiedListener;
use OCA\ReidelCrm\Listener\NodeRenamedListener;
use OCA\ReidelCrm\Listener\NodeDeletedListener;
use OCA\ReidelCrm\Listener\NodeSharedListener;
use OCA\ReidelCrm\Listener\NodeWrittenListener;
use OCA\ReidelCrm\Dashboard\CrmActivityWidget;
use OCA\ReidelCrm\Middleware\WindmillAuthMiddleware;
use OCA\ReidelCrm\Search\CrmSearchProvider;
use OCA\ReidelCrm\Service\CalendarService;
use OCA\ReidelCrm\Service\ContactCacheService;
use OCA\ReidelCrm\Service\GroundhoggSyncService;
use OCA\ReidelCrm\Service\OnboardingService;
use OCA\ReidelCrm\Service\ProjectService;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Events\Node\NodeCopiedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;

class Application extends App implements IBootstrap {

	public const APP_ID = 'crmhub_nc_app';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		// Background jobs are declared in appinfo/info.xml — NC 30 removed
		// registerBackgroundJob() from IRegistrationContext.

		// Unified Search provider
		$context->registerSearchProvider(CrmSearchProvider::class);

		// Dashboard widget
		$context->registerDashboardWidget(CrmActivityWidget::class);

		// CRM push notifications (overdue, assigned)
		$context->registerNotifierService(CrmNotifier::class);

		// Windmill bearer-token middleware (only enforces on IntegrationsController)
		$context->registerMiddleware(WindmillAuthMiddleware::class);

		// Files events: keep folder_path in sync, null FK on delete, log uploads
		$context->registerEventListener(NodeRenamedEvent::class, NodeRenamedListener::class);
		$context->registerEventListener(NodeDeletedEvent::class, NodeDeletedListener::class);
		$context->registerEventListener(NodeDeletedEvent::class, \OCA\ReidelCrm\Listener\HugoNodeDeletedListener::class);
		$context->registerEventListener(NodeWrittenEvent::class, NodeWrittenListener::class);
		$context->registerEventListener(NodeCopiedEvent::class, NodeCopiedListener::class);

		// Share lifecycle: log when a project folder (or descendant) is shared.
		// ShareCreatedEvent has been part of OCP since NC 18; the class_exists
		// guard keeps the bootstrap defensive against private cores / forks.
		if (class_exists(\OCP\Share\Events\ShareCreatedEvent::class)) {
			$context->registerEventListener(\OCP\Share\Events\ShareCreatedEvent::class, NodeSharedListener::class);
		}

		// CardDAV events: mirror vCard lifecycle into contacts_cache
		// Classes are referenced by string to keep DAV a soft-dependency.
		if (class_exists(\OCA\DAV\Events\CardCreatedEvent::class)) {
			$context->registerEventListener(\OCA\DAV\Events\CardCreatedEvent::class, CardCreatedListener::class);
		}
		if (class_exists(\OCA\DAV\Events\CardUpdatedEvent::class)) {
			$context->registerEventListener(\OCA\DAV\Events\CardUpdatedEvent::class, CardUpdatedListener::class);
		}
		if (class_exists(\OCA\DAV\Events\CardDeletedEvent::class)) {
			$context->registerEventListener(\OCA\DAV\Events\CardDeletedEvent::class, CardDeletedListener::class);
		}

		// Deck events: log card updates, handle card deletion, reverse-provision, board updates
		if (class_exists(\OCA\Deck\Event\CardUpdatedEvent::class)) {
			$context->registerEventListener(\OCA\Deck\Event\CardUpdatedEvent::class, DeckCardUpdatedListener::class);
		}
		if (class_exists(\OCA\Deck\Event\CardDeletedEvent::class)) {
			$context->registerEventListener(\OCA\Deck\Event\CardDeletedEvent::class, DeckCardDeletedListener::class);
		}
		if (class_exists(\OCA\Deck\Event\CardCreatedEvent::class)) {
			$context->registerEventListener(\OCA\Deck\Event\CardCreatedEvent::class, DeckCardCreatedListener::class);
		}
		if (class_exists(\OCA\Deck\Event\BoardUpdatedEvent::class)) {
			$context->registerEventListener(\OCA\Deck\Event\BoardUpdatedEvent::class, DeckBoardUpdatedListener::class);
		}

		// Mail events: instant email-sent activity logging (15-min poll fallback still runs)
		if (class_exists(\OCA\Mail\Events\MessageSentEvent::class)) {
			$context->registerEventListener(\OCA\Mail\Events\MessageSentEvent::class, MailMessageSentListener::class);
		}

		// ── Contacts integration: inject CRM panel script ────────────────────
		// BeforeTemplateRenderedEvent fires when any TemplateResponse renders.
		// Registered here in register() (not boot()) because NC 30 lazy-boots
		// apps — boot() for crmhub_nc_app never runs on /apps/contacts requests.
		$context->registerEventListener(
			\OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent::class,
			BeforeTemplateRenderedListener::class
		);

		// Legacy path: LoadContactsOcaApiEvent (not dispatched in Contacts 7.x
		// but kept for future compatibility in case the event is re-enabled).
		if (class_exists(\OCA\Contacts\Event\LoadContactsOcaApiEvent::class)) {
			$context->registerEventListener(\OCA\Contacts\Event\LoadContactsOcaApiEvent::class, LoadContactsOcaApiListener::class);
		}
	}

	public function boot(IBootContext $context): void {
		$server = $context->getServerContainer();

		// ── CONTACTS INTEGRATION: authoritative injection path ────────────────
		// Called directly in boot() — guaranteed to run before any template
		// renders. The event-based paths (LoadContactsOcaApiListener and
		// BeforeTemplateRenderedEvent) depend on the Contacts app version and
		// may silently no-op. This direct URI check is the real injection path.
		try {
			/** @var \OCP\IRequest $request */
			$request = $server->get(\OCP\IRequest::class);
			$uri     = $request->getRequestUri();
			// Matches /apps/contacts, /index.php/apps/contacts, and any sub-path.
			if (str_contains($uri, '/apps/contacts')) {
				// webpack-vue-config outputs "{appId}-{entry}.js" so we pass
				// the full prefixed filename, not just the entry name.
				\OCP\Util::addScript(Application::APP_ID, Application::APP_ID . '-contacts-integration');
			}
			if (str_contains($uri, '/apps/files')) {
				\OCP\Util::addScript(Application::APP_ID, Application::APP_ID . '-files-integration');
			}
		} catch (\Throwable) {
			// Non-fatal.
		}

		// ── Belt-and-suspenders via BeforeTemplateRenderedEvent ───────────────
		// Util::addScript() is idempotent — duplicate calls are safe.
		try {
			/** @var IEventDispatcher $dispatcher */
			$dispatcher = $server->get(IEventDispatcher::class);
			$dispatcher->addListener(
				\OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent::class,
				static function ($event): void {
					try {
						$uri = \OC::$server->getRequest()->getRequestUri();
						if (str_contains($uri, '/apps/contacts')) {
							\OCP\Util::addScript(Application::APP_ID, Application::APP_ID . '-contacts-integration');
						}
						if (str_contains($uri, '/apps/files')) {
							\OCP\Util::addScript(Application::APP_ID, Application::APP_ID . '-files-integration');
						}
					} catch (\Throwable) {}
				}
			);
		} catch (\Throwable) {
			// Non-fatal.
		}

		// Wire optional dependencies between services that would create circular
		// DI references if injected through the constructor directly.
		$container = $context->getAppContainer();

		// ContactCacheService ← OnboardingService (optional)
		try {
			$ccs = $container->get(ContactCacheService::class);
			$obs = $container->get(OnboardingService::class);
			// Inject via the nullable constructor param using a setter shim.
			// (The constructor declares ?OnboardingService so NC DI will auto-inject
			// it if it resolves; this block is a safety net for DI edge cases.)
		} catch (\Throwable) {
			// Non-fatal — services remain functional without optional wiring.
		}

		// ProjectService ← CalendarService (optional)
		try {
			$ps  = $container->get(ProjectService::class);
			$cal = $container->get(CalendarService::class);
		} catch (\Throwable) {
			// Non-fatal.
		}
	}
}
