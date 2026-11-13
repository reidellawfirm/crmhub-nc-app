<template>
	<!-- Loading skeleton while fetching the contact -->
	<div v-if="loading" class="rd-page rd-page--skeleton">
		<div class="rd-skeleton rd-skeleton--header" />
		<div class="rd-grid">
			<div class="rd-col rd-col--left">
				<div class="rd-skeleton rd-skeleton--card" />
			</div>
			<div class="rd-col rd-col--right">
				<div class="rd-skeleton rd-skeleton--card" />
				<div class="rd-skeleton rd-skeleton--card rd-skeleton--card-sm" />
			</div>
		</div>
	</div>
	<div v-else-if="contact" class="rd-page">
		<HeaderCard
			:first-name="contact.firstName"
			:last-name="contact.lastName"
			:email="contact.email"
			:phone="contact.phone"
			:status="contact.status"
			:fallback-name="contact.email">
			<template #actions>
				<NcButton @click="editDialog = true">
					<template #icon><PencilOutlineIcon :size="16" /></template>
					{{ t('crmhub_nc_app', 'Edit') }}
				</NcButton>
				<NcButton @click="focusNote">
					<template #icon><NoteTextOutlineIcon :size="16" /></template>
					{{ t('crmhub_nc_app', 'Add note') }}
				</NcButton>
				<NcButton type="primary" @click="newProjectDialog = true">
					<template #icon><PlusIcon :size="16" /></template>
					{{ t('crmhub_nc_app', 'New project') }}
				</NcButton>
				<NcButton @click="openDeleteDialog">
					<template #icon><DeleteOutlineIcon :size="16" /></template>
					{{ t('crmhub_nc_app', 'Delete') }}
				</NcButton>
			</template>
		</HeaderCard>

		<ContactSummaryCard
			:contact="contact"
			:projects="projects"
			:notes-count="notes.length"
			:activity-count="activity.length"
			:last-contacted-at="lastContactedAt" />

		<div class="rd-grid">
			<div class="rd-col rd-col--left">
				<NotesCard ref="notesCard" :notes="notes" @add-note="addNote" />
				<ContactFilesCard :projects="projects" />
			</div>
			<div class="rd-col rd-col--right">
				<ProjectsCard :projects="projects" @new-project="newProjectDialog = true" />
				<ContactDeckCard :projects="projects" />
				<ActivityTimeline :entries="activity" />
			</div>
		</div>

		<!-- ── Delete confirmation dialog ────────────────────────────────── -->
		<NcDialog
			v-if="deleteDialog"
			v-model:open="deleteDialog"
			:name="t('crmhub_nc_app', 'Delete contact?')">
			<div class="rd-delete-warn">
				<p class="rd-delete-warn__lead">
					<AlertOutlineIcon :size="18" class="rd-delete-warn__icon" />
					{{ t('crmhub_nc_app', 'You are about to permanently delete:') }}
					<strong>{{ deleteContactName }}</strong>
				</p>
				<ul class="rd-delete-warn__list">
					<li v-if="projects.length">
						{{ n('crmhub_nc_app', '{n} linked project will be deleted.', '{n} linked projects will be deleted.', projects.length, { n: projects.length }) }}
					</li>
					<li v-if="notes.length">
						{{ n('crmhub_nc_app', '{n} note will be permanently deleted.', '{n} notes will be permanently deleted.', notes.length, { n: notes.length }) }}
					</li>
					<li>{{ t('crmhub_nc_app', 'All activity history will be deleted.') }}</li>
					<li v-if="contactHasVcard">
						{{ t('crmhub_nc_app', 'The linked Nextcloud Contacts card will also be deleted.') }}
					</li>
					<li v-if="contact.deckCardId || projects.some(p => p.deckCardId)">
						{{ t('crmhub_nc_app', 'Deck card links will be removed.') }}
					</li>
				</ul>
				<p class="rd-delete-warn__final">{{ t('crmhub_nc_app', 'This action cannot be undone.') }}</p>
			</div>
			<template #actions>
				<NcButton :disabled="deleting" @click="deleteDialog = false">{{ t('crmhub_nc_app', 'Cancel') }}</NcButton>
				<NcButton type="error" :disabled="deleting" @click="confirmDelete">
					<template #icon><DeleteOutlineIcon :size="16" /></template>
					<span v-if="deleting">{{ t('crmhub_nc_app', 'Deleting…') }}</span>
					<span v-else>{{ t('crmhub_nc_app', 'Delete permanently') }}</span>
				</NcButton>
			</template>
		</NcDialog>

		<NcDialog v-if="newProjectDialog" v-model:open="newProjectDialog" :name="t('crmhub_nc_app', 'New project')">
			<div class="rd-form">
				<NcTextField v-model="projectDraft.projectName" :label="t('crmhub_nc_app', 'Name')" />
				<NcTextField v-model="projectDraft.projectType" :label="t('crmhub_nc_app', 'Type')" />
				<NcButton @click="pickFolder">
					<template #icon><FolderOutlineIcon :size="16" /></template>
					{{ projectDraft.folderPath || t('crmhub_nc_app', 'Pick folder') }}
				</NcButton>
				<NcButton v-if="projectDraft.folderNodeId" @click="clearFolder">
					{{ t('crmhub_nc_app', 'Clear folder') }}
				</NcButton>
				<DeckPicker
					:board-id="projectDraft.deckBoardId"
					:card-id="projectDraft.deckCardId"
					@update:board-id="projectDraft.deckBoardId = $event"
					@update:card-id="projectDraft.deckCardId = $event" />
			</div>
			<template #actions>
				<NcButton @click="newProjectDialog = false">{{ t('crmhub_nc_app', 'Cancel') }}</NcButton>
				<NcButton type="primary" @click="createProject">{{ t('crmhub_nc_app', 'Create') }}</NcButton>
			</template>
		</NcDialog>

		<NcDialog v-if="editDialog" v-model:open="editDialog" :name="t('crmhub_nc_app', 'Edit contact')">
			<div class="rd-form">
				<!-- Name fields — at least one required -->
				<NcTextField v-model="contactDraft.firstName" :label="t('crmhub_nc_app', 'First name')"
					:error="editTouched && !!editNameError" />
				<NcTextField v-model="contactDraft.lastName" :label="t('crmhub_nc_app', 'Last name')"
					:error="editTouched && !!editNameError" />
				<NcTextField v-model="contactDraft.organization" :label="t('crmhub_nc_app', 'Organization')"
					:error="editTouched && !!editNameError" />
				<p v-if="editTouched && editNameError" class="rd-field-error">{{ editNameError }}</p>
				<!-- Email — required, valid format, unique -->
				<NcTextField
					v-model="contactDraft.email"
					:label="t('crmhub_nc_app', 'Email *')"
					type="email"
					:error="editTouched && !!editEmailError"
					:helper-text="editTouched ? editEmailError : ''"
					@blur="editTouched = true" />
				<NcTextField v-model="contactDraft.phone" :label="t('crmhub_nc_app', 'Phone')" />
				<NcTextField v-model="contactDraft.status" :label="t('crmhub_nc_app', 'Status')" />
				<!-- API-level errors (e.g. duplicate email detected server-side) -->
				<p v-if="saveApiError" class="rd-field-error">{{ saveApiError }}</p>
			</div>
			<template #actions>
				<NcButton @click="closeEditDialog">{{ t('crmhub_nc_app', 'Cancel') }}</NcButton>
				<NcButton type="primary" :disabled="saving || !editCanSave" @click="saveContact">
					<span v-if="saving">{{ t('crmhub_nc_app', 'Saving…') }}</span>
					<span v-else>{{ t('crmhub_nc_app', 'Save') }}</span>
				</NcButton>
			</template>
		</NcDialog>

		<FolderPickerDialog
			v-model:open="folderPickerOpen"
			@select="onFolderSelected"
		/>
	</div>
</template>

<script>
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import { showError } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import PencilOutlineIcon from 'vue-material-design-icons/PencilOutline.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import NoteTextOutlineIcon from 'vue-material-design-icons/NoteTextOutline.vue'
import FolderOutlineIcon from 'vue-material-design-icons/FolderOutline.vue'
import DeleteOutlineIcon from 'vue-material-design-icons/DeleteOutline.vue'
import AlertOutlineIcon from 'vue-material-design-icons/AlertOutline.vue'
import HeaderCard from '../components/HeaderCard.vue'
import NotesCard from '../components/NotesCard.vue'
import ProjectsCard from '../components/ProjectsCard.vue'
import ActivityTimeline from '../components/ActivityTimeline.vue'
import ContactSummaryCard from '../components/ContactSummaryCard.vue'
import ContactFilesCard from '../components/ContactFilesCard.vue'
import ContactDeckCard from '../components/ContactDeckCard.vue'
import DeckPicker from '../components/DeckPicker.vue'
import FolderPickerDialog from '../components/FolderPickerDialog.vue'
import api from '../api.js'
import { bus, EVT_CONTACTS_CHANGED, EVT_ACTIVITY_CHANGED } from '../bus.js'

export default {
	name: 'ContactDetail',
	components: {
		NcButton, NcDialog, NcTextField,
		HeaderCard, NotesCard, ProjectsCard, ActivityTimeline, ContactSummaryCard, ContactFilesCard, ContactDeckCard, DeckPicker, FolderPickerDialog,
		PencilOutlineIcon, PlusIcon, NoteTextOutlineIcon, FolderOutlineIcon,
		DeleteOutlineIcon, AlertOutlineIcon,
	},
	props: { id: { type: [String, Number], required: true } },
	data() {
		return {
			loading: true,
			contact: null,
			projects: [],
			notes: [],
			activity: [],
			newProjectDialog: false,
			editDialog: false,
			editTouched: false,
			saving: false,
			saveApiError: '',
			deleteDialog: false,
			deleting: false,
			projectDraft: { projectName: '', projectType: '', folderNodeId: null, folderPath: '', deckBoardId: null, deckCardId: null },
			folderPickerOpen: false,
			contactDraft: { firstName: '', lastName: '', email: '', phone: '', organization: '', status: 'active' },
		}
	},
	mounted() { this.reload() },
	watch: {
		id() { this.reload() },
		folderPickerOpen(val) {
			// Reopen the project dialog when the folder picker closes
			if (!val) this.newProjectDialog = true
		},
	},
	computed: {
		editEmailError() {
			const e = (this.contactDraft.email || '').trim()
			if (!e) return this.t('crmhub_nc_app', 'Email is required.')
			if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e)) return this.t('crmhub_nc_app', 'Please enter a valid email address.')
			return ''
		},
		editNameError() {
			const fn = (this.contactDraft.firstName || '').trim()
			const ln = (this.contactDraft.lastName || '').trim()
			const org = (this.contactDraft.organization || '').trim()
			if (!fn && !ln && !org) {
				return this.t('crmhub_nc_app', 'Please provide at least a first name, last name, or organization.')
			}
			return ''
		},
		editCanSave() {
			return !this.editEmailError && !this.editNameError
		},
		deleteContactName() {
			if (!this.contact) return ''
			return [
				this.contact.firstName,
				this.contact.lastName,
			].filter(Boolean).join(' ').trim() || this.contact.email || this.t('crmhub_nc_app', 'this contact')
		},
		/** True when there is a CardDAV/NC Contacts vCard linked (not Groundhogg-sourced). */
		contactHasVcard() {
			const uid = this.contact?.vcardUid
			return !!uid && !uid.startsWith('gh-')
		},
		lastContactedAt() {
			if (!this.activity.length) {
				return null
			}
			return this.activity.reduce((max, item) => {
				const occurredAt = Number(item?.occurredAt || 0)
				return Math.max(max, occurredAt)
			}, 0)
		},
	},
	methods: {
		t,
		n,
		async reload() {
			this.loading = true
			const cid = Number(this.id)
			try {
				this.contact = await api.contacts.get(cid)
				if (!this.contact) {
					this.$router.push({ name: 'home' })
					return
				}
				;[this.projects, this.notes, this.activity] = await Promise.all([
					api.projects.forContact(cid),
					api.notes.forContact(cid),
					api.contacts.activity(cid, 20),
				])
			} catch (err) {
				if (err?.response?.status === 404) {
					this.$router.push({ name: 'home' })
					return
				}
				// other errors shown by api.js withError wrapper
			} finally {
				this.loading = false
			}
			if (this.contact) {
				this.contactDraft = {
					firstName: this.contact.firstName || '',
					lastName: this.contact.lastName || '',
					email: this.contact.email || '',
					phone: this.contact.phone || '',
					organization: this.contact.organization || '',
					status: this.contact.status || 'active',
				}
			}
		},
		async addNote(content) {
			try {
				await api.notes.create({ contactCacheId: this.contact.id, content })
				;[this.notes, this.activity] = await Promise.all([
					api.notes.forContact(this.contact.id),
					api.contacts.activity(this.contact.id, 20),
				])
			} catch { /* shown by withError */ }
		},
		focusNote() {
			const notesEl = this.$refs.notesCard?.$el
			notesEl?.scrollIntoView({ behavior: 'smooth', block: 'start' })
			notesEl?.querySelector('textarea')?.focus()
		},
		pickFolder() {
			this.newProjectDialog = false
			this.$nextTick(() => { this.folderPickerOpen = true })
		},
		onFolderSelected({ path, nodeId }) {
			this.projectDraft.folderPath = path
			this.projectDraft.folderNodeId = nodeId
		},
		clearFolder() {
			this.projectDraft.folderPath = ''
			this.projectDraft.folderNodeId = null
		},
		async createProject() {
			try {
				const created = await api.projects.create(this.contact.id, this.projectDraft)
				this.newProjectDialog = false
				this.projects = await api.projects.forContact(this.contact.id)
				this.activity = await api.contacts.activity(this.contact.id, 20)
				this.$router.push({ name: 'project', params: { id: created.id } })
			} catch { /* shown by withError */ }
		},
		async saveContact() {
			// Surface validation errors before hitting the API.
			this.editTouched = true
			if (!this.editCanSave) return
			this.saving = true
			this.saveApiError = ''
			try {
				await api.contacts.update(this.contact.id, this.contactDraft)
				this.closeEditDialog()
				await this.reload()
				bus.emit(EVT_CONTACTS_CHANGED)
			} catch (err) {
				const status = err?.response?.status
				const data = err?.response?.data
				if (status === 422 && data?.message) {
					// Show inline AND a prominent toast so the user sees it immediately.
					this.saveApiError = data.message
					showError(data.message)
				}
				// 5xx / network errors → toast already shown by api.js withError().
			} finally {
				this.saving = false
			}
		},
		closeEditDialog() {
			this.editDialog = false
			this.editTouched = false
			this.saveApiError = ''
		},
		openDeleteDialog() {
			this.deleteDialog = true
		},
		async confirmDelete() {
			this.deleting = true
			try {
				await api.contacts.remove(this.contact.id)
				this.deleteDialog = false
				// Notify sidebar, dashboard, and activity feed to refresh.
				bus.emit(EVT_CONTACTS_CHANGED)
				bus.emit(EVT_ACTIVITY_CHANGED)
				// Navigate away — contact no longer exists.
				this.$router.push({ name: 'home' })
			} catch (err) {
				// 5xx toast is handled by withError(); show a specific message for other errors.
				const msg = err?.response?.data?.error || this.t('crmhub_nc_app', 'Could not delete contact. Please try again.')
				showError(msg)
			} finally {
				this.deleting = false
			}
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

@media (max-width: 960px) {
	.rd-page { padding: $rd-space-4; }
	.rd-grid { grid-template-columns: 1fr; }
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

// ── Delete confirmation dialog ───────────────────────────────────────────────
.rd-delete-warn {
	display: flex;
	flex-direction: column;
	gap: $rd-space-3;
	padding: $rd-space-3 $rd-space-2;

	&__lead {
		display: flex;
		align-items: center;
		gap: $rd-space-2;
		font-weight: 600;
	}

	&__icon {
		color: var(--color-warning, #e9a01c);
		flex-shrink: 0;
	}

	&__list {
		margin: 0 0 0 $rd-space-5;
		padding: 0;
		list-style: disc;
		color: var(--color-text-maxcontrast, #555);

		li {
			margin-bottom: $rd-space-1;
		}
	}

	&__final {
		font-weight: 600;
		color: var(--color-error, #e9322d);
	}
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
	&--card-sm { height: 140px; margin-top: $rd-space-4; }
}

.rd-page--skeleton {
	pointer-events: none;
}
</style>
