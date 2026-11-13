<template>
	<SectionCard :title="title" :count="entries.length">
		<template #icon><ClockOutlineIcon :size="18" /></template>
		<template v-if="$slots.actions" #actions><slot name="actions" /></template>

		<EmptyState v-if="!entries.length"
			:message="emptyMessage"
			:hint="emptyHint">
			<template #icon><ClockOutlineIcon :size="40" /></template>
		</EmptyState>

		<ol v-else class="rd-timeline">
			<li v-for="entry in entries" :key="entry.id" class="rd-timeline__item">
				<div class="rd-timeline__rail">
					<div class="rd-timeline__dot" :class="`rd-timeline__dot--${typeKey(entry.activityType)}`">
						<component :is="iconFor(entry.activityType)" :size="16" />
					</div>
				</div>
				<div class="rd-timeline__body">
					<div class="rd-timeline__head">
						<span class="rd-timeline__title">{{ entry.summary || labelFor(entry.activityType) }}</span>
						<span class="rd-timeline__time" :title="absoluteTime(entry.occurredAt)">
							{{ relativeTime(entry.occurredAt) }}
						</span>
					</div>
					<div class="rd-timeline__meta">
						<span class="rd-tag">{{ labelFor(entry.activityType) }}</span>
						<span class="rd-source" :class="`rd-source--${entry.source || 'nextcloud'}`">
							{{ entry.source || 'nextcloud' }}
						</span>
						<span v-if="showContact && entry.contactCacheId" class="rd-timeline__sub">
						· {{ entry.contactName || ('contact #' + entry.contactCacheId) }}
						</span>
					</div>
				</div>
			</li>
		</ol>
	</SectionCard>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import ClockOutlineIcon from 'vue-material-design-icons/ClockOutline.vue'
import NoteTextOutlineIcon from 'vue-material-design-icons/NoteTextOutline.vue'
import FolderPlusOutlineIcon from 'vue-material-design-icons/FolderPlusOutline.vue'
import EmailOutlineIcon from 'vue-material-design-icons/EmailOutline.vue'
import EmailFastOutlineIcon from 'vue-material-design-icons/EmailFastOutline.vue'
import CardAccountDetailsOutlineIcon from 'vue-material-design-icons/CardAccountDetailsOutline.vue'
import FileDocumentOutlineIcon from 'vue-material-design-icons/FileDocumentOutline.vue'
import FolderEditOutlineIcon from 'vue-material-design-icons/FolderEditOutline.vue'
import CardAccountMailOutlineIcon from 'vue-material-design-icons/CardAccountMailOutline.vue'
import AlertCircleOutlineIcon from 'vue-material-design-icons/AlertCircleOutline.vue'
import CalendarCheckOutlineIcon from 'vue-material-design-icons/CalendarCheckOutline.vue'
import AccountRemoveOutlineIcon from 'vue-material-design-icons/AccountRemoveOutline.vue'
import FolderCheckOutlineIcon from 'vue-material-design-icons/FolderCheckOutline.vue'
import AccountPlusOutlineIcon from 'vue-material-design-icons/AccountPlusOutline.vue'
import RefreshIcon from 'vue-material-design-icons/Refresh.vue'
import AccountMultipleOutlineIcon from 'vue-material-design-icons/AccountMultipleOutline.vue'
import TagOutlineIcon from 'vue-material-design-icons/TagOutline.vue'
import FilterOutlineIcon from 'vue-material-design-icons/FilterOutline.vue'
import SectionCard from './SectionCard.vue'
import EmptyState from './EmptyState.vue'

const TYPE_MAP = {
	note_added: { label: 'Note added', icon: NoteTextOutlineIcon, key: 'note' },
	project_created: { label: 'Project created', icon: FolderPlusOutlineIcon, key: 'project' },
	project_updated: { label: 'Project updated', icon: FolderEditOutlineIcon, key: 'project' },
	project_closed: { label: 'Project closed', icon: FolderCheckOutlineIcon, key: 'project' },
	contact_created: { label: 'Contact created', icon: CardAccountDetailsOutlineIcon, key: 'contact' },
	contact_updated: { label: 'Contact updated', icon: CardAccountMailOutlineIcon, key: 'contact' },
	contact_deleted: { label: 'Contact deleted', icon: AccountRemoveOutlineIcon, key: 'contact' },
	vcard_linked: { label: 'Contact linked from vCard', icon: CardAccountMailOutlineIcon, key: 'contact' },
	email_received: { label: 'Email received', icon: EmailOutlineIcon, key: 'email-in' },
	email_sent: { label: 'Email sent', icon: EmailFastOutlineIcon, key: 'email-out' },
	email_matched: { label: 'Email matched', icon: EmailOutlineIcon, key: 'email-in' },
	file_added: { label: 'File added', icon: FileDocumentOutlineIcon, key: 'file' },
	file_uploaded: { label: 'File uploaded', icon: FileDocumentOutlineIcon, key: 'file' },
	folder_renamed: { label: 'Folder renamed', icon: FolderEditOutlineIcon, key: 'project' },
	folder_deleted: { label: 'Folder deleted', icon: FolderEditOutlineIcon, key: 'project' },
	deck_update: { label: 'Deck card updated', icon: FolderEditOutlineIcon, key: 'project' },
	overdue: { label: 'Project overdue', icon: AlertCircleOutlineIcon, key: 'overdue' },
	calendar_event: { label: 'Calendar event created', icon: CalendarCheckOutlineIcon, key: 'calendar' },
	onboarding: { label: 'Client onboarding started', icon: AccountPlusOutlineIcon, key: 'contact' },
	sync: { label: 'Data synced', icon: RefreshIcon, key: 'sync' },
	record_merged: { label: 'Records merged', icon: AccountMultipleOutlineIcon, key: 'contact' },
	tag_changed: { label: 'Tag changed', icon: TagOutlineIcon, key: 'contact' },
	funnel_event: { label: 'Funnel event', icon: FilterOutlineIcon, key: 'project' },
}

export default {
	name: 'ActivityTimeline',
	components: { SectionCard, EmptyState, ClockOutlineIcon },
	props: {
		entries: { type: Array, default: () => [] },
		title: { type: String, default: () => t('crmhub_nc_app', 'Activity timeline') },
		emptyMessage: { type: String, default: () => t('crmhub_nc_app', 'No activity found') },
		emptyHint: { type: String, default: () => t('crmhub_nc_app', 'Actions on this contact will appear here.') },
		showContact: { type: Boolean, default: false },
	},
	methods: {
		t,
		typeKey(type) { return (TYPE_MAP[type] && TYPE_MAP[type].key) || 'default' },
		iconFor(type) { return (TYPE_MAP[type] && TYPE_MAP[type].icon) || ClockOutlineIcon },
		labelFor(type) {
			if (TYPE_MAP[type]) return t('crmhub_nc_app', TYPE_MAP[type].label)
			return type ? type.replace(/_/g, ' ') : t('crmhub_nc_app', 'Activity')
		},
		absoluteTime(ts) { return ts ? new Date(ts * 1000).toLocaleString() : '' },
		relativeTime(ts) {
			if (!ts) return ''
			const diff = Math.floor(Date.now() / 1000 - ts)
			if (diff < 60) return t('crmhub_nc_app', 'just now')
			if (diff < 3600) return Math.floor(diff / 60) + 'm'
			if (diff < 86400) return Math.floor(diff / 3600) + 'h'
			if (diff < 604800) return Math.floor(diff / 86400) + 'd'
			return new Date(ts * 1000).toLocaleDateString()
		},
	},
}
</script>

<style lang="scss" scoped>
@use './_tokens.scss' as *;

.rd-timeline {
	list-style: none;
	margin: 0;
	padding: 0;
	position: relative;
	max-height: 400px;
	min-height: 200px;
	overflow-y: auto;
	/* Add some padding to the right for the scrollbar */
	padding-right: 8px;

	&__item {
		display: flex;
		gap: $rd-space-3;
		position: relative;
		padding: $rd-space-2 0;

		&:not(:last-child) .rd-timeline__rail::after {
			content: '';
			position: absolute;
			left: 50%;
			top: 30px;
			bottom: -8px;
			width: 2px;
			background: $rd-border;
			transform: translateX(-50%);
		}
	}

	&__rail {
		position: relative;
		width: 28px;
		flex-shrink: 0;
		display: flex;
		justify-content: center;
		padding-top: 4px;
	}

	&__dot {
		width: 28px;
		height: 28px;
		border-radius: 50%;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		background: $rd-bg-page;
		color: $rd-primary;
		border: 2px solid $rd-bg-card;
		box-shadow: 0 0 0 1px $rd-border;
		z-index: 1;

		:deep(.material-design-icon) { display: inline-flex; }

		&--note      { color: #6366f1; }
		&--project   { color: #0ea5e9; }
		&--contact   { color: #ec4899; }
		&--email-in  { color: #10b981; }
		&--email-out { color: #f59e0b; }
		&--file      { color: #8b5cf6; }
		&--overdue   { color: #ef4444; }
		&--calendar  { color: #3b82f6; }
		&--sync      { color: #6b7280; }
		&--default   { color: $rd-text-muted; }
	}

	&__body {
		flex: 1;
		min-width: 0;
		padding-bottom: $rd-space-3;
	}

	&__head {
		display: flex;
		justify-content: space-between;
		align-items: baseline;
		gap: $rd-space-2;
	}

	&__title {
		font-size: $rd-font-md;
		font-weight: 500;
		color: $rd-text;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	&__time {
		font-size: $rd-font-xs;
		color: $rd-text-muted;
		flex-shrink: 0;
	}

	&__meta {
		margin-top: 4px;
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: $rd-space-2;
	}

	&__sub {
		font-size: $rd-font-xs;
		color: $rd-text-muted;
	}
}

.rd-tag {
	font-size: $rd-font-xs;
	padding: 1px 8px;
	border-radius: 999px;
	background: $rd-bg-page;
	color: $rd-text-muted;
	text-transform: capitalize;
}

.rd-source {
	font-size: $rd-font-xs;
	padding: 1px 8px;
	border-radius: 999px;
	text-transform: capitalize;
	font-weight: 500;

	&--nextcloud  { background: rgba(0, 130, 201, 0.10); color: #0369a1; }
	&--manual     { background: rgba(99, 102, 241, 0.10); color: #4338ca; }
	&--groundhogg { background: rgba(245, 158, 11, 0.12); color: #b45309; }
}
</style>
