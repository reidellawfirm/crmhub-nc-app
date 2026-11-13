<template>
	<!-- When forcedModal is used by parent, this renders only the modals (no button row) -->
	<div :class="forcedModal ? 'crm-qab--modals-only' : 'crm-quick-actions'">
		<!-- Button row — hidden when parent drives modals via forcedModal prop -->
		<template v-if="!forcedModal">
			<NcButton type="secondary" class="crm-quick-actions__btn" @click="showNoteModal = true">
				<template #icon><NoteTextOutlineIcon :size="16" /></template>
				{{ t('crmhub_nc_app', 'Note') }}
			</NcButton>

			<NcButton type="secondary" class="crm-quick-actions__btn" @click="showTaskModal = true">
				<template #icon><CheckboxMarkedOutlineIcon :size="16" /></template>
				{{ t('crmhub_nc_app', 'Task') }}
			</NcButton>

			<NcButton type="secondary" class="crm-quick-actions__btn" @click="showActivityModal = true">
				<template #icon><PhoneOutlineIcon :size="16" /></template>
				{{ t('crmhub_nc_app', 'Activity') }}
			</NcButton>

			<NcButton type="secondary" class="crm-quick-actions__btn" @click="showFolderModal = true">
				<template #icon><FolderPlusOutlineIcon :size="16" /></template>
				{{ t('crmhub_nc_app', 'Folder') }}
			</NcButton>

			<NcButton type="secondary" class="crm-quick-actions__btn" @click="showStageModal = true">
				<template #icon><ChevronRightIcon :size="16" /></template>
				{{ t('crmhub_nc_app', 'Stage') }}
			</NcButton>
		</template>

		<!-- ── ADD NOTE ── -->
		<NcModal v-if="showNoteModal" size="small" @close="closeNote">
			<template #header>{{ t('crmhub_nc_app', 'Add Note') }}</template>
			<div class="crm-modal-body">
				<NcTextArea
					v-model="noteContent"
					:label="t('crmhub_nc_app', 'Note')"
					:placeholder="t('crmhub_nc_app', 'Write a note about this contact…')"
					:rows="4"
					autofocus
				/>
				<div class="crm-modal-footer">
					<NcButton type="tertiary" @click="closeNote">{{ t('crmhub_nc_app', 'Cancel') }}</NcButton>
					<NcButton type="primary" :disabled="!noteContent.trim() || saving" @click="saveNote">
						<template #icon><ContentSaveOutlineIcon :size="16" /></template>
						{{ saving ? t('crmhub_nc_app', 'Saving…') : t('crmhub_nc_app', 'Save Note') }}
					</NcButton>
				</div>
			</div>
		</NcModal>

		<!-- ── CREATE TASK ── -->
		<NcModal v-if="showTaskModal" size="small" @close="closeTask">
			<template #header>{{ t('crmhub_nc_app', 'Create Task') }}</template>
			<div class="crm-modal-body">
				<div class="crm-field">
					<label class="crm-field__label">{{ t('crmhub_nc_app', 'Title') }} <span class="crm-field__required">*</span></label>
					<NcTextField
						v-model="task.title"
						:placeholder="t('crmhub_nc_app', 'e.g. Follow up on proposal')"
					/>
				</div>
				<div class="crm-field">
					<label class="crm-field__label">{{ t('crmhub_nc_app', 'Due date') }}</label>
					<input
						v-model="task.dueAtStr"
						type="datetime-local"
						class="crm-date-input"
					/>
				</div>
				<div class="crm-field">
					<label class="crm-field__label">{{ t('crmhub_nc_app', 'Notes') }}</label>
					<NcTextArea
						v-model="task.notes"
						:rows="2"
						:placeholder="t('crmhub_nc_app', 'Optional task notes…')"
					/>
				</div>
				<div class="crm-modal-footer">
					<NcButton type="tertiary" @click="closeTask">{{ t('crmhub_nc_app', 'Cancel') }}</NcButton>
					<NcButton type="primary" :disabled="!task.title.trim() || saving" @click="saveTask">
						<template #icon><ContentSaveOutlineIcon :size="16" /></template>
						{{ saving ? t('crmhub_nc_app', 'Saving…') : t('crmhub_nc_app', 'Create Task') }}
					</NcButton>
				</div>
			</div>
		</NcModal>

		<!-- ── LOG ACTIVITY ── -->
		<NcModal v-if="showActivityModal" size="small" @close="closeActivity">
			<template #header>{{ t('crmhub_nc_app', 'Log Activity') }}</template>
			<div class="crm-modal-body">
				<div class="crm-field">
					<label class="crm-field__label">{{ t('crmhub_nc_app', 'Activity type') }} <span class="crm-field__required">*</span></label>
					<NcSelect
						v-model="activityType"
						:options="activityTypes"
						:placeholder="t('crmhub_nc_app', 'Select type')"
					/>
				</div>
				<div class="crm-field">
					<label class="crm-field__label">{{ t('crmhub_nc_app', 'Summary') }} <span class="crm-field__required">*</span></label>
					<NcTextField
						v-model="activitySummary"
						:placeholder="t('crmhub_nc_app', 'Brief description…')"
					/>
				</div>
				<div class="crm-modal-footer">
					<NcButton type="tertiary" @click="closeActivity">{{ t('crmhub_nc_app', 'Cancel') }}</NcButton>
					<NcButton type="primary" :disabled="!activityType || !activitySummary.trim() || saving" @click="saveActivity">
						<template #icon><ContentSaveOutlineIcon :size="16" /></template>
						{{ saving ? t('crmhub_nc_app', 'Saving…') : t('crmhub_nc_app', 'Log Activity') }}
					</NcButton>
				</div>
			</div>
		</NcModal>

		<!-- ── CREATE FOLDER ── -->
		<NcModal v-if="showFolderModal" size="small" @close="closeFolder">
			<template #header>{{ t('crmhub_nc_app', 'Create Contact Folder') }}</template>
			<div class="crm-modal-body">
				<p class="crm-modal-hint">
					{{ t('crmhub_nc_app', 'A folder will be created in Nextcloud Files to store documents for this contact.') }}
				</p>
				<div class="crm-field">
					<label class="crm-field__label">{{ t('crmhub_nc_app', 'Folder name') }} <span class="crm-field__required">*</span></label>
					<NcTextField
						v-model="folderName"
						:placeholder="t('crmhub_nc_app', 'Contact folder name')"
					/>
				</div>
				<div v-if="folderError" class="crm-modal-error">{{ folderError }}</div>
				<div v-if="folderSuccess" class="crm-modal-success">
					✓ {{ t('crmhub_nc_app', 'Folder created:') }} <code>{{ folderSuccess }}</code>
				</div>
				<div class="crm-modal-footer">
					<NcButton type="tertiary" @click="closeFolder">{{ t('crmhub_nc_app', 'Cancel') }}</NcButton>
					<NcButton type="primary" :disabled="!folderName.trim() || saving || !!folderSuccess" @click="createFolder">
						<template #icon><FolderPlusOutlineIcon :size="16" /></template>
						{{ saving ? t('crmhub_nc_app', 'Creating…') : t('crmhub_nc_app', 'Create Folder') }}
					</NcButton>
				</div>
			</div>
		</NcModal>

		<!-- ── MOVE STAGE ── -->
		<NcModal v-if="showStageModal" size="small" @close="closeStage">
			<template #header>{{ t('crmhub_nc_app', 'Move Pipeline Stage') }}</template>
			<div class="crm-modal-body">
				<div class="crm-field">
					<label class="crm-field__label">{{ t('crmhub_nc_app', 'New stage') }} <span class="crm-field__required">*</span></label>
					<NcSelect
						v-model="newStage"
						:options="pipelineStages"
						:placeholder="t('crmhub_nc_app', 'Select stage')"
					/>
				</div>
				<div class="crm-modal-footer">
					<NcButton type="tertiary" @click="closeStage">{{ t('crmhub_nc_app', 'Cancel') }}</NcButton>
					<NcButton type="primary" :disabled="!newStage || saving" @click="moveStage">
						<template #icon><ChevronRightIcon :size="16" /></template>
						{{ saving ? t('crmhub_nc_app', 'Saving…') : t('crmhub_nc_app', 'Update Stage') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { generateUrl, generateRemoteUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import { showError, showSuccess } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NoteTextOutlineIcon from 'vue-material-design-icons/NoteTextOutline.vue'
import CheckboxMarkedOutlineIcon from 'vue-material-design-icons/CheckboxMarkedOutline.vue'
import PhoneOutlineIcon from 'vue-material-design-icons/PhoneOutline.vue'
import FolderPlusOutlineIcon from 'vue-material-design-icons/FolderPlusOutline.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import ContentSaveOutlineIcon from 'vue-material-design-icons/ContentSaveOutline.vue'

export default {
	name: 'QuickActionsBar',
	components: {
		NcButton, NcModal, NcTextField, NcTextArea, NcSelect,
		NoteTextOutlineIcon, CheckboxMarkedOutlineIcon, PhoneOutlineIcon,
		FolderPlusOutlineIcon, ChevronRightIcon, ContentSaveOutlineIcon,
	},
	props: {
		contactId:   { type: Number, required: true },
		contactName: { type: String, default: '' },
		// Parent can open a specific modal by setting this prop (null = none)
		forcedModal: { type: String, default: null },
	},
	emits: ['note-added', 'task-added', 'meeting-scheduled', 'stage-moved', 'folder-created', 'modal-closed'],
	data() {
		return {
			saving: false,
			// Note
			showNoteModal:     false,
			noteContent:       '',
			// Task
			showTaskModal:     false,
			task:              { title: '', dueAtStr: '', notes: '' },
			// Activity
			showActivityModal: false,
			activityType:      null,
			activitySummary:   '',
			// Folder
			showFolderModal:   false,
			folderName:        '',
			folderError:       '',
			folderSuccess:     '',
			// Stage
			showStageModal:    false,
			newStage:          null,

			activityTypes: [
				{ id: 'call',    label: t('crmhub_nc_app', 'Phone call') },
				{ id: 'meeting', label: t('crmhub_nc_app', 'Meeting') },
				{ id: 'email',   label: t('crmhub_nc_app', 'Email') },
				{ id: 'note',    label: t('crmhub_nc_app', 'Note') },
			],
			pipelineStages: [
				{ id: 'lead',        label: t('crmhub_nc_app', 'Lead') },
				{ id: 'prospect',    label: t('crmhub_nc_app', 'Prospect') },
				{ id: 'qualified',   label: t('crmhub_nc_app', 'Qualified') },
				{ id: 'proposal',    label: t('crmhub_nc_app', 'Proposal') },
				{ id: 'negotiation', label: t('crmhub_nc_app', 'Negotiation') },
				{ id: 'won',         label: t('crmhub_nc_app', 'Won') },
				{ id: 'lost',        label: t('crmhub_nc_app', 'Lost') },
			],
		}
	},

	watch: {
		// Parent action chips drive modal opening via this prop
		forcedModal(val) {
			if (!val) return
			if (val === 'note')     this.showNoteModal     = true
			if (val === 'task')     this.showTaskModal     = true
			if (val === 'activity') this.showActivityModal = true
			if (val === 'folder')   this.showFolderModal   = true
			if (val === 'stage')    this.showStageModal    = true
		},
		// Emit modal-closed when all modals close so parent resets showModal
		showNoteModal(v)     { if (!v) this.$emit('modal-closed') },
		showTaskModal(v)     { if (!v) this.$emit('modal-closed') },
		showActivityModal(v) { if (!v) this.$emit('modal-closed') },
		showFolderModal(val) {
			if (!val) { this.$emit('modal-closed'); return }
			this.folderName    = this.contactName || ''
			this.folderError   = ''
			this.folderSuccess = ''
		},
		showStageModal(v)    { if (!v) this.$emit('modal-closed') },
	},

	methods: {
		t,

		// ── Note ──────────────────────────────────────────────────────────────
		closeNote() { this.showNoteModal = false; this.noteContent = '' },
		async saveNote() {
			if (!this.noteContent.trim() || this.saving) return
			this.saving = true
			try {
				await axios.post(generateUrl('/apps/crmhub_nc_app/api/notes'), {
					contactCacheId: this.contactId,
					content:        this.noteContent.trim(),
				})
				showSuccess(t('crmhub_nc_app', 'Note saved'))
				this.$emit('note-added')
				this.closeNote()
			} catch {
				showError(t('crmhub_nc_app', 'Failed to save note'))
			} finally {
				this.saving = false
			}
		},

		// ── Task ──────────────────────────────────────────────────────────────
		closeTask() { this.showTaskModal = false; this.task = { title: '', dueAtStr: '', notes: '' } },
		async saveTask() {
			if (!this.task.title.trim() || this.saving) return
			this.saving = true
			try {
				const dueAt = this.task.dueAtStr
					? Math.floor(new Date(this.task.dueAtStr).getTime() / 1000)
					: null

				await axios.post(
					generateUrl(`/apps/crmhub_nc_app/api/contacts/${this.contactId}/tasks`),
					{
						title: this.task.title.trim(),
						dueAt,
						notes: (this.task.notes || '').trim() || null,
					}
				)
				showSuccess(t('crmhub_nc_app', 'Task created'))
				this.$emit('task-added')
				this.closeTask()
			} catch (e) {
				const msg = e?.response?.data?.message
				showError(msg || t('crmhub_nc_app', 'Failed to create task'))
			} finally {
				this.saving = false
			}
		},

		// ── Activity ──────────────────────────────────────────────────────────
		closeActivity() { this.showActivityModal = false; this.activityType = null; this.activitySummary = '' },
		async saveActivity() {
			if (!this.activityType || !this.activitySummary.trim() || this.saving) return
			this.saving = true
			try {
				await axios.post(
					generateUrl(`/apps/crmhub_nc_app/api/contacts/${this.contactId}/activity`),
					{ activityType: this.activityType.id, summary: this.activitySummary.trim(), source: 'manual' }
				)
				showSuccess(t('crmhub_nc_app', 'Activity logged'))
				this.$emit('meeting-scheduled')
				this.closeActivity()
			} catch {
				showError(t('crmhub_nc_app', 'Failed to log activity'))
			} finally {
				this.saving = false
			}
		},

		// ── Folder ────────────────────────────────────────────────────────────
		closeFolder() { if (!this.saving) { this.showFolderModal = false; this.folderSuccess = '' } },
		async createFolder() {
			const name = this.folderName.trim()
			if (!name || this.saving) return
			this.saving      = true
			this.folderError = ''

			const safeName = name.replace(/[/\\:*?"<>|]/g, '-').trim()
			const basePath = 'CRM Contacts'
			const fullPath = `${basePath}/${safeName}`

			try {
				const user = getCurrentUser()
				if (!user?.uid) throw new Error('Not logged in')

				const parentUrl = generateRemoteUrl(`dav/files/${user.uid}/${basePath}`)
				const folderUrl  = generateRemoteUrl(`dav/files/${user.uid}/${fullPath}`)

				await axios({ method: 'MKCOL', url: parentUrl }).catch(e => {
					if (e?.response?.status !== 405) throw e
				})

				await axios({ method: 'MKCOL', url: folderUrl })

				await axios.post(
					generateUrl(`/apps/crmhub_nc_app/api/contacts/${this.contactId}/projects`),
					{ projectName: safeName, folderPath: '/' + fullPath, status: 'active' }
				).catch(() => null)

				this.folderSuccess = '/' + fullPath
				showSuccess(t('crmhub_nc_app', 'Folder created successfully'))
				this.$emit('folder-created', fullPath)
			} catch (e) {
				if (e?.response?.status === 405) {
					this.folderError = t('crmhub_nc_app', 'A folder with this name already exists')
				} else {
					this.folderError = t('crmhub_nc_app', 'Failed to create folder — check your permissions')
				}
				showError(this.folderError)
			} finally {
				this.saving = false
			}
		},

		// ── Stage ─────────────────────────────────────────────────────────────
		closeStage() { this.showStageModal = false; this.newStage = null },
		async moveStage() {
			if (!this.newStage || this.saving) return
			this.saving = true
			try {
				await axios.post(
					generateUrl(`/apps/crmhub_nc_app/api/contacts/${this.contactId}/move-stage`),
					{ stage: this.newStage.id }
				)
				showSuccess(t('crmhub_nc_app', 'Stage updated'))
				this.$emit('stage-moved')
				this.closeStage()
			} catch {
				showError(t('crmhub_nc_app', 'Failed to move stage'))
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>
@use '../_tokens.scss' as *;

.crm-quick-actions {
	display: flex;
	flex-wrap: wrap;
	gap: $rd-space-2;
	padding: $rd-space-3 0 $rd-space-4;

	&__btn {
		font-size: $rd-font-xs !important;
		height: 30px !important;
		min-height: 30px !important;
		padding: 0 $rd-space-3 !important;
	}
}

.crm-modal-body {
	padding: $rd-space-4 $rd-space-5;
	display: flex;
	flex-direction: column;
	gap: $rd-space-4;
	min-width: 320px;
}

.crm-modal-footer {
	display: flex;
	justify-content: flex-end;
	align-items: center;
	gap: $rd-space-2;
	padding-top: $rd-space-2;
	border-top: 1px solid var(--color-border, #e5e7eb);
	margin-top: $rd-space-1;
}

.crm-modal-hint {
	margin: 0;
	font-size: $rd-font-sm;
	color: $rd-text-muted;
	line-height: 1.5;
}

.crm-modal-error {
	padding: $rd-space-2 $rd-space-3;
	background: rgba(239, 68, 68, 0.1);
	color: #dc2626;
	border-radius: $rd-radius-sm;
	font-size: $rd-font-sm;
}

.crm-modal-success {
	padding: $rd-space-2 $rd-space-3;
	background: rgba(16, 185, 129, 0.1);
	color: #065f46;
	border-radius: $rd-radius-sm;
	font-size: $rd-font-sm;

	code {
		font-family: monospace;
		font-size: $rd-font-xs;
	}
}

.crm-field {
	display: flex;
	flex-direction: column;
	gap: $rd-space-1;

	&__label {
		font-size: $rd-font-xs;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.5px;
		color: $rd-text-muted;
	}

	&__required {
		color: #dc2626;
		margin-left: 2px;
	}
}

.crm-date-input {
	width: 100%;
	padding: 8px 10px;
	border: 1px solid var(--color-border, #e5e7eb);
	border-radius: $rd-radius-sm;
	font-size: $rd-font-sm;
	color: $rd-text;
	background: var(--color-main-background, #fff);
	box-sizing: border-box;

	&:focus {
		outline: 2px solid $rd-primary;
		border-color: $rd-primary;
	}
}
</style>
