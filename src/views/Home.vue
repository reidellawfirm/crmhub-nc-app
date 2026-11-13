<template>
	<div class="rd-dash">

		<!-- ── Compact header ─────────────────────────────────────────────── -->
		<header class="rd-dash__header">
			<div class="rd-dash__header-left">
				<h1 class="rd-dash__title">{{ t('crmhub_nc_app', 'CRM Overview') }}</h1>
				<span v-if="!loading" class="rd-dash__sub">
					{{ t('crmhub_nc_app', '{n} contacts', { n: kpi.totalContacts }) }}
				</span>
			</div>
			<div class="rd-dash__header-right">
				<NcButton :disabled="loading" @click="load">
					<template #icon><RefreshIcon :size="16" /></template>
					{{ t('crmhub_nc_app', 'Refresh') }}
				</NcButton>
				<NcButton type="primary" @click="openCreateContact">
					<template #icon><PlusIcon :size="16" /></template>
					{{ t('crmhub_nc_app', 'Add contact') }}
				</NcButton>
			</div>
		</header>

		<!-- ── KPI row ────────────────────────────────────────────────────── -->
		<div class="rd-kpi-row">
			<KpiCard
				:value="kpi.totalContacts"
				:label="t('crmhub_nc_app', 'Total contacts')"
				color="blue"
				:loading="loading">
				<template #icon><AccountMultipleOutlineIcon :size="22" /></template>
			</KpiCard>
			<KpiCard
				:value="kpi.activeProjects"
				:label="t('crmhub_nc_app', 'Active projects')"
				color="green"
				:loading="loading">
				<template #icon><FolderOpenOutlineIcon :size="22" /></template>
			</KpiCard>
			<KpiCard
				:value="kpi.notesCount"
				:label="t('crmhub_nc_app', 'Notes')"
				color="amber"
				:loading="loading">
				<template #icon><NoteTextOutlineIcon :size="22" /></template>
			</KpiCard>
			<KpiCard
				:value="kpi.activitiesToday"
				:label="t('crmhub_nc_app', 'Activity today')"
				color="purple"
				:loading="loading">
				<template #icon><ClockOutlineIcon :size="22" /></template>
			</KpiCard>
		</div>

		<!-- ── Main content grid ─────────────────────────────────────────── -->
		<div class="rd-dash__grid">

			<!-- LEFT column -->
			<div class="rd-dash__col rd-dash__col--left">

				<!-- Recent contacts -->
				<section class="rd-card">
					<div class="rd-card__head">
						<AccountMultipleOutlineIcon :size="16" class="rd-card__head-icon" />
						<h2 class="rd-card__title">{{ t('crmhub_nc_app', 'Recent contacts') }}</h2>
					</div>

					<div v-if="loading" class="rd-card__body">
						<div v-for="n in 4" :key="n" class="rd-contact-row rd-contact-row--skel">
							<span class="rd-skeleton rd-skeleton--avatar" />
							<span class="rd-skel-lines">
								<span class="rd-skeleton rd-skeleton--line" style="width:55%" />
								<span class="rd-skeleton rd-skeleton--line" style="width:35%" />
							</span>
						</div>
					</div>

					<ul v-else-if="recentContacts.length" class="rd-card__body rd-card__body--list">
						<li v-for="c in recentContacts" :key="c.id">
							<router-link
								:to="{ name: 'contact', params: { id: c.id } }"
								class="rd-contact-row">
								<span class="rd-contact-row__avatar" :style="avatarStyle(c)">
									{{ initials(c) }}
								</span>
								<span class="rd-contact-row__body">
									<span class="rd-contact-row__name">{{ displayName(c) }}</span>
									<span class="rd-contact-row__sub">{{ c.organization || c.email || '—' }}</span>
								</span>
								<span class="rd-status-dot" :class="`rd-status-dot--${statusKey(c.status)}`" />
							</router-link>
						</li>
					</ul>

					<div v-else class="rd-card__empty">
						<AccountMultipleOutlineIcon :size="32" />
						<p>{{ t('crmhub_nc_app', 'No contacts yet. Add your first contact!') }}</p>
					</div>
				</section>

				<!-- Project status -->
				<section class="rd-card">
					<div class="rd-card__head">
						<FolderOpenOutlineIcon :size="16" class="rd-card__head-icon" />
						<h2 class="rd-card__title">{{ t('crmhub_nc_app', 'Projects by status') }}</h2>
					</div>
					<div class="rd-card__body rd-status-list">
						<div
							v-for="s in projectStatusRows"
							:key="s.key"
							class="rd-status-row">
							<span class="rd-status-row__label">
								<span class="rd-status-row__dot" :class="`rd-status-row__dot--${s.key}`" />
								{{ s.label }}
							</span>
							<div class="rd-status-row__bar-wrap">
								<div
									class="rd-status-row__bar"
									:class="`rd-status-row__bar--${s.key}`"
									:style="{ width: barWidth(s.count) + '%' }" />
							</div>
							<span class="rd-status-row__count">{{ loading ? '—' : s.count }}</span>
						</div>
					</div>
				</section>

			</div>

			<!-- RIGHT column -->
			<div class="rd-dash__col rd-dash__col--right">

				<!-- Quick actions -->
				<section class="rd-card rd-quick-actions">
					<div class="rd-card__head">
						<BoltIcon :size="16" class="rd-card__head-icon" />
						<h2 class="rd-card__title">{{ t('crmhub_nc_app', 'Quick actions') }}</h2>
					</div>
					<div class="rd-card__body rd-quick-actions__grid">
						<button class="rd-qa-btn" @click="openCreateContact">
							<span class="rd-qa-btn__icon rd-qa-btn__icon--blue">
								<AccountPlusOutlineIcon :size="20" />
							</span>
							<span class="rd-qa-btn__label">{{ t('crmhub_nc_app', 'Add contact') }}</span>
						</button>
						<button class="rd-qa-btn" @click="$router.push({ name: 'activity' })">
							<span class="rd-qa-btn__icon rd-qa-btn__icon--purple">
								<ClockOutlineIcon :size="20" />
							</span>
							<span class="rd-qa-btn__label">{{ t('crmhub_nc_app', 'Activity feed') }}</span>
						</button>
						<button class="rd-qa-btn" @click="load">
							<span class="rd-qa-btn__icon rd-qa-btn__icon--amber">
								<RefreshIcon :size="20" />
							</span>
							<span class="rd-qa-btn__label">{{ t('crmhub_nc_app', 'Refresh data') }}</span>
						</button>
						<button class="rd-qa-btn" @click="$router.push({ name: 'reports' })">
							<span class="rd-qa-btn__icon rd-qa-btn__icon--green">
								<FileChartOutlineIcon :size="20" />
							</span>
							<span class="rd-qa-btn__label">{{ t('crmhub_nc_app', 'Reports') }}</span>
						</button>
					</div>
				</section>

				<!-- Recent activity -->
				<section class="rd-card rd-card--grow">
					<div class="rd-card__head">
						<ClockOutlineIcon :size="16" class="rd-card__head-icon" />
						<h2 class="rd-card__title">{{ t('crmhub_nc_app', 'Recent activity') }}</h2>
						<router-link :to="{ name: 'activity' }" class="rd-card__link">
							{{ t('crmhub_nc_app', 'All activity →') }}
						</router-link>
					</div>

					<div v-if="loading" class="rd-card__body">
						<div v-for="n in 5" :key="n" class="rd-activity-row rd-activity-row--skel">
							<span class="rd-skeleton rd-skeleton--dot" />
							<span class="rd-skel-lines">
								<span class="rd-skeleton rd-skeleton--line" style="width:70%" />
								<span class="rd-skeleton rd-skeleton--line" style="width:30%" />
							</span>
						</div>
					</div>

					<ol v-else-if="recentActivity.length" class="rd-card__body rd-activity-feed">
						<li
							v-for="entry in recentActivity"
							:key="entry.id"
							class="rd-activity-row"
							:class="`rd-activity-row--${typeKey(entry.activityType)}`">
							<span class="rd-activity-row__dot">
								<component :is="iconFor(entry.activityType)" :size="14" />
							</span>
							<span class="rd-activity-row__body">
								<span class="rd-activity-row__title">
									{{ entry.summary || labelFor(entry.activityType) }}
								</span>
								<span class="rd-activity-row__meta">
									<span
										v-if="entry.contactName"
										class="rd-activity-row__contact"
										@click.prevent="entry.contactCacheId && $router.push({ name: 'contact', params: { id: entry.contactCacheId } })">
										{{ entry.contactName }}
									</span>
									<span class="rd-activity-row__time" :title="absoluteTime(entry.occurredAt)">
										{{ relativeTime(entry.occurredAt) }}
									</span>
								</span>
							</span>
						</li>
					</ol>

					<div v-else class="rd-card__empty">
						<ClockOutlineIcon :size="32" />
						<p>{{ t('crmhub_nc_app', 'No activity yet. Start by adding contacts or notes.') }}</p>
					</div>
				</section>

			</div>
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import AccountMultipleOutlineIcon from 'vue-material-design-icons/AccountMultipleOutline.vue'
import AccountPlusOutlineIcon from 'vue-material-design-icons/AccountPlusOutline.vue'
import ClockOutlineIcon from 'vue-material-design-icons/ClockOutline.vue'
import FolderOpenOutlineIcon from 'vue-material-design-icons/FolderOpenOutline.vue'
import NoteTextOutlineIcon from 'vue-material-design-icons/NoteTextOutline.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import RefreshIcon from 'vue-material-design-icons/Refresh.vue'
import BoltIcon from 'vue-material-design-icons/LightningBolt.vue'
import FileChartOutlineIcon from 'vue-material-design-icons/FileChartOutline.vue'
// Activity icon map
import NoteIcon from 'vue-material-design-icons/NoteTextOutline.vue'
import FolderPlusIcon from 'vue-material-design-icons/FolderPlusOutline.vue'
import EmailIcon from 'vue-material-design-icons/EmailOutline.vue'
import EmailSentIcon from 'vue-material-design-icons/EmailFastOutline.vue'
import CardIcon from 'vue-material-design-icons/CardAccountDetailsOutline.vue'
import FileIcon from 'vue-material-design-icons/FileDocumentOutline.vue'
import FolderEditIcon from 'vue-material-design-icons/FolderEditOutline.vue'
import api from '../api.js'
import { bus, EVT_CONTACTS_CHANGED, EVT_ACTIVITY_CHANGED } from '../bus.js'

const TYPE_MAP = {
	note_added:      { label: 'Note added',      icon: NoteIcon,       key: 'note'    },
	project_created: { label: 'Project created',  icon: FolderPlusIcon, key: 'project' },
	project_updated: { label: 'Project updated',  icon: FolderEditIcon, key: 'project' },
	contact_created: { label: 'Contact created',  icon: CardIcon,       key: 'contact' },
	contact_updated: { label: 'Contact updated',  icon: CardIcon,       key: 'contact' },
	contact_deleted: { label: 'Contact deleted',  icon: CardIcon,       key: 'contact' },
	vcard_linked:    { label: 'Contact linked',   icon: CardIcon,       key: 'contact' },
	email_received:  { label: 'Email received',   icon: EmailIcon,      key: 'email'   },
	email_sent:      { label: 'Email sent',       icon: EmailSentIcon,  key: 'email'   },
	file_added:      { label: 'File added',       icon: FileIcon,       key: 'file'    },
	file_uploaded:   { label: 'File uploaded',    icon: FileIcon,       key: 'file'    },
	deck_update:     { label: 'Deck updated',     icon: FolderEditIcon, key: 'project' },
}

const PROJECT_STATUS_DEFS = [
	{ key: 'active',    label: 'Active'    },
	{ key: 'pending',   label: 'Pending'   },
	{ key: 'completed', label: 'Completed' },
	{ key: 'archived',  label: 'Archived'  },
]

// Inline KPI card component — no extra file
const KpiCard = {
	name: 'KpiCard',
	props: {
		value:   { type: Number, default: 0 },
		label:   { type: String, required: true },
		color:   { type: String, default: 'blue' },
		loading: { type: Boolean, default: false },
	},
	data() { return { displayed: 0, raf: null } },
	watch: {
		value(to) { this.animateTo(to) },
	},
	methods: {
		animateTo(target) {
			if (this.raf) cancelAnimationFrame(this.raf)
			const start = this.displayed
			const delta = target - start
			if (delta === 0) return
			const duration = 600
			const startTime = performance.now()
			const step = (now) => {
				const progress = Math.min(1, (now - startTime) / duration)
				const ease = 1 - Math.pow(1 - progress, 3)
				this.displayed = Math.round(start + delta * ease)
				if (progress < 1) this.raf = requestAnimationFrame(step)
			}
			this.raf = requestAnimationFrame(step)
		},
	},
	template: `
		<div class="rd-kpi" :class="['rd-kpi--' + color, loading ? 'rd-kpi--loading' : '']">
			<div class="rd-kpi__icon-wrap">
				<slot name="icon" />
			</div>
			<div class="rd-kpi__body">
				<span class="rd-kpi__value">{{ loading ? '—' : displayed }}</span>
				<span class="rd-kpi__label">{{ label }}</span>
			</div>
		</div>
	`,
}

export default {
	name: 'Home',
	components: {
		NcButton, KpiCard,
		AccountMultipleOutlineIcon, AccountPlusOutlineIcon,
		ClockOutlineIcon, FolderOpenOutlineIcon, NoteTextOutlineIcon,
		PlusIcon, RefreshIcon, BoltIcon, FileChartOutlineIcon,
		CardIcon, NoteIcon, FolderPlusIcon, EmailIcon, EmailSentIcon, FileIcon, FolderEditIcon,
	},
	data() {
		return {
			loading: true,
			kpi: { totalContacts: 0, activeProjects: 0, notesCount: 0, activitiesToday: 0 },
			projectStatus: {},
			recentContacts: [],
			recentActivity: [],
		}
	},
	computed: {
		projectStatusRows() {
			const total = Object.values(this.projectStatus).reduce((s, n) => s + n, 0) || 1
			return PROJECT_STATUS_DEFS.map(d => ({
				...d,
				count: this.projectStatus[d.key] ?? 0,
				pct: ((this.projectStatus[d.key] ?? 0) / total) * 100,
			}))
		},
	},
	mounted() {
		this.load()
		bus.on(EVT_CONTACTS_CHANGED, this.load)
		bus.on(EVT_ACTIVITY_CHANGED, this.load)
	},
	beforeUnmount() {
		bus.off(EVT_CONTACTS_CHANGED, this.load)
		bus.off(EVT_ACTIVITY_CHANGED, this.load)
	},
	methods: {
		t,
		async load() {
			this.loading = true
			try {
				const data = await api.dashboard.summary()
				this.kpi            = data.kpi
				this.projectStatus  = data.projectStatus ?? {}
				this.recentContacts = data.recentContacts ?? []
				this.recentActivity = data.recentActivity ?? []
			} catch {
				// withError() already showed a toast for 5xx/network
			} finally {
				this.loading = false
			}
		},
		openCreateContact() {
			bus.emit('crmhub_nc_app:dashboard:add_contact')
		},
		// ── Contact helpers ───────────────────────────────────────────
		displayName(c) {
			return [c.firstName, c.lastName].filter(Boolean).join(' ').trim() || c.email || '—'
		},
		initials(c) {
			const a = ((c.firstName || '').trim()[0] || '').toUpperCase()
			const b = ((c.lastName  || '').trim()[0] || '').toUpperCase()
			if (a || b) return a + b
			return ((c.email || '?')[0] || '?').toUpperCase()
		},
		avatarStyle(c) {
			const seed = this.displayName(c).split('').reduce((acc, ch) => acc + ch.charCodeAt(0), 0)
			const hue  = seed % 360
			return { background: `linear-gradient(135deg, hsl(${hue},60%,55%), hsl(${(hue + 40) % 360},60%,45%))` }
		},
		statusKey(s) {
			const k = (s || '').toLowerCase()
			return ['active', 'prospect', 'archived', 'inactive'].includes(k) ? k : 'active'
		},
		// ── Project status bars ───────────────────────────────────────
		barWidth(count) {
			const total = Object.values(this.projectStatus).reduce((s, n) => s + n, 0) || 1
			return Math.round((count / total) * 100)
		},
		// ── Activity helpers ──────────────────────────────────────────
		typeKey(type)  { return (TYPE_MAP[type] && TYPE_MAP[type].key)  || 'default' },
		iconFor(type)  { return (TYPE_MAP[type] && TYPE_MAP[type].icon) || ClockOutlineIcon },
		labelFor(type) {
			if (TYPE_MAP[type]) return t('crmhub_nc_app', TYPE_MAP[type].label)
			return type ? type.replace(/_/g, ' ') : t('crmhub_nc_app', 'Activity')
		},
		absoluteTime(ts) { return ts ? new Date(ts * 1000).toLocaleString() : '' },
		relativeTime(ts) {
			if (!ts) return ''
			const diff = Math.floor(Date.now() / 1000 - ts)
			if (diff < 60)     return t('crmhub_nc_app', 'just now')
			if (diff < 3600)   return Math.floor(diff / 60) + 'm'
			if (diff < 86400)  return Math.floor(diff / 3600) + 'h'
			if (diff < 604800) return Math.floor(diff / 86400) + 'd'
			return new Date(ts * 1000).toLocaleDateString()
		},
	},
}
</script>

<style lang="scss" scoped>
@use '../components/_tokens.scss' as *;

// ── Page shell ────────────────────────────────────────────────────────────────
.rd-dash {
	display: flex;
	flex-direction: column;
	gap: $rd-space-4;
	padding: $rd-space-5 $rd-space-6;
	max-width: 1440px;
	margin: 0 auto;
	min-height: 100%;
	background: $rd-bg-page;
}

// ── Header ────────────────────────────────────────────────────────────────────
.rd-dash__header {
	display: flex;
	align-items: center;
	gap: $rd-space-3;
}

.rd-dash__header-left {
	display: flex;
	align-items: baseline;
	gap: $rd-space-3;
	flex: 1;
}

.rd-dash__title {
	margin: 0;
	font-size: $rd-font-xl;
	font-weight: 700;
	color: $rd-text;
}

.rd-dash__sub {
	font-size: $rd-font-sm;
	color: $rd-text-muted;
}

.rd-dash__header-right {
	display: flex;
	align-items: center;
	gap: $rd-space-2;
}

// ── KPI row ───────────────────────────────────────────────────────────────────
.rd-kpi-row {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: $rd-space-3;

	@media (max-width: 1024px) { grid-template-columns: repeat(2, 1fr); }
	@media (max-width: 600px)  { grid-template-columns: 1fr; }
}

:global(.rd-kpi) {
	display: flex;
	align-items: center;
	gap: $rd-space-3;
	padding: $rd-space-4 $rd-space-5;
	background: $rd-bg-card;
	border: 1px solid $rd-border;
	border-radius: $rd-radius-lg;
	box-shadow: $rd-shadow-sm;
	transition: transform 120ms ease, box-shadow 120ms ease;
	cursor: default;

	&:hover {
		transform: translateY(-2px);
		box-shadow: $rd-shadow-md;
	}

	&.rd-kpi--loading { opacity: 0.6; }
}

:global(.rd-kpi__icon-wrap) {
	flex-shrink: 0;
	width: 44px;
	height: 44px;
	border-radius: $rd-radius-md;
	display: flex;
	align-items: center;
	justify-content: center;
}

:global(.rd-kpi--blue   .rd-kpi__icon-wrap) { background: #dbeafe; color: #2563eb; }
:global(.rd-kpi--green  .rd-kpi__icon-wrap) { background: #d1fae5; color: #059669; }
:global(.rd-kpi--amber  .rd-kpi__icon-wrap) { background: #fef3c7; color: #d97706; }
:global(.rd-kpi--purple .rd-kpi__icon-wrap) { background: #ede9fe; color: #7c3aed; }

:global(.rd-kpi__body) {
	display: flex;
	flex-direction: column;
}

:global(.rd-kpi__value) {
	font-size: $rd-font-2xl;
	font-weight: 700;
	color: $rd-text;
	line-height: 1;
}

:global(.rd-kpi__label) {
	font-size: $rd-font-xs;
	color: $rd-text-muted;
	margin-top: 2px;
	text-transform: uppercase;
	letter-spacing: 0.4px;
}

// ── Main grid ─────────────────────────────────────────────────────────────────
.rd-dash__grid {
	display: grid;
	grid-template-columns: minmax(0, 5fr) minmax(0, 4fr);
	gap: $rd-space-4;
	align-items: start;

	@media (max-width: 960px) { grid-template-columns: 1fr; }
}

.rd-dash__col {
	display: flex;
	flex-direction: column;
	gap: $rd-space-4;
	min-width: 0;
}

// ── Generic card ──────────────────────────────────────────────────────────────
.rd-card {
	background: $rd-bg-card;
	border: 1px solid $rd-border;
	border-radius: $rd-radius-lg;
	box-shadow: $rd-shadow-sm;
	overflow: hidden;

	&--grow { flex: 1; }
}

.rd-card__head {
	display: flex;
	align-items: center;
	gap: $rd-space-2;
	padding: $rd-space-3 $rd-space-4;
	border-bottom: 1px solid $rd-border;

	:deep(.material-design-icon) { color: $rd-text-muted; display: inline-flex; }
}

.rd-card__title {
	margin: 0;
	font-size: $rd-font-md;
	font-weight: 600;
	color: $rd-text;
	flex: 1;
}

.rd-card__link {
	font-size: $rd-font-xs;
	color: $rd-primary;
	text-decoration: none;
	&:hover { text-decoration: underline; }
}

.rd-card__body {
	padding: $rd-space-2 0;
	&--list { list-style: none; margin: 0; padding: 0; }
}

.rd-card__empty {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: $rd-space-2;
	padding: $rd-space-8 $rd-space-4;
	color: $rd-text-muted;
	text-align: center;

	:deep(.material-design-icon) { color: $rd-text-muted; display: inline-flex; opacity: 0.4; }
	p { margin: 0; font-size: $rd-font-sm; }
}

// ── Contact rows ──────────────────────────────────────────────────────────────
.rd-contact-row {
	display: flex;
	align-items: center;
	gap: $rd-space-3;
	padding: $rd-space-2 $rd-space-4;
	text-decoration: none;
	color: inherit;
	transition: background 100ms ease;

	&:hover { background: var(--color-background-hover, #f0f0f0); }
	&--skel { pointer-events: none; }
}

.rd-contact-row__avatar {
	flex-shrink: 0;
	width: 34px;
	height: 34px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	color: #fff;
	font-size: $rd-font-xs;
	font-weight: 700;
}

.rd-contact-row__body {
	display: flex;
	flex-direction: column;
	flex: 1;
	min-width: 0;
}

.rd-contact-row__name {
	font-size: $rd-font-sm;
	font-weight: 500;
	color: $rd-text;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.rd-contact-row__sub {
	font-size: $rd-font-xs;
	color: $rd-text-muted;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.rd-status-dot {
	flex-shrink: 0;
	width: 8px;
	height: 8px;
	border-radius: 50%;
	margin-left: auto;

	&--active   { background: $rd-status-active; }
	&--prospect { background: $rd-status-prospect; }
	&--inactive { background: $rd-status-inactive; }
	&--archived { background: $rd-status-archived; }
}

// ── Project status bars ───────────────────────────────────────────────────────
.rd-status-list {
	padding: $rd-space-3 $rd-space-4;
	display: flex;
	flex-direction: column;
	gap: $rd-space-3;
}

.rd-status-row {
	display: flex;
	align-items: center;
	gap: $rd-space-3;
}

.rd-status-row__label {
	display: flex;
	align-items: center;
	gap: $rd-space-2;
	width: 90px;
	flex-shrink: 0;
	font-size: $rd-font-sm;
	color: $rd-text;
}

.rd-status-row__dot {
	width: 8px;
	height: 8px;
	border-radius: 50%;
	flex-shrink: 0;

	&--active    { background: $rd-status-active; }
	&--pending   { background: $rd-status-prospect; }
	&--completed { background: $rd-primary; }
	&--archived  { background: $rd-status-archived; }
}

.rd-status-row__bar-wrap {
	flex: 1;
	height: 6px;
	background: $rd-border;
	border-radius: 99px;
	overflow: hidden;
}

.rd-status-row__bar {
	height: 100%;
	border-radius: 99px;
	transition: width 600ms cubic-bezier(0.34, 1.56, 0.64, 1);

	&--active    { background: $rd-status-active; }
	&--pending   { background: $rd-status-prospect; }
	&--completed { background: $rd-primary; }
	&--archived  { background: $rd-status-archived; }
}

.rd-status-row__count {
	width: 24px;
	text-align: right;
	font-size: $rd-font-sm;
	font-weight: 600;
	color: $rd-text-muted;
}

// ── Quick actions ─────────────────────────────────────────────────────────────
.rd-quick-actions__grid {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: $rd-space-2;
	padding: $rd-space-3 $rd-space-4 $rd-space-4;
}

.rd-qa-btn {
	all: unset;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: $rd-space-2;
	padding: $rd-space-3;
	border-radius: $rd-radius-md;
	border: 1px solid $rd-border;
	cursor: pointer;
	transition: background 120ms ease, transform 120ms ease, box-shadow 120ms ease;
	text-align: center;
	box-sizing: border-box;

	&:hover {
		background: $rd-bg-page;
		transform: translateY(-1px);
		box-shadow: $rd-shadow-sm;
	}

	&:active { transform: translateY(0); }
}

.rd-qa-btn__icon {
	width: 40px;
	height: 40px;
	border-radius: $rd-radius-md;
	display: flex;
	align-items: center;
	justify-content: center;

	:deep(.material-design-icon) { display: inline-flex; }

	&--blue   { background: #dbeafe; color: #2563eb; :deep(.material-design-icon) { color: #2563eb; } }
	&--purple { background: #ede9fe; color: #7c3aed; :deep(.material-design-icon) { color: #7c3aed; } }
	&--amber  { background: #fef3c7; color: #d97706; :deep(.material-design-icon) { color: #d97706; } }		&--green  { background: #d1fae5; color: #059669; :deep(.material-design-icon) { color: #059669; } }}

.rd-qa-btn__label {
	font-size: $rd-font-xs;
	color: $rd-text;
	font-weight: 500;
}

// ── Activity feed ─────────────────────────────────────────────────────────────
.rd-activity-feed {
	list-style: none;
	margin: 0;
	padding: $rd-space-2 0;
}

.rd-activity-row {
	display: flex;
	align-items: flex-start;
	gap: $rd-space-3;
	padding: $rd-space-2 $rd-space-4;
	transition: background 100ms ease;

	&:hover { background: var(--color-background-hover, #f0f0f0); }
	&--skel { pointer-events: none; }
}

.rd-activity-row__dot {
	flex-shrink: 0;
	width: 26px;
	height: 26px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	margin-top: 1px;
	background: $rd-border;
	color: $rd-text-muted;

	:deep(.material-design-icon) { display: inline-flex; }

	.rd-activity-row--note    & { background: #fef3c7; color: #d97706; :deep(.material-design-icon) { color: #d97706; } }
	.rd-activity-row--project & { background: #d1fae5; color: #059669; :deep(.material-design-icon) { color: #059669; } }
	.rd-activity-row--contact & { background: #dbeafe; color: #2563eb; :deep(.material-design-icon) { color: #2563eb; } }
	.rd-activity-row--email   & { background: #ede9fe; color: #7c3aed; :deep(.material-design-icon) { color: #7c3aed; } }
	.rd-activity-row--file    & { background: #fce7f3; color: #be185d; :deep(.material-design-icon) { color: #be185d; } }
}

.rd-activity-row__body {
	display: flex;
	flex-direction: column;
	flex: 1;
	min-width: 0;
}

.rd-activity-row__title {
	font-size: $rd-font-sm;
	color: $rd-text;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.rd-activity-row__meta {
	display: flex;
	align-items: center;
	gap: $rd-space-2;
	margin-top: 1px;
}

.rd-activity-row__contact {
	font-size: $rd-font-xs;
	color: $rd-primary;
	cursor: pointer;
	&:hover { text-decoration: underline; }
}

.rd-activity-row__time {
	font-size: $rd-font-xs;
	color: $rd-text-muted;
}

// ── Skeleton shimmer ──────────────────────────────────────────────────────────
@keyframes rd-shimmer {
	0%   { background-position: -200% 0; }
	100% { background-position:  200% 0; }
}

.rd-skeleton {
	border-radius: $rd-radius-sm;
	background: linear-gradient(90deg, $rd-border 25%, rgba(255,255,255,0.5) 50%, $rd-border 75%);
	background-size: 200% 100%;
	animation: rd-shimmer 1.4s ease-in-out infinite;

	&--avatar {
		display: inline-block;
		width: 34px;
		height: 34px;
		border-radius: 50%;
		flex-shrink: 0;
	}

	&--dot {
		display: inline-block;
		width: 26px;
		height: 26px;
		border-radius: 50%;
		flex-shrink: 0;
	}

	&--line {
		display: block;
		height: 12px;
		border-radius: $rd-radius-sm;
		margin-bottom: 4px;
	}
}

.rd-skel-lines {
	display: flex;
	flex-direction: column;
	flex: 1;
	gap: $rd-space-1;
	justify-content: center;
}
</style>
