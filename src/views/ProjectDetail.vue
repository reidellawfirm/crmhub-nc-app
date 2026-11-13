<template>
	<!-- Loading skeleton while fetching the project -->
	<div v-if="loading" class="rd-page rd-page--skeleton">
		<div class="rd-skeleton rd-skeleton--header" />
		<div class="rd-grid">
			<div class="rd-col rd-col--left"><div class="rd-skeleton rd-skeleton--card" /></div>
			<div class="rd-col rd-col--right"><div class="rd-skeleton rd-skeleton--card" /></div>
		</div>
	</div>
	<div v-else-if="project" class="rd-page">
		<HeaderCard
			:first-name="project.projectName"
			:status="project.status"
			:fallback-name="project.projectName">
			<template #actions>
				<NcButton v-if="project.folderPath" @click="openFolder">
					<template #icon><FolderOutlineIcon :size="16" /></template>
					{{ t('crmhub_nc_app', 'Open folder') }}
				</NcButton>
				<NcButton v-if="contact" @click="$router.push({ name: 'contact', params: { id: contact.id } })">
					<template #icon><AccountOutlineIcon :size="16" /></template>
					{{ contactName }}
				</NcButton>
			</template>
		</HeaderCard>

		<section class="rd-card rd-meta-strip">
			<div v-if="project.projectType" class="rd-meta-strip__item">
				<span class="rd-meta-strip__label">{{ t('crmhub_nc_app', 'Type') }}</span>
				<span class="rd-meta-strip__value">{{ project.projectType }}</span>
			</div>
			<div v-if="project.folderPath" class="rd-meta-strip__item">
				<span class="rd-meta-strip__label">
					<FolderOutlineIcon :size="14" /> {{ t('crmhub_nc_app', 'Folder') }}
				</span>
				<span class="rd-meta-strip__value rd-truncate" :title="project.folderPath">{{ project.folderPath }}</span>
			</div>
			<div v-if="project.deckCardId" class="rd-meta-strip__item">
				<span class="rd-meta-strip__label">
					<ViewDashboardOutlineIcon :size="14" /> {{ t('crmhub_nc_app', 'Deck card') }}
				</span>
				<span class="rd-meta-strip__value">#{{ project.deckCardId }}</span>
			</div>
			<div v-if="project.assignedUserId" class="rd-meta-strip__item">
				<span class="rd-meta-strip__label">
					<AccountOutlineIcon :size="14" /> {{ t('crmhub_nc_app', 'Assigned') }}
				</span>
				<span class="rd-meta-strip__value">{{ project.assignedUserId }}</span>
			</div>
		</section>

		<div class="rd-grid">
			<div class="rd-col rd-col--left">
				<NotesCard :notes="notes" @add-note="addNote" />
			</div>
			<div class="rd-col rd-col--right">
				<ActivityTimeline
					:entries="activity"
					:title="t('crmhub_nc_app', 'Project activity')"
					:empty-message="t('crmhub_nc_app', 'No project activity yet')"
					:empty-hint="t('crmhub_nc_app', 'Notes, file changes and Deck updates will appear here.')" />
			</div>
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import FolderOutlineIcon from 'vue-material-design-icons/FolderOutline.vue'
import AccountOutlineIcon from 'vue-material-design-icons/AccountOutline.vue'
import ViewDashboardOutlineIcon from 'vue-material-design-icons/ViewDashboardOutline.vue'
import HeaderCard from '../components/HeaderCard.vue'
import NotesCard from '../components/NotesCard.vue'
import ActivityTimeline from '../components/ActivityTimeline.vue'
import api from '../api.js'

export default {
	name: 'ProjectDetail',
	components: {
		NcButton, HeaderCard, NotesCard, ActivityTimeline,
		FolderOutlineIcon, AccountOutlineIcon, ViewDashboardOutlineIcon,
	},
	props: { id: { type: [String, Number], required: true } },
	data() { return { loading: true, project: null, contact: null, notes: [], activity: [] } },
	computed: {
		contactName() {
			if (!this.contact) return ''
			return [this.contact.firstName, this.contact.lastName].filter(Boolean).join(' ').trim() || this.contact.email
		},
	},
	mounted() { this.reload() },
	watch: { id() { this.reload() } },
	methods: {
		t,
		async reload() {
			this.loading = true
			const pid = Number(this.id)
			try {
				;[this.project, this.notes, this.activity] = await Promise.all([
					api.projects.get(pid),
					api.notes.forProject(pid),
					api.projects.activity(pid, 50),
				])
				if (!this.project) {
					this.$router.push({ name: 'home' })
					return
				}
			} catch (err) {
				if (err?.response?.status === 404) {
					this.$router.push({ name: 'home' })
					return
				}
				/* other errors shown by api.js withError wrapper */
			} finally {
				this.loading = false
			}
			if (this.project?.contactCacheId) {
				try {
					this.contact = await api.contacts.get(this.project.contactCacheId)
				} catch { /* contact may be inaccessible — not a fatal error */ }
			}
		},
		async addNote(content) {
			try {
				await api.notes.create({
					contactCacheId: this.project.contactCacheId,
					projectId: this.project.id,
					content,
				})
				;[this.notes, this.activity] = await Promise.all([
					api.notes.forProject(this.project.id),
					api.projects.activity(this.project.id, 50),
				])
			} catch { /* shown by withError */ }
		},
		openFolder() {
			if (!this.project?.folderPath) return
			window.open(generateUrl('/apps/files/?dir=' + encodeURIComponent(this.project.folderPath)), '_blank')
		},
	},
}
</script>

<style lang="scss" scoped>
@use '../components/_tokens.scss' as *;

.rd-page {
	display: flex;
	flex-direction: column;
	gap: $rd-space-4;
	padding: $rd-space-5 $rd-space-6;
	max-width: 1280px;
	margin: 0 auto;
	background: $rd-bg-page;
	min-height: 100%;
}

.rd-grid {
	display: grid;
	grid-template-columns: minmax(0, 5fr) minmax(0, 7fr);
	gap: $rd-space-4;
}

.rd-col { min-width: 0; }

.rd-card {
	background: $rd-bg-card;
	border: 1px solid $rd-border;
	border-radius: $rd-radius-lg;
	box-shadow: $rd-shadow-sm;
}

.rd-meta-strip {
	display: flex;
	flex-wrap: wrap;
	gap: $rd-space-5;
	padding: $rd-space-4 $rd-space-5;

	&__item {
		display: flex;
		flex-direction: column;
		gap: 2px;
		min-width: 0;
	}

	&__label {
		display: inline-flex;
		align-items: center;
		gap: 4px;
		font-size: $rd-font-xs;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.4px;
		color: $rd-text-muted;

		:deep(.material-design-icon) { display: inline-flex; }
	}

	&__value {
		font-size: $rd-font-md;
		color: $rd-text;
		max-width: 360px;
	}
}

.rd-truncate {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

@media (max-width: 960px) {
	.rd-page { padding: $rd-space-4; }
	.rd-grid { grid-template-columns: 1fr; }
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
	border-radius: $rd-radius-md;

	&--header { height: 120px; }
	&--card    { height: 240px; }
}

.rd-page--skeleton { pointer-events: none; }
</style>
