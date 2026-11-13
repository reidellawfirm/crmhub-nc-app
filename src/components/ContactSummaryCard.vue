<template>
	<SectionCard :title="t('crmhub_nc_app', 'CRM summary')">
		<div class="rd-contact-summary">
			<div class="rd-contact-summary__row">
				<dt>{{ t('crmhub_nc_app', 'Status') }}</dt>
				<dd><span class="rd-chip rd-chip--status">{{ contact.status || t('crmhub_nc_app', 'Unknown') }}</span></dd>
			</div>
			<div class="rd-contact-summary__row">
				<dt>{{ t('crmhub_nc_app', 'Lead source') }}</dt>
				<dd>{{ leadSource }}</dd>
			</div>
			<div class="rd-contact-summary__row">
				<dt>{{ t('crmhub_nc_app', 'Projects') }}</dt>
				<dd>{{ projectCount }} · {{ openProjectCount }} {{ t('crmhub_nc_app', 'open') }}</dd>
			</div>
			<div class="rd-contact-summary__row">
				<dt>{{ t('crmhub_nc_app', 'Notes') }}</dt>
				<dd>{{ notesCount }}</dd>
			</div>
			<div class="rd-contact-summary__row">
				<dt>{{ t('crmhub_nc_app', 'Recent activity') }}</dt>
				<dd>{{ activityCount }}</dd>
			</div>
			<div class="rd-contact-summary__row">
				<dt>{{ t('crmhub_nc_app', 'Last contacted') }}</dt>
				<dd>{{ formatDate(lastContactedAt) || t('crmhub_nc_app', 'No recent contact') }}</dd>
			</div>
			<div class="rd-contact-summary__row">
				<dt>{{ t('crmhub_nc_app', 'Linked folders') }}</dt>
				<dd>{{ linkedFolderCount }}</dd>
			</div>
			<div class="rd-contact-summary__row">
				<dt>{{ t('crmhub_nc_app', 'Deck cards') }}</dt>
				<dd>{{ linkedDeckCount }}</dd>
			</div>
			<div class="rd-contact-summary__row rd-contact-summary__row--tags" v-if="tags.length">
				<dt>{{ t('crmhub_nc_app', 'Tags') }}</dt>
				<dd>
					<span v-for="tag in tags" :key="tag" class="rd-chip rd-chip--tag">{{ tag }}</span>
				</dd>
			</div>
			<div class="rd-contact-summary__row" v-if="projectTypes.length">
				<dt>{{ t('crmhub_nc_app', 'Project types') }}</dt>
				<dd>{{ projectTypes.join(', ') }}</dd>
			</div>
			<div class="rd-contact-summary__row" v-if="assignedUsers.length">
				<dt>{{ t('crmhub_nc_app', 'Assigned to') }}</dt>
				<dd>{{ assignedUsers.join(', ') }}</dd>
			</div>
		</div>
	</SectionCard>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import SectionCard from './SectionCard.vue'

export default {
	name: 'ContactSummaryCard',
	components: { SectionCard },
	props: {
		contact: { type: Object, required: true },
		projects: { type: Array, default: () => [] },
		notesCount: { type: Number, default: 0 },
		activityCount: { type: Number, default: 0 },
		lastContactedAt: { type: Number, default: null },
	},
	computed: {
		leadSource() {
			if (this.contact.ghContactId) {
				return t('crmhub_nc_app', 'Groundhogg')
			}
			return t('crmhub_nc_app', 'CRM')
		},
		tags() {
			return Array.isArray(this.contact.ghTags) ? this.contact.ghTags : []
		},
		projectCount() {
			return this.projects.length
		},
		openProjectCount() {
			return this.projects.filter(p => p.status && !['archived', 'closed'].includes(p.status.toLowerCase())).length
		},
		linkedFolderCount() {
			return this.projects.filter(p => p.folderPath).length
		},
		linkedDeckCount() {
			return this.projects.filter(p => p.deckCardId).length
		},
		projectTypes() {
			return [...new Set(this.projects.map(p => p.projectType).filter(Boolean))]
		},
		assignedUsers() {
			return [...new Set(this.projects.map(p => p.assignedUserId).filter(Boolean))]
		},
	},
	methods: {
		t,
		formatDate(ts) {
			if (!ts) {
				return ''
			}
			return new Date(ts * 1000).toLocaleString()
		},
	},
}
</script>

<style lang="scss" scoped>
@use './_tokens.scss' as *;

.rd-contact-summary {
	display: grid;
	gap: $rd-space-3;
}

.rd-contact-summary__row {
	display: grid;
	grid-template-columns: 160px minmax(0, 1fr);
	gap: $rd-space-2;
	align-items: center;
	padding: $rd-space-2 0;
	border-bottom: 1px solid $rd-border-soft;

	&:last-child { border-bottom: none; }
}

.rd-contact-summary__row--tags {
	align-items: flex-start;
}

.rd-contact-summary dt {
	font-size: $rd-font-xs;
	font-weight: 600;
	text-transform: uppercase;
	color: $rd-text-muted;
}

.rd-contact-summary dd {
	margin: 0;
	font-size: $rd-font-md;
	color: $rd-text;
	display: flex;
	flex-wrap: wrap;
	gap: $rd-space-2;
}

.rd-chip {
	display: inline-flex;
	align-items: center;
	padding: 4px 10px;
	border-radius: 999px;
	font-size: $rd-font-xs;
	font-weight: 600;
	background: $rd-bg-page;
	color: $rd-text;
}

.rd-chip--status {
	background: rgba(59, 130, 246, 0.08);
	color: #1d4ed8;
}

.rd-chip--tag {
	background: rgba(16, 185, 129, 0.12);
	color: #047857;
}
</style>
