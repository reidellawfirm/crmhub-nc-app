// Lightweight in-app event bus so views can notify the sidebar to reload its
// contact list after create / update / delete operations, without re-fetching
// on every route change.

import { reactive } from 'vue'

const listeners = new Map()

export const bus = {
	on(event, fn) {
		if (!listeners.has(event)) listeners.set(event, new Set())
		listeners.get(event).add(fn)
	},
	off(event, fn) {
		listeners.get(event)?.delete(fn)
	},
	emit(event, payload) {
		listeners.get(event)?.forEach((fn) => {
			try { fn(payload) } catch (e) { /* ignore listener errors */ }
		})
	},
}

export const EVT_CONTACTS_CHANGED = 'crmhub_nc_app:contacts:changed'
export const EVT_ACTIVITY_CHANGED = 'crmhub_nc_app:activity:changed'

// Optional shared reactive selection state if needed elsewhere.
export const selection = reactive({ contactId: null })
