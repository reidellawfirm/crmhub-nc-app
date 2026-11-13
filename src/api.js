import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

const url = (path) => generateUrl('/apps/crmhub_nc_app' + path)
const deckUrl = (path) => generateUrl('/apps/deck/api/v1.0' + path)

/**
 * Wraps a promise: on rejection shows a NC-native error toast and re-throws
 * so the caller can still react (e.g. reset loading state).
 * 5xx / network errors always toast. 4xx errors are re-thrown silently so
 * components can decide whether to show their own inline messages.
 */
function withError(promise, label) {
	return promise.catch((err) => {
		const status = err?.response?.status ?? 0
		if (status === 0 || status >= 500) {
			const msg = err?.response?.data?.error || err?.message || t('crmhub_nc_app', 'Unknown error')
			showError(t('crmhub_nc_app', '{label} failed: {msg}', { label, msg }))
		}
		throw err
	})
}

export default {
	dashboard: {
		summary: () => withError(axios.get(url('/api/dashboard/summary')).then(r => r.data), 'Load dashboard'),
	},
	contacts: {
		/**
		 * Returns a paginated page object: { items, total, limit, offset, hasMore }
		 * Use listPage() from the Sidebar; list() kept for backward compat (returns items[]).
		 */
		listPage: (params = {}) => withError(axios.get(url('/api/contacts'), { params }).then(r => r.data), 'Load contacts'),
		list: (params = {}) => withError(axios.get(url('/api/contacts'), { params }).then(r => {
			// Unwrap paginated envelope — keep backward compat for callers expecting an array.
			const d = r.data
			return Array.isArray(d) ? d : (d.items ?? d)
		}), 'Load contacts'),
		get: (id) => withError(axios.get(url(`/api/contacts/${id}`)).then(r => r.data), 'Load contact'),
		create: (data) => withError(axios.post(url('/api/contacts'), data).then(r => r.data), 'Create contact'),
		update: (id, data) => withError(axios.put(url(`/api/contacts/${id}`), data).then(r => r.data), 'Update contact'),
		remove: (id) => withError(axios.delete(url(`/api/contacts/${id}`)).then(r => r.data), 'Delete contact'),
		linkVcard: (data) => withError(axios.post(url('/api/contacts/link-vcard'), data).then(r => r.data), 'Link vCard'),
		activity: (id, limit = 20) => withError(axios.get(url(`/api/contacts/${id}/activity`), { params: { limit } }).then(r => r.data), 'Load activity'),
		summary: (id) => withError(axios.get(url(`/api/contacts/${id}/summary`)).then(r => r.data), 'Load contact summary'),
		byVcardUid: (vcardUid) => withError(axios.get(url('/api/contacts/by-vcard'), { params: { vcardUid } }).then(r => r.data), 'Lookup contact by vCard UID'),
		byEmail: (email) => withError(axios.get(url('/api/contacts/by-email'), { params: { email } }).then(r => r.data), 'Lookup contact by email'),
	},
	activity: {
		recent: (limit = 50, since = null) => withError(
			axios.get(url('/api/activity'), { params: { limit, ...(since ? { since } : {}) } }).then(r => r.data),
			'Load activity',
		),
	},
	projects: {
		list: (params = {}) => withError(axios.get(url('/api/projects'), { params }).then(r => r.data), 'Load projects'),
		get: (id) => withError(axios.get(url(`/api/projects/${id}`)).then(r => r.data), 'Load project'),
		forContact: (contactId) => withError(axios.get(url(`/api/contacts/${contactId}/projects`)).then(r => r.data), 'Load projects'),
		create: (contactId, data) => withError(axios.post(url(`/api/contacts/${contactId}/projects`), data).then(r => r.data), 'Create project'),
		update: (id, data) => withError(axios.put(url(`/api/projects/${id}`), data).then(r => r.data), 'Update project'),
		remove: (id) => withError(axios.delete(url(`/api/projects/${id}`)).then(r => r.data), 'Delete project'),
		activity: (id, limit = 50) => withError(axios.get(url(`/api/projects/${id}/activity`), { params: { limit } }).then(r => r.data), 'Load activity'),
	},
	notes: {
		forContact: (contactId) => withError(axios.get(url(`/api/contacts/${contactId}/notes`)).then(r => r.data), 'Load notes'),
		forProject: (projectId) => withError(axios.get(url(`/api/projects/${projectId}/notes`)).then(r => r.data), 'Load notes'),
		create: (data) => withError(axios.post(url('/api/notes'), data).then(r => r.data), 'Create note'),
		remove: (id) => withError(axios.delete(url(`/api/notes/${id}`)).then(r => r.data), 'Delete note'),
	},
	relations: {
		lookup: (params) => withError(axios.get(url('/api/relations'), { params }).then(r => r.data), 'Lookup relations'),
	},
	records: {
		merge: (fromContactId, toContactId) => withError(axios.post(url('/api/records/merge'), { fromContactId, toContactId }).then(r => r.data), 'Merge contacts'),
		duplicate: (id) => withError(axios.post(url(`/api/records/${id}/duplicate`)).then(r => r.data), 'Duplicate contact'),
	},
	/**
	 * Deck REST API calls (soft-dependency: Deck app may not be installed).
	 * All calls gracefully return [] on failure — the picker hides itself
	 * when no boards are available.
	 */
	deck: {
		boards: () => axios.get(deckUrl('/boards')).then(r => r.data).catch(() => []),
		stacks: (boardId) => axios.get(deckUrl(`/boards/${boardId}/stacks`)).then(r => r.data).catch(() => []),
	},
	analytics: {
		trends: (days = 30) => withError(axios.get(url('/api/analytics/trends'), { params: { days } }).then(r => r.data), 'Load trends'),
		projectHealth: () => withError(axios.get(url('/api/analytics/project-health')).then(r => r.data), 'Load project health'),
		contactGrowth: (weeks = 12) => withError(axios.get(url('/api/analytics/contact-growth'), { params: { weeks } }).then(r => r.data), 'Load contact growth'),
		activityByType: (days = 30) => withError(axios.get(url('/api/analytics/activity-by-type'), { params: { days } }).then(r => r.data), 'Load activity breakdown'),
	},
	export: {
		contactsUrl: () => url('/api/export/contacts'),
		projectsUrl: () => url('/api/export/projects'),
		activitiesUrl: (contactId = null) => contactId ? url(`/api/export/activities?contactId=${contactId}`) : url('/api/export/activities'),
	},
	webhooks: {
		list: () => withError(axios.get(url('/api/webhooks')).then(r => r.data), 'Load webhooks'),
		create: (data) => withError(axios.post(url('/api/webhooks'), data).then(r => r.data), 'Create webhook'),
		update: (id, data) => withError(axios.put(url(`/api/webhooks/${id}`), data).then(r => r.data), 'Update webhook'),
		remove: (id) => withError(axios.delete(url(`/api/webhooks/${id}`)).then(r => r.data), 'Delete webhook'),
	},
}
