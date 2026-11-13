<template>
	<div class="rd-page">
		<header class="rd-pageheader">
			<div class="rd-pageheader__title">
				<ClockOutlineIcon :size="24" />
				<h1>{{ t('crmhub_nc_app', 'Activity timeline') }}</h1>
				<span class="rd-pageheader__count">{{ entries.length }}</span>
			</div>
			<div class="rd-pageheader__actions">
				<NcButton @click="reload">
					<template #icon><RefreshIcon :size="16" /></template>
					{{ t('crmhub_nc_app', 'Refresh') }}
				</NcButton>
			</div>
		</header>

		<ActivityTimeline
			v-if="!loading"
			:entries="entries"
			:title="t('crmhub_nc_app', 'Recent across all contacts')"
			:show-contact="true"
			:empty-message="t('crmhub_nc_app', 'No activity found')"
			:empty-hint="t('crmhub_nc_app', 'Add contacts, notes or projects to start populating the timeline.')" />
		<!-- Skeleton while loading -->
		<div v-else class="rd-activity-skeleton">
			<div v-for="n in 5" :key="n" class="rd-skeleton rd-skeleton--row" />
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import ClockOutlineIcon from 'vue-material-design-icons/ClockOutline.vue'
import RefreshIcon from 'vue-material-design-icons/Refresh.vue'
import ActivityTimeline from '../components/ActivityTimeline.vue'
import api from '../api.js'
import { bus, EVT_CONTACTS_CHANGED, EVT_ACTIVITY_CHANGED } from '../bus.js'

/** Auto-refresh interval (ms). Keeps the timeline live without a full reload. */
const POLL_INTERVAL_MS = 30_000

export default {
	name: 'ActivityFeed',
	components: { NcButton, ActivityTimeline, ClockOutlineIcon, RefreshIcon },
	data() {
		return {
			loading: true,
			entries: [],
			lastSeenId: null,
			_pollTimer: null,
		}
	},
	mounted() {
		this.reload()
		bus.on(EVT_CONTACTS_CHANGED, this.onActivityChanged)
		bus.on(EVT_ACTIVITY_CHANGED, this.onActivityChanged)
		this._pollTimer = setInterval(this.poll, POLL_INTERVAL_MS)
	},
	beforeUnmount() {
		bus.off(EVT_CONTACTS_CHANGED, this.onActivityChanged)
		bus.off(EVT_ACTIVITY_CHANGED, this.onActivityChanged)
		clearInterval(this._pollTimer)
	},
	methods: {
		t,
		/** Full reload — replaces the entire entries array. */
		async reload() {
			this.loading = true
			try {
				this.entries = await api.activity.recent(50)
				if (this.entries.length) {
					this.lastSeenId = Math.max(...this.entries.map(e => e.id ?? 0))
				}
			} finally {
				this.loading = false
			}
		},
		/** Incremental poll — prepends only new entries. */
		async poll() {
			try {
				const fresh = await api.activity.recent(20, this.lastSeenId)
				if (fresh.length) {
					// Prepend newest entries and cap to 100 to avoid unbounded growth.
					this.entries = [...fresh, ...this.entries].slice(0, 100)
					this.lastSeenId = Math.max(...fresh.map(e => e.id ?? 0))
				}
			} catch {
				// Polling failures are silent; the user can manually refresh.
			}
		},
		onActivityChanged() {
			this.reload()
		},
	},
}
</script>

<style lang="scss" scoped>
@use '../components/_tokens.scss' as *;

.rd-page {
	padding: $rd-space-5 $rd-space-6;
	background: $rd-bg-page;
	min-height: 100%;
	max-width: 1280px;
	margin: 0 auto;
}

.rd-pageheader {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: $rd-space-4;
	margin-bottom: $rd-space-4;
	flex-wrap: wrap;

	&__title {
		display: flex;
		align-items: center;
		gap: $rd-space-2;

		h1 {
			margin: 0;
			font-size: $rd-font-2xl;
			font-weight: 600;
			color: $rd-text;
		}

		:deep(.material-design-icon) { color: $rd-primary; display: inline-flex; }
	}

	&__count {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-width: 26px;
		height: 22px;
		padding: 0 8px;
		font-size: $rd-font-xs;
		font-weight: 600;
		color: $rd-text-muted;
		background: $rd-bg-card;
		border: 1px solid $rd-border;
		border-radius: 999px;
	}

	&__actions { display: flex; gap: $rd-space-2; }
}

@media (max-width: 640px) {
	.rd-page { padding: $rd-space-4; }
}

// ── Skeleton shimmer ─────────────────────────────────────────────────────────
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

	&--row { height: 64px; }
}

.rd-activity-skeleton {
	display: flex;
	flex-direction: column;
	gap: $rd-space-3;
	margin-top: $rd-space-3;
}
</style>
