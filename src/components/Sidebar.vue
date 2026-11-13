<template>
	<div class="rd-sidebar">
		<nav class="rd-sidebar__nav">
			<router-link :to="{ name: 'home' }" class="rd-nav-link" exact-active-class="rd-nav-link--active">
				<HomeOutlineIcon :size="18" />
				<span>{{ t('crmhub_nc_app', 'Overview') }}</span>
			</router-link>
			<router-link :to="{ name: 'activity' }" class="rd-nav-link" active-class="rd-nav-link--active">
				<ClockOutlineIcon :size="18" />
				<span>{{ t('crmhub_nc_app', 'Activity') }}</span>
			</router-link>
		</nav>

		<div class="rd-sidebar__section">
			<div class="rd-sidebar__section-head">
				<AccountMultipleOutlineIcon :size="16" />
				<span class="rd-sidebar__section-title">{{ t('crmhub_nc_app', 'Contacts') }}</span>
				<span class="rd-sidebar__section-count">{{ contacts.length }}</span>
				<button
					class="rd-sidebar__add"
					:title="t('crmhub_nc_app', 'New contact')"
					@click="createDialog = true">
					<PlusIcon :size="16" />
				</button>
			</div>

			<div class="rd-sidebar__search">
				<MagnifyIcon :size="16" class="rd-sidebar__search-icon" />
				<input
					v-model="query"
					type="search"
					class="rd-sidebar__search-input"
					:placeholder="t('crmhub_nc_app', 'Search contacts…')"
					@input="onSearch">
			</div>
		</div>

		<ul class="rd-sidebar__list">
			<li v-if="!contacts.length && !loading" class="rd-sidebar__empty">
				{{ t('crmhub_nc_app', 'No contacts yet') }}
			</li>
			<!-- Loading skeletons during initial fetch -->
			<template v-if="loading && !contacts.length">
				<li v-for="n in 3" :key="`skel-${n}`" class="rd-contact-item rd-contact-item--skeleton">
					<span class="rd-skeleton rd-skeleton--avatar" />
					<span class="rd-contact-item__body">
						<span class="rd-skeleton rd-skeleton--line rd-skeleton--name" />
						<span class="rd-skeleton rd-skeleton--line rd-skeleton--sub" />
					</span>
				</li>
			</template>
			<li v-for="c in contacts" :key="c.id">
				<router-link
					:to="{ name: 'contact', params: { id: c.id } }"
					class="rd-contact-item"
					active-class="rd-contact-item--active">
					<span class="rd-contact-item__avatar" :style="avatarStyle(c)">{{ initials(c) }}</span>
					<span class="rd-contact-item__body">
						<span class="rd-contact-item__name">{{ displayName(c) }}</span>
						<span class="rd-contact-item__sub">{{ c.email || t('crmhub_nc_app', 'No email') }}</span>
					</span>
					<span class="rd-contact-item__dot" :class="`rd-contact-item__dot--${statusKey(c.status)}`" />
				</router-link>
			</li>
			<!-- Load more button -->
			<li v-if="hasMore" class="rd-sidebar__load-more">
				<button
					:disabled="loadingMore"
					class="rd-sidebar__load-more-btn"
					@click="loadMore">
					<span v-if="loadingMore">{{ t('crmhub_nc_app', 'Loading…') }}</span>
					<span v-else>{{ t('crmhub_nc_app', 'Load more') }}</span>
				</button>
			</li>
		</ul>

		<NcDialog v-if="createDialog" v-model:open="createDialog" :name="t('crmhub_nc_app', 'New contact')">
			<div class="rd-form">
				<!-- Email — required, must be valid + unique -->
				<NcTextField
					v-model="draft.email"
					:label="t('crmhub_nc_app', 'Email *')"
					type="email"
					:error="draftTouched && !!draftEmailError"
					:helper-text="draftTouched ? draftEmailError : ''"
					@blur="draftTouched = true" />
				<!-- Name fields — at least one required -->
				<NcTextField
					v-model="draft.firstName"
					:label="t('crmhub_nc_app', 'First name')"
					:error="draftTouched && !!draftNameError" />
				<NcTextField
					v-model="draft.lastName"
					:label="t('crmhub_nc_app', 'Last name')"
					:error="draftTouched && !!draftNameError" />
				<NcTextField v-model="draft.phone" :label="t('crmhub_nc_app', 'Phone')" />
				<p v-if="draftTouched && draftNameError" class="rd-field-error">{{ draftNameError }}</p>
				<p v-if="createApiError" class="rd-field-error">{{ createApiError }}</p>
			</div>
			<template #actions>
				<NcButton @click="closeCreateDialog">{{ t('crmhub_nc_app', 'Cancel') }}</NcButton>
				<NcButton type="primary" :disabled="creating || !draftCanCreate" @click="create">
					<span v-if="creating">{{ t('crmhub_nc_app', 'Creating…') }}</span>
					<span v-else>{{ t('crmhub_nc_app', 'Create') }}</span>
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { showError } from '@nextcloud/dialogs'
import { subscribe } from '@nextcloud/event-bus'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import HomeOutlineIcon from 'vue-material-design-icons/HomeOutline.vue'
import ClockOutlineIcon from 'vue-material-design-icons/ClockOutline.vue'
import AccountMultipleOutlineIcon from 'vue-material-design-icons/AccountMultipleOutline.vue'
import MagnifyIcon from 'vue-material-design-icons/Magnify.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import api from '../api.js'
import { bus, EVT_CONTACTS_CHANGED, EVT_ACTIVITY_CHANGED } from '../bus.js'

export default {
	name: 'Sidebar',
	components: {
		NcButton, NcDialog, NcTextField,
		HomeOutlineIcon, ClockOutlineIcon, AccountMultipleOutlineIcon, MagnifyIcon, PlusIcon,
	},
	data() {
		return {
			query: '',
			loading: false,
			loadingMore: false,
			hasMore: false,
			page: 0,
			contacts: [],
			createDialog: false,
			draft: { email: '', firstName: '', lastName: '', phone: '' },
			draftTouched: false,
			creating: false,
			createApiError: '',
			searchTimer: null,
		}
	},
	computed: {
		/** Returns an error message string (truthy) or '' (falsy = valid). */
		draftEmailError() {
			const e = (this.draft.email || '').trim()
			if (!e) return this.t('crmhub_nc_app', 'Email is required.')
			// Same pattern used by the backend filter_var(FILTER_VALIDATE_EMAIL).
			if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e)) return this.t('crmhub_nc_app', 'Please enter a valid email address.')
			return ''
		},
		draftNameError() {
			const fn = (this.draft.firstName || '').trim()
			const ln = (this.draft.lastName || '').trim()
			if (!fn && !ln) return this.t('crmhub_nc_app', 'Please provide at least a first or last name.')
			return ''
		},
		draftCanCreate() {
			return !this.draftEmailError && !this.draftNameError
		},
	},
	mounted() {
		this.reload()
		bus.on(EVT_CONTACTS_CHANGED, this.reload)
		bus.on('crmhub_nc_app:dashboard:add_contact', this.openCreateDialog)
		try { subscribe('crmhub_nc_app:contacts:changed', this.reload) } catch (e) { /* optional */ }
	},
	beforeUnmount() {
		bus.off(EVT_CONTACTS_CHANGED, this.reload)
		bus.off('crmhub_nc_app:dashboard:add_contact', this.openCreateDialog)
	},
	methods: {
		t,
		displayName(c) {
			return [c.firstName, c.lastName].filter(Boolean).join(' ').trim() || c.email || '—'
		},
		initials(c) {
			const a = (c.firstName || '').trim()[0] || ''
			const b = (c.lastName || '').trim()[0] || ''
			const composed = (a + b).toUpperCase()
			if (composed) return composed
			return ((c.email || '?')[0] || '?').toUpperCase()
		},
		avatarStyle(c) {
			const seed = this.displayName(c).split('').reduce((acc, ch) => acc + ch.charCodeAt(0), 0)
			const hue = seed % 360
			return { background: `linear-gradient(135deg, hsl(${hue},60%,55%), hsl(${(hue + 40) % 360},60%,45%))` }
		},
		statusKey(s) {
			const k = (s || '').toLowerCase()
			return ['active', 'prospect', 'archived', 'inactive'].includes(k) ? k : 'active'
		},
		onSearch() {
			clearTimeout(this.searchTimer)
			this.searchTimer = setTimeout(() => {
				this.page = 0
				this.contacts = []
				this.reload()
			}, 200)
		},
		async reload() {
			this.loading = true
			this.page = 0
			try {
				const data = await api.contacts.listPage({ q: this.query, limit: 50, offset: 0 })
				this.contacts = data.items ?? []
				this.hasMore = data.hasMore ?? false
			} finally {
				this.loading = false
			}
		},
		async loadMore() {
			if (this.loadingMore || !this.hasMore) return
			this.loadingMore = true
			try {
				const offset = this.contacts.length
				const data = await api.contacts.listPage({ q: this.query, limit: 50, offset })
				this.contacts = [...this.contacts, ...(data.items ?? [])]
				this.hasMore = data.hasMore ?? false
			} finally {
				this.loadingMore = false
			}
		},
		async create() {
			// Trigger validation display.
			this.draftTouched = true
			if (!this.draftCanCreate) return
			this.creating = true
			this.createApiError = ''
			try {
				const c = await api.contacts.create(this.draft)
				this.closeCreateDialog()
				await this.reload()
				// Notify any open views (ActivityFeed, etc.) that new activity exists.
				bus.emit(EVT_CONTACTS_CHANGED)
				bus.emit(EVT_ACTIVITY_CHANGED)
				this.$router.push({ name: 'contact', params: { id: c.id } })
			} catch (err) {
				const status = err?.response?.status
				const data = err?.response?.data
				if (status === 422 && data?.message) {
					// Show inline AND a toast so the error is hard to miss.
					this.createApiError = data.message
					showError(data.message)
				}
				// 5xx toasts are handled by api.js withError().
			} finally {
				this.creating = false
			}
		},
		closeCreateDialog() {
			this.createDialog = false
			this.draft = { email: '', firstName: '', lastName: '', phone: '' }
			this.draftTouched = false
			this.createApiError = ''
		},
		openCreateDialog() {
			this.createDialog = true
		},
	},
}
</script>

<style lang="scss" scoped>
@use './_tokens.scss' as *;

.rd-sidebar {
	display: flex;
	flex-direction: column;
	height: 100%;
	min-height: 0;
	padding: $rd-space-3 0;
	gap: $rd-space-2;
}

.rd-sidebar__nav {
	display: flex;
	flex-direction: column;
	gap: 2px;
	padding: 0 $rd-space-2;
}

.rd-nav-link {
	display: flex;
	align-items: center;
	gap: $rd-space-2;
	padding: $rd-space-2 $rd-space-3;
	border-radius: $rd-radius-md;
	color: $rd-text;
	text-decoration: none;
	font-size: $rd-font-md;
	font-weight: 500;
	transition: background 120ms ease;

	&:hover { background: var(--color-background-hover, #f0f0f0); }
	&--active {
		background: $rd-primary-soft;
		color: $rd-primary;
		:deep(.material-design-icon) { color: $rd-primary; }
	}

	:deep(.material-design-icon) {
		display: inline-flex;
		color: $rd-text-muted;
	}
}

.rd-sidebar__section {
	padding: 0 $rd-space-2;
	margin-top: $rd-space-2;
}

.rd-sidebar__section-head {
	display: flex;
	align-items: center;
	gap: $rd-space-2;
	padding: $rd-space-2 $rd-space-3;
	color: $rd-text-muted;
	font-size: $rd-font-xs;
	text-transform: uppercase;
	letter-spacing: 0.5px;

	:deep(.material-design-icon) { display: inline-flex; }
}

.rd-sidebar__section-title { font-weight: 600; }

.rd-sidebar__section-count {
	margin-left: auto;
	font-weight: 600;
	color: $rd-text-muted;
}

.rd-sidebar__add {
	background: transparent;
	border: none;
	color: $rd-text-muted;
	cursor: pointer;
	padding: 4px;
	border-radius: $rd-radius-sm;
	display: inline-flex;
	align-items: center;
	justify-content: center;

	&:hover {
		background: $rd-primary-soft;
		color: $rd-primary;
	}
}

.rd-sidebar__search {
	position: relative;
	margin: $rd-space-1 $rd-space-3 $rd-space-2;

	&-icon {
		position: absolute;
		left: 8px;
		top: 50%;
		transform: translateY(-50%);
		color: $rd-text-muted;
		pointer-events: none;
		display: inline-flex;
	}

	&-input {
		width: 100%;
		padding: 6px 10px 6px 30px;
		border: 1px solid $rd-border;
		border-radius: $rd-radius-md;
		background: var(--color-main-background, #fff);
		font-size: $rd-font-sm;
		color: $rd-text;
		transition: border-color 120ms ease, box-shadow 120ms ease;

		&:focus {
			outline: none;
			border-color: $rd-primary;
			box-shadow: 0 0 0 3px rgba(0, 130, 201, 0.15);
		}
	}
}

.rd-sidebar__list {
	list-style: none;
	margin: 0;
	padding: 0 $rd-space-2;
	overflow-y: auto;
	flex: 1;
	min-height: 0;
}

.rd-sidebar__empty {
	padding: $rd-space-4 $rd-space-3;
	color: $rd-text-muted;
	font-size: $rd-font-sm;
	text-align: center;
}

.rd-contact-item {
	display: flex;
	align-items: center;
	gap: $rd-space-3;
	padding: $rd-space-2 $rd-space-3;
	border-radius: $rd-radius-md;
	color: $rd-text;
	text-decoration: none;
	transition: background 120ms ease;

	&:hover { background: var(--color-background-hover, #f0f0f0); }

	&--active {
		background: $rd-primary-soft;
		.rd-contact-item__name { color: $rd-primary; }
	}

	&__avatar {
		width: 32px;
		height: 32px;
		border-radius: 50%;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		color: #fff;
		font-weight: 600;
		font-size: $rd-font-xs;
		flex-shrink: 0;
	}

	&__body {
		flex: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;
	}

	&__name {
		font-size: $rd-font-sm;
		font-weight: 600;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__sub {
		font-size: $rd-font-xs;
		color: $rd-text-muted;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__dot {
		width: 8px;
		height: 8px;
		border-radius: 50%;
		flex-shrink: 0;

		&--active   { background: $rd-status-active; }
		&--prospect { background: $rd-status-prospect; }
		&--archived { background: $rd-status-archived; }
		&--inactive { background: $rd-status-inactive; }
	}
}

.rd-form {
	display: flex;
	flex-direction: column;
	gap: $rd-space-3;
	padding: $rd-space-3 $rd-space-2;
}

.rd-field-error {
	color: var(--color-error, #e9322d);
	font-size: 0.8rem;
	margin: -($rd-space-2) 0 0;
	padding: 0 2px;
}

// ── Skeleton loading placeholders ───────────────────────────────────────────
@keyframes rd-shimmer {
	0%   { background-position: -200% 0; }
	100% { background-position: 200% 0; }
}

.rd-skeleton {
	background: linear-gradient(90deg, var(--color-background-dark, #eee) 25%, var(--color-background-hover, #f5f5f5) 50%, var(--color-background-dark, #eee) 75%);
	background-size: 200% 100%;
	animation: rd-shimmer 1.4s ease infinite;
	border-radius: $rd-radius-sm;
	display: block;

	&--avatar {
		width: 32px;
		height: 32px;
		border-radius: 50%;
		flex-shrink: 0;
	}
	&--line   { height: 11px; }
	&--name   { width: 60%; margin-bottom: 4px; }
	&--sub    { width: 80%; }
}

.rd-contact-item--skeleton {
	pointer-events: none;
	opacity: 0.7;
}

// ── Load-more button ─────────────────────────────────────────────────────────
.rd-sidebar__load-more {
	list-style: none;
	padding: $rd-space-2 $rd-space-3;
}

.rd-sidebar__load-more-btn {
	width: 100%;
	padding: $rd-space-2;
	border: 1px dashed $rd-border;
	border-radius: $rd-radius-md;
	background: transparent;
	color: $rd-text-muted;
	font-size: $rd-font-sm;
	cursor: pointer;
	transition: background 120ms ease;

	&:not(:disabled):hover {
		background: var(--color-background-hover, #f0f0f0);
		color: $rd-text;
	}
	&:disabled { cursor: default; opacity: 0.6; }
}
</style>
