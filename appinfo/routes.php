<?php

declare(strict_types=1);

return [
	'routes' => [
		// Diagnostic: verify Contacts integration is wired up (no admin required)
		['name' => 'diagnostic#contactsIntegration', 'url' => '/api/debug/contacts-integration', 'verb' => 'GET'],

		// Dashboard summary (KPIs + recent data for Home view)
		['name' => 'dashboardApi#summary', 'url' => '/api/dashboard/summary', 'verb' => 'GET'],

		// Main page
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'page#hugo', 'url' => '/hugo', 'verb' => 'GET'],

		// --- Hugo Preview API Routes ---
		['name' => 'hugoApi#projects', 'url' => '/api/hugo/projects', 'verb' => 'GET'],
		['name' => 'hugoApi#saveProject', 'url' => '/api/hugo/projects', 'verb' => 'POST'],
		['name' => 'hugoApi#getProject', 'url' => '/api/hugo/projects/{id}', 'verb' => 'GET'],
		['name' => 'hugoApi#deleteProject', 'url' => '/api/hugo/projects/{id}', 'verb' => 'DELETE'],
		['name' => 'hugoApi#buildProject', 'url' => '/api/hugo/projects/{id}/build', 'verb' => 'POST'],
		['name' => 'hugoApi#buildAllProjects', 'url' => '/api/hugo/build/all', 'verb' => 'POST'],
		['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
		['name' => 'config#rotateToken', 'url' => '/config/rotate-token', 'verb' => 'POST'],

		// ── Contacts cache: literal routes MUST come before {id} wildcards ──────
		// NC's router matches in declaration order. If {id} is listed first,
		// requests for /by-email, /by-vcard, /vcards etc. are misrouted to
		// show() with id="by-email" → int cast → 0 → 404.
		['name' => 'contactsApi#index',           'url' => '/api/contacts',                  'verb' => 'GET'],
		['name' => 'contactsApi#create',           'url' => '/api/contacts',                  'verb' => 'POST'],

		// Literal sub-paths — must appear before {id} wildcard
		['name' => 'contactsApi#findByEmail',      'url' => '/api/contacts/by-email',         'verb' => 'GET'],
		['name' => 'contactsApi#findByVcardUid',   'url' => '/api/contacts/by-vcard',         'verb' => 'GET'],
		['name' => 'contactsApi#linkVcard',        'url' => '/api/contacts/link-vcard',       'verb' => 'POST'],
		['name' => 'contactsApi#listVcards',       'url' => '/api/contacts/vcards',           'verb' => 'GET'],

		// Contacts integration summary (by email OR vcardUid)
		['name' => 'contactsApi#contactsIntegration', 'url' => '/api/contacts-integration/summary', 'verb' => 'GET'],

		// Wildcard {id} routes — must come AFTER all literal sub-paths above
		['name' => 'contactsApi#show',             'url' => '/api/contacts/{id}',             'verb' => 'GET'],
		['name' => 'contactsApi#update',           'url' => '/api/contacts/{id}',             'verb' => 'PUT'],
		['name' => 'contactsApi#destroy',          'url' => '/api/contacts/{id}',             'verb' => 'DELETE'],
		['name' => 'contactsApi#activity',         'url' => '/api/contacts/{id}/activity',    'verb' => 'GET'],
		['name' => 'contactsApi#summary',          'url' => '/api/contacts/{id}/summary',     'verb' => 'GET'],
		['name' => 'contactsApi#createActivity',   'url' => '/api/contacts/{id}/activity',    'verb' => 'POST'],
		['name' => 'contactsApi#getTasks',         'url' => '/api/contacts/{id}/tasks',       'verb' => 'GET'],
		['name' => 'contactsApi#createTask',       'url' => '/api/contacts/{id}/tasks',       'verb' => 'POST'],
		['name' => 'contactsApi#moveStage',        'url' => '/api/contacts/{id}/move-stage',  'verb' => 'POST'],

		// Global CRM activity feed (recent entries across all owned contacts)
		['name' => 'contactsApi#recent', 'url' => '/api/activity', 'verb' => 'GET'],

		// Tasks (standalone update endpoint for completing tasks from Contacts panel)
		['name' => 'contactsApi#updateTask', 'url' => '/api/tasks/{id}', 'verb' => 'PUT'],

		// Projects
		['name' => 'projectsApi#index', 'url' => '/api/projects', 'verb' => 'GET'],
		['name' => 'projectsApi#show', 'url' => '/api/projects/{id}', 'verb' => 'GET'],
		['name' => 'projectsApi#forContact', 'url' => '/api/contacts/{contactId}/projects', 'verb' => 'GET'],
		['name' => 'projectsApi#create', 'url' => '/api/contacts/{contactId}/projects', 'verb' => 'POST'],
		['name' => 'projectsApi#update', 'url' => '/api/projects/{id}', 'verb' => 'PUT'],
		['name' => 'projectsApi#destroy', 'url' => '/api/projects/{id}', 'verb' => 'DELETE'],
		['name' => 'projectsApi#projectActivity', 'url' => '/api/projects/{id}/activity', 'verb' => 'GET'],

		// Notes
		['name' => 'notesApi#forContact', 'url' => '/api/contacts/{contactId}/notes', 'verb' => 'GET'],
		['name' => 'notesApi#forProject', 'url' => '/api/projects/{projectId}/notes', 'verb' => 'GET'],
		['name' => 'notesApi#create', 'url' => '/api/notes', 'verb' => 'POST'],
		['name' => 'notesApi#destroy', 'url' => '/api/notes/{id}', 'verb' => 'DELETE'],

		// Relations (the connector)
		['name' => 'relationsApi#lookup', 'url' => '/api/relations', 'verb' => 'GET'],

		// Record ops (Phase 2)
		['name' => 'recordOps#merge', 'url' => '/api/records/merge', 'verb' => 'POST'],
		['name' => 'recordOps#duplicate', 'url' => '/api/records/{id}/duplicate', 'verb' => 'POST'],

		// Windmill integration (Phase 3)
		['name' => 'integrations#emailMatch', 'url' => '/api/integrations/email/match', 'verb' => 'POST'],
		['name' => 'integrations#emailArchive', 'url' => '/api/integrations/email/archive', 'verb' => 'POST'],
		['name' => 'integrations#funnelEvent', 'url' => '/api/integrations/funnel/event', 'verb' => 'POST'],

		// Groundhogg sync (Phase 4)
		['name' => 'sync#runIncremental', 'url' => '/api/sync/groundhogg', 'verb' => 'POST'],
		['name' => 'sync#runFull', 'url' => '/api/sync/groundhogg/full', 'verb' => 'POST'],
		['name' => 'sync#log', 'url' => '/api/sync/log', 'verb' => 'GET'],

		// Analytics (Phase 4/5)
		['name' => 'analyticsApi#trends', 'url' => '/api/analytics/trends', 'verb' => 'GET'],
		['name' => 'analyticsApi#contactGrowth', 'url' => '/api/analytics/contact-growth', 'verb' => 'GET'],
		['name' => 'analyticsApi#projectHealth', 'url' => '/api/analytics/project-health', 'verb' => 'GET'],
		['name' => 'analyticsApi#activityByType', 'url' => '/api/analytics/activity-by-type', 'verb' => 'GET'],

		// CSV Exports (Phase 4/5)
		['name' => 'exportApi#exportContacts', 'url' => '/api/export/contacts', 'verb' => 'GET'],
		['name' => 'exportApi#exportProjects', 'url' => '/api/export/projects', 'verb' => 'GET'],
		['name' => 'exportApi#exportActivities', 'url' => '/api/export/activities', 'verb' => 'GET'],

		// Webhooks CRUD (Phase 4/5)
		['name' => 'webhookApi#index', 'url' => '/api/webhooks', 'verb' => 'GET'],
		['name' => 'webhookApi#create', 'url' => '/api/webhooks', 'verb' => 'POST'],
		['name' => 'webhookApi#update', 'url' => '/api/webhooks/{id}', 'verb' => 'PUT'],
		['name' => 'webhookApi#destroy', 'url' => '/api/webhooks/{id}', 'verb' => 'DELETE'],
	],
];
