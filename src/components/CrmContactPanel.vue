<template>
	<div class="crm-panel">

		<!-- ══ HEADER ══════════════════════════════════════════════ -->
		<div class="crm-panel__header">
			<div class="crm-panel__header-left">
				<span class="crm-panel__logo" aria-hidden="true">
					<LightningBoltIcon title="" />
				</span>
				<div class="crm-panel__header-info">
					<span class="crm-panel__header-label">CRM</span>
					<span v-if="contact" class="crm-panel__header-name">{{ displayName }}</span>
				</div>
			</div>
			<div class="crm-panel__header-right">
				<span
					v-if="contact && stageLabel"
					class="crm-panel__stage-badge"
					:class="`crm-panel__stage-badge--${contact.pipelineStage || contact.status}`"
				>{{ stageLabel }}</span>
				<a
					v-if="contact"
					:href="crmDeepLink"
					class="crm-panel__open-link"
					target="_blank"
				>{{ t('crmhub_nc_app', 'Open CRM') }} ↗</a>
			</div>
		</div>

		<!-- ══ LOADING ══════════════════════════════════════════════ -->
		<div v-if="loading" class="crm-panel__loading">
			<div class="crm-panel__skel crm-panel__skel--wide" />
			<div class="crm-panel__skel crm-panel__skel--half" />
			<div class="crm-panel__skel crm-panel__skel--third" />
		</div>

		<!-- ══ NOT LINKED ═══════════════════════════════════════════ -->
		<div v-else-if="!contact" class="crm-panel__unlinked">
			<div class="crm-panel__unlinked-icon">
				<AccountAlertOutlineIcon title="" :size="32" />
			</div>
			<p class="crm-panel__unlinked-title">{{ t('crmhub_nc_app', 'No CRM record yet') }}</p>
			<p class="crm-panel__unlinked-hint">{{ t('crmhub_nc_app', 'Create a CRM record to track notes, projects, and activity for this contact.') }}</p>
			<button class="crm-panel__create-btn" :disabled="creating" @click="createCrmRecord">
				{{ creating ? t('crmhub_nc_app', 'Creating…') : t('crmhub_nc_app', '+ Create CRM Record') }}
			</button>
		</div>

		<!-- ══ FULL PANEL ════════════════════════════════════════════ -->
		<template v-else>

			<!-- CRM Summary Card (Identical to ContactDetail.vue) -->
			<ContactSummaryCard
				class="crm-panel__summary-card"
				:contact="contact"
				:projects="projects"
				:notes-count="notes.length"
				:activity-count="activity.length"
				:last-contacted-at="lastActivityTs"
			/>

			<div class="rd-grid crm-panel__grid">
				<div class="rd-col rd-col--left">
					<!-- CRM Information -->
					<div class="rd-card">
						<div class="rd-card__header">
							<LabelOutlineIcon class="rd-card__icon" />
							<h3 class="rd-card__title">{{ t('crmhub_nc_app', 'CRM Information') }}</h3>
						</div>
						<div class="rd-card__body">
							<CrmInfoPanel
								:contact="contact"
								:last-activity-at="lastActivityTs"
								@updated="refreshContact"
							/>
						</div>
					</div>

					<NotesCard :notes="notes" @add-note="addNote" />
				</div>

				<div class="rd-col rd-col--right">
					<ProjectsCard :projects="projects" @new-project="openModal('folder')" />

					<!-- Tasks & Reminders -->
					<div class="rd-card">
						<div class="rd-card__header">
							<CheckAllIcon class="rd-card__icon" />
							<h3 class="rd-card__title">
								{{ t('crmhub_nc_app', 'Tasks & Reminders') }}
								<span v-if="overdueTasks > 0 || pendingTasks > 0" class="rd-badge" :class="{ 'rd-badge--warn': overdueTasks > 0 }">
									{{ overdueTasks > 0 ? overdueTasks : pendingTasks }}
								</span>
							</h3>
						</div>
						<div class="rd-card__body rd-card__body--no-pad">
							<TasksPanel
								:contact-id="contact.id"
								:refresh-key="tasksRefreshKey"
								@tasks-loaded="onTasksLoaded"
							/>
						</div>
					</div>

					<ContactDeckCard v-if="deckProjects.length" :projects="deckProjects" />
					
					<ActivityTimeline :entries="activity" />
					<div v-if="activity.length >= activityLimit" class="crm-panel__load-more">
						<button class="crm-panel__load-btn" @click="loadMoreActivity">{{ t('crmhub_nc_app', 'Load more…') }}</button>
					</div>
					
					<ContactFilesCard :projects="projects" />

					<!-- Quick Actions (Note, Task, Activity, Folder, Stage buttons + modals) -->
					<QuickActionsBar
						v-if="contact"
						:contact-id="contact.id"
						:contact-name="displayName"
						:forced-modal="pendingModal"
						@modal-closed="pendingModal = null"
						@note-added="onNoteAdded"
						@task-added="tasksRefreshKey++"
						@meeting-scheduled="refreshActivity"
						@stage-moved="refreshContact"
						@folder-created="onProjectCreated"
					/>
				</div>
			</div>

		</template>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import api from '../api.js'

import QuickActionsBar from './panels/QuickActionsBar.vue'
import CrmInfoPanel    from './panels/CrmInfoPanel.vue'
import TasksPanel      from './panels/TasksPanel.vue'
import NotesCard       from './NotesCard.vue'
import ProjectsCard    from './ProjectsCard.vue'
import ContactFilesCard from './ContactFilesCard.vue'
import ContactDeckCard  from './ContactDeckCard.vue'
import ActivityTimeline from './ActivityTimeline.vue'
import ContactSummaryCard from './ContactSummaryCard.vue'

import LightningBoltIcon from 'vue-material-design-icons/LightningBolt.vue'
import AccountAlertOutlineIcon from 'vue-material-design-icons/AccountAlertOutline.vue'
import NotePlusOutlineIcon from 'vue-material-design-icons/NotePlusOutline.vue'
import CheckCircleOutlineIcon from 'vue-material-design-icons/CheckCircleOutline.vue'
import PhoneOutlineIcon from 'vue-material-design-icons/PhoneOutline.vue'
import FolderPlusOutlineIcon from 'vue-material-design-icons/FolderPlusOutline.vue'
import TargetIcon from 'vue-material-design-icons/Target.vue'
import LabelOutlineIcon from 'vue-material-design-icons/LabelOutline.vue'
import NoteOutlineIcon from 'vue-material-design-icons/NoteOutline.vue'
import ClockOutlineIcon from 'vue-material-design-icons/ClockOutline.vue'
import FolderOutlineIcon from 'vue-material-design-icons/FolderOutline.vue'
import CheckAllIcon from 'vue-material-design-icons/CheckAll.vue'
import FileDocumentOutlineIcon from 'vue-material-design-icons/FileDocumentOutline.vue'
import ViewDashboardOutlineIcon from 'vue-material-design-icons/ViewDashboardOutline.vue'

const ACTIVITY_LABELS = {
	note_added:       'Note added',
	project_created:  'Project created',
	project_updated:  'Project updated',
	contact_created:  'Contact created',
	contact_updated:  'Contact updated',
	email_received:   'Email received',
	email_sent:       'Email sent',
	file_added:       'File added',
	calendar_event:   'Calendar event',
	tag_changed:      'Tag changed',
	vcard_linked:     'Contact linked',
	call:             'Phone call',
	meeting:          'Meeting',
	email:            'Email',
}

const ACTIVITY_COLORS = {
	note:     'note',
	project:  'project',
	contact:  'contact',
	'email-in':  'email-in',
	'email-out': 'email-out',
	file:     'file',
	calendar: 'calendar',
	call:     'call',
	meeting:  'meeting',
}

export default {
	name: 'CrmContactPanel',

	components: {
		QuickActionsBar,
		CrmInfoPanel,
		TasksPanel,
		NotesCard,
		ProjectsCard,
		ContactFilesCard,
		ContactDeckCard,
		ActivityTimeline,
		ContactSummaryCard,
		LightningBoltIcon, AccountAlertOutlineIcon, NotePlusOutlineIcon,
		CheckCircleOutlineIcon, PhoneOutlineIcon, FolderPlusOutlineIcon,
		TargetIcon, LabelOutlineIcon, CheckAllIcon,
	},

	props: {
		email:     { type: String, default: null },
		vcardUid:  { type: String, default: null },
		firstName: { type: String, default: null },
		lastName:  { type: String, default: null },
	},

	data() {
		return {
			loading:         true,
			creating:        false,
			contact:         null,
			projects:        [],
			notes:           [],
			activity:        [],
			activityLimit:   20,
			pendingTasks:    0,
			overdueTasks:    0,
			tasksRefreshKey: 0,
			pendingModal:    null,
		}
	},

	computed: {
		displayName() {
			if (!this.contact) return ''
			return [this.contact.firstName, this.contact.lastName]
				.filter(Boolean).join(' ') || this.contact.email || ''
		},
		stageLabel() {
			const s = this.contact?.pipelineStage || this.contact?.status || ''
			return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''
		},
		crmDeepLink() {
			return generateUrl('/apps/crmhub_nc_app') + '#/contacts/' + this.contact?.id
		},
		deckProjects() {
			return this.projects.filter(p => p.deckCardId || p.deckBoardId)
		},
		lastActivityTs() {
			return this.activity.length ? Number(this.activity[0]?.occurredAt || 0) : null
		},
		healthScore() {
			if (this.contact?.healthScore != null) return Math.round(this.contact.healthScore)
			const days = this.lastActivityTs
				? Math.floor((Date.now() / 1000 - this.lastActivityTs) / 86400)
				: 999
			if (days <= 7)  return 90
			if (days <= 30) return 65
			if (days <= 90) return 35
			return 15
		},
		healthColor() {
			const s = this.healthScore
			return s >= 70 ? '#10b981' : s >= 40 ? '#f59e0b' : '#ef4444'
		},
		lastActivityLabel() {
			if (!this.lastActivityTs) return '—'
			const mins = Math.floor((Date.now() / 1000 - this.lastActivityTs) / 60)
			if (mins < 1)  return t('crmhub_nc_app', 'Just now')
			if (mins < 60) return mins + 'm'
			const hrs = Math.floor(mins / 60)
			if (hrs < 24)  return hrs + 'h'
			const days = Math.floor(hrs / 24)
			return days < 30 ? days + 'd' : Math.floor(days / 30) + 'mo'
		},
	},

	async created() { await this.loadContact() },

	watch: {
		// Reload whenever the contact identifier prop changes — this handles the
		// case where contacts-integration.js remounts with new props without fully
		// destroying the component (e.g. during the async email-poll re-attach).
		email(v, old)    { if (v !== old) this.loadContact() },
		vcardUid(v, old) { if (v !== old) this.loadContact() },
	},

	methods: {
		t,

		openModal(type) { this.pendingModal = type },

		async loadContact() {
			this.loading  = true
			// Clear previous contact's data immediately so stale records never
			// appear while the new contact is being fetched.
			this.contact  = null
			this.projects = []
			this.notes    = []
			this.activity = []
			try {
				let contact = null
				if (this.vcardUid) {
					contact = await api.contacts.byVcardUid(this.vcardUid).catch(() => null)
					if (contact?.error) contact = null
				}
				if (!contact && this.email) {
					contact = await api.contacts.byEmail(this.email).catch(() => null)
					if (contact?.error) contact = null
				}
				if (!contact && (this.email || this.vcardUid)) {
					const res = await axios.get(
						generateUrl('/apps/crmhub_nc_app/api/contacts-integration/summary'),
						{ params: { email: this.email || '', vcardUid: this.vcardUid || '' } }
					).catch(() => null)
					if (res?.data && !res.data.error) contact = res.data
				}
				this.contact = contact || null
				if (this.contact?.id) await this.loadAllData()
			} catch (e) {
				console.error('[Reidel CRM] loadContact failed', e)
				this.contact = null
			} finally {
				this.loading = false
			}
		},

		async loadAllData() {
			const cid = this.contact.id
			;[this.projects, this.notes, this.activity] = await Promise.all([
				api.projects.forContact(cid).catch(() => []),
				api.notes.forContact(cid).catch(() => []),
				api.contacts.activity(cid, this.activityLimit).catch(() => []),
			])
		},

		async refreshContact() {
			if (!this.contact?.id) return
			try { const c = await api.contacts.get(this.contact.id); if (c) this.contact = c } catch (_) {}
		},
		async refreshProjects() {
			if (!this.contact?.id) return
			try { this.projects = await api.projects.forContact(this.contact.id) } catch (_) {}
		},
		async refreshNotes() {
			if (!this.contact?.id) return
			try { this.notes = await api.notes.forContact(this.contact.id) } catch (_) {}
		},
		async refreshActivity() {
			if (!this.contact?.id) return
			try { this.activity = await api.contacts.activity(this.contact.id, this.activityLimit) } catch (_) {}
		},
		async loadMoreActivity() {
			this.activityLimit += 20
			await this.refreshActivity()
		},

		onTasksLoaded({ pending, overdue }) {
			this.pendingTasks = pending
			this.overdueTasks = overdue
		},

		async addNote(content) {
			if (!this.contact?.id || !content.trim()) return
			try {
				await api.notes.create({ contactCacheId: this.contact.id, content })
				;[this.notes, this.activity] = await Promise.all([
					api.notes.forContact(this.contact.id),
					api.contacts.activity(this.contact.id, this.activityLimit),
				])
				showSuccess(t('crmhub_nc_app', 'Note saved'))
			} catch { showError(t('crmhub_nc_app', 'Failed to save note')) }
		},

		async onNoteAdded() {
			await Promise.all([this.refreshNotes(), this.refreshActivity()])
		},

		async onProjectCreated() {
			await Promise.all([this.refreshProjects(), this.refreshActivity()])
		},

		async createCrmRecord() {
			if (!this.email && !this.vcardUid) return
			this.creating = true
			try {
				const data = await api.contacts.create({
					status: 'lead', email: this.email || '',
					vcardUid: this.vcardUid || '',
					firstName: this.firstName || '', lastName: this.lastName || '',
				})
				if (!data?.id) { showError(t('crmhub_nc_app', 'Unexpected response')); return }
				if (this.vcardUid) await api.contacts.linkVcard({ vcardUid: this.vcardUid, contactId: data.id }).catch(() => null)
				showSuccess(t('crmhub_nc_app', '✓ CRM record created'))
				await this.loadContact()
			} catch (e) {
				const s = e?.response?.status
				if (s === 409) { showError(t('crmhub_nc_app', 'Record already exists')); await this.loadContact() }
				else if (s === 422) showError(t('crmhub_nc_app', 'Please add an email address first'))
			} finally { this.creating = false }
		},
	},
}
</script>

<style lang="scss" scoped>
@use './_tokens.scss' as *;

.crm-panel__grid {
	display: grid;
	grid-template-columns: minmax(0, 5fr) minmax(0, 7fr);
	gap: $rd-space-4;
	padding: 0 20px 20px;
}

.rd-col { min-width: 0; display: flex; flex-direction: column; gap: 16px; }

@media (max-width: 960px) {
	.crm-panel__grid { grid-template-columns: 1fr; }
}

.rd-card {
	background: var(--color-main-background, #fff);
	border: 1px solid var(--color-border, #e5e7eb);
	border-radius: $rd-radius-lg;
	box-shadow: 0 2px 4px rgba(0,0,0,0.02);
	overflow: hidden;
	display: flex;
	flex-direction: column;
}

.rd-card__header {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 16px 20px;
	border-bottom: 1px solid var(--color-border, #f3f4f6);
	background: var(--color-background-hover, #f9fafb);
}

.rd-card__icon {
	color: var(--color-text-maxcontrast, #4b5563);
}

.rd-card__title {
	font-size: 14px;
	font-weight: 600;
	margin: 0;
	color: var(--color-main-text, #111827);
	display: flex;
	align-items: center;
	gap: 8px;
}

.rd-badge {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 20px;
	height: 20px;
	padding: 0 6px;
	border-radius: 10px;
	font-size: 11px;
	font-weight: 700;
	background: var(--color-background-dark, #f0f0f0);
	color: var(--color-text-lighter, #6b7280);
}

.rd-badge--warn { background: rgba(239,68,68,0.12); color: #dc2626; }

.rd-card__body {
	padding: 20px;
}
.rd-card__body--no-pad {
	padding: 0;
}

.crm-panel {
	border-top: 3px solid $rd-primary;
	background: var(--color-main-background, #fff);
	font-family: var(--font-face, system-ui, sans-serif);
	font-size: 13px;
	color: var(--color-main-text, #1f2937);
}

/* ── Header ─────────────────────────────────────────── */
.crm-panel__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 16px 20px;
	background: var(--color-main-background, #fff);
	border-bottom: 1px solid var(--color-border, #e9e9e9);
	box-shadow: 0 2px 8px rgba(0,0,0,0.02);
	color: var(--color-main-text, #1f2937);
}

.crm-panel__header-left {
	display: flex;
	align-items: center;
	gap: 10px;
	min-width: 0;
}

.crm-panel__logo { 
	display: flex; 
	align-items: center;
	justify-content: center;
	width: 38px;
	height: 38px;
	border-radius: 10px;
	background: $rd-primary;
	background: linear-gradient(135deg, $rd-primary, var(--color-primary-element, $rd-primary));
	font-size: 22px; 
	flex-shrink: 0; 
	color: #fff;
	box-shadow: 0 4px 12px rgba($rd-primary, 0.25);
}

.crm-panel__header-info {
	display: flex;
	flex-direction: column;
	gap: 1px;
	min-width: 0;
}

.crm-panel__header-label {
	font-size: 10px;
	font-weight: 700;
	letter-spacing: 1.5px;
	text-transform: uppercase;
	color: var(--color-text-lighter, #6b7280);
	line-height: 1;
}

.crm-panel__header-name {
	font-size: 16px;
	font-weight: 700;
	line-height: 1.2;
	color: var(--color-main-text, #111827);
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.crm-panel__header-right {
	display: flex;
	align-items: center;
	gap: 12px;
	flex-shrink: 0;
}

.crm-panel__stage-badge {
	font-size: 11px;
	font-weight: 700;
	padding: 4px 12px;
	border-radius: 999px;
	background: rgba($rd-primary, 0.1);
	color: $rd-primary;
	text-transform: capitalize;
	white-space: nowrap;
	border: 1px solid rgba($rd-primary, 0.2);

	&--won    { background: rgba(16,185,129,0.1); color: #059669; border-color: rgba(16,185,129,0.2); }
	&--lost   { background: rgba(239,68,68,0.1); color: #dc2626; border-color: rgba(239,68,68,0.2); }
}

.crm-panel__open-link {
	font-size: 12px;
	font-weight: 600;
	color: var(--color-text-lighter, #4b5563);
	text-decoration: none;
	padding: 6px 14px;
	border-radius: 8px;
	border: 1px solid var(--color-border, #e5e7eb);
	background: var(--color-main-background, #fff);
	white-space: nowrap;
	transition: all 0.2s ease;
	box-shadow: 0 1px 3px rgba(0,0,0,0.05);
	display: flex;
	align-items: center;
	gap: 4px;
	
	&:hover { 
		background: var(--color-background-hover, #f9fafb); 
		color: var(--color-main-text, #111827); 
		border-color: #d1d5db;
		box-shadow: 0 2px 4px rgba(0,0,0,0.08);
	}
}

/* ── KPI strip ──────────────────────────────────────── */
.crm-panel__kpi {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	background: var(--color-main-background, #fff);
	border-bottom: 1px solid var(--color-border, #e9e9e9);
	padding: 12px 16px;
	gap: 12px;
}

.crm-panel__kpi-item {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 6px;
	padding: 12px 8px;
	text-align: center;
	background: var(--color-background-dark, #f8f9fa);
	border-radius: 12px;
	border: 1px solid var(--color-border, #f3f4f6);
	transition: transform 0.2s, box-shadow 0.2s;
	
	&:hover {
		transform: translateY(-2px);
		box-shadow: 0 4px 12px rgba(0,0,0,0.05);
	}
}

.crm-panel__kpi-val {
	font-size: 17px;
	font-weight: 700;
	line-height: 1;
	color: var(--color-main-text, #1f2937);
}

.crm-panel__kpi-status {
	font-size: 11px;
	font-weight: 600;
	text-transform: capitalize;

	&--active   { color: #10b981; }
	&--lead, &--prospect { color: #6366f1; }
	&--inactive, &--lost, &--archived { color: #9ca3af; }
}

.crm-panel__kpi-bar {
	width: 80%;
	height: 4px;
	background: var(--color-border, #e5e7eb);
	border-radius: 4px;
	overflow: hidden;
	margin-top: 2px;
}

.crm-panel__kpi-fill {
	height: 100%;
	border-radius: 4px;
	transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
	background: $rd-primary;
	background: linear-gradient(90deg, $rd-primary, var(--color-primary-element, $rd-primary));
}

.crm-panel__kpi-label {
	font-size: 9px;
	text-transform: uppercase;
	letter-spacing: 0.6px;
	color: var(--color-text-lighter, #9ca3af);
	font-weight: 700;
}

/* ── Action chips ───────────────────────────────────── */
.crm-panel__actions {
	display: flex;
	flex-wrap: wrap;
	gap: 10px;
	padding: 16px 20px;
	border-bottom: 1px solid var(--color-border, #e9e9e9);
	background: var(--color-main-background, #fff);
}

.crm-panel__chip {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	padding: 8px 16px;
	background: var(--color-background-dark, #f8f9fa);
	border: 1px solid var(--color-border, #e5e7eb);
	border-radius: 999px;
	font-size: 13px;
	font-weight: 600;
	color: var(--color-main-text, #374151);
	cursor: pointer;
	white-space: nowrap;
	transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
	
	.material-design-icon {
		display: flex;
		align-items: center;
		color: $rd-primary;
		transition: color 0.2s;
	}

	&:hover {
		background: var(--color-main-background, #fff);
		border-color: rgba($rd-primary, 0.4);
		color: $rd-primary;
		box-shadow: 0 4px 12px rgba(0,0,0,0.06), 0 0 0 1px rgba($rd-primary, 0.1);
		transform: translateY(-1px);
	}
	
	&:active {
		transform: translateY(0);
	}
}

/* ── Badge (shared — used in KPI strip and section badges) ── */
.crm-panel__badge {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 20px;
	height: 20px;
	padding: 0 6px;
	border-radius: 10px;
	font-size: 11px;
	font-weight: 700;
	background: var(--color-background-dark, #f0f0f0);
	color: var(--color-text-lighter, #6b7280);
}

/* ── Inline activity timeline ───────────────────────── */
.crm-timeline {
	list-style: none;
	margin: 0;
	padding: 0;
}

.crm-timeline__item {
	display: flex;
	align-items: flex-start;
	gap: 10px;
	padding: 7px 0;
	border-bottom: 1px solid var(--color-border, #f3f4f6);
	&:last-child { border: none; }
}

.crm-panel__summary-card {
	margin: 0 20px 20px;
}

.crm-timeline__dot {
	width: 8px;
	height: 8px;
	border-radius: 50%;
	flex-shrink: 0;
	margin-top: 5px;
	background: var(--color-border, #d1d5db);

	&--note       { background: #6366f1; }
	&--project    { background: #0ea5e9; }
	&--contact    { background: #ec4899; }
	&--email-in   { background: #10b981; }
	&--email-out  { background: #f59e0b; }
	&--file       { background: #8b5cf6; }
	&--calendar   { background: #3b82f6; }
	&--call       { background: #10b981; }
	&--meeting    { background: #f59e0b; }
}

.crm-timeline__body {
	flex: 1;
	min-width: 0;
}

.crm-timeline__title {
	display: block;
	font-size: 13px;
	color: var(--color-main-text, #1f2937);
	font-weight: 500;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.crm-timeline__meta {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-top: 2px;
}

.crm-timeline__type {
	font-size: 11px;
	color: var(--color-text-lighter, #9ca3af);
	text-transform: capitalize;
}

.crm-timeline__time {
	font-size: 11px;
	color: var(--color-text-lighter, #9ca3af);
	margin-left: auto;
	flex-shrink: 0;
}

/* ── Empty state / load more ────────────────────────── */
.crm-panel__empty-state {
	padding: 12px 0 4px;
}

.crm-panel__empty-text {
	margin: 0;
	font-size: 13px;
	font-weight: 500;
	color: var(--color-main-text, #374151);
}

.crm-panel__empty-hint {
	margin: 4px 0 0;
	font-size: 12px;
	color: var(--color-text-lighter, #9ca3af);
}

.crm-panel__load-more { display: flex; justify-content: center; padding: 10px 0 0; }
.crm-panel__load-btn {
	background: none;
	border: none;
	color: $rd-primary;
	cursor: pointer;
	font-size: 12px;
	text-decoration: underline;
	padding: 4px 8px;
	&:hover { opacity: 0.8; }
}

/* ── Unlinked / loading states ──────────────────────── */
.crm-panel__unlinked {
	padding: 28px 20px;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 10px;
	text-align: center;
	background: var(--color-background-dark, #f8f9fa);
}

.crm-panel__unlinked-icon { 
	display: flex;
	align-items: center;
	justify-content: center;
	color: var(--color-text-lighter, #9ca3af);
}
.crm-panel__unlinked-title { margin: 0; font-size: 15px; font-weight: 600; }
.crm-panel__unlinked-hint  { margin: 0; font-size: 13px; color: var(--color-text-lighter, #6b7280); line-height: 1.5; max-width: 300px; }

.crm-panel__create-btn {
	padding: 9px 20px;
	background: $rd-primary;
	color: #fff;
	border: none;
	border-radius: $rd-radius-sm;
	font-size: 13px;
	font-weight: 600;
	cursor: pointer;
	margin-top: 4px;
	transition: opacity 0.15s;
	&:hover:not(:disabled) { opacity: 0.85; }
	&:disabled { opacity: 0.5; cursor: not-allowed; }
}

.crm-panel__loading { padding: 20px 16px; display: flex; flex-direction: column; gap: 10px; }

.crm-panel__skel {
	height: 14px;
	border-radius: 6px;
	background: linear-gradient(90deg, var(--color-background-dark, #f0f0f0) 25%, var(--color-background-hover, #e0e0e0) 50%, var(--color-background-dark, #f0f0f0) 75%);
	background-size: 200% 100%;
	animation: crm-panel-shimmer 1.4s ease-in-out infinite;
	&--wide  { width: 100%; }
	&--half  { width: 60%; }
	&--third { width: 38%; }
}

@keyframes crm-panel-shimmer {
	0%   { background-position: 200% 0; }
	100% { background-position: -200% 0; }
}

/* ── Responsive ──────────────────────────────────────── */
@media (max-width: 460px) {
	.crm-panel__kpi { grid-template-columns: repeat(2, 1fr); }
}

/* ── Dark mode ───────────────────────────────────────── */
@media (prefers-color-scheme: dark) {
	.crm-panel__kpi      { background: rgba(255,255,255,0.04); }
	.crm-panel__unlinked { background: rgba(255,255,255,0.04); }
	.crm-panel__chip     { background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.12); color: #e5e7eb; }
}
</style>
