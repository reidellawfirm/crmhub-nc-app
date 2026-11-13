<template>
	<div class="crm-projects">

		<!-- Empty state -->
		<div v-if="!projects.length" class="crm-projects__empty">
			<FolderPlusOutlineIcon :size="32" />
			<span>{{ t('crmhub_nc_app', 'No projects linked to this contact yet.') }}</span>
			<NcButton type="primary" @click="openCreate">
				<template #icon><FolderPlusOutlineIcon :size="16" /></template>
				{{ t('crmhub_nc_app', 'Create First Project') }}
			</NcButton>
		</div>

		<!-- Project list -->
		<ul v-else class="crm-projects__list">
			<li v-for="project in projects" :key="project.id" class="crm-projects__item">
				<div class="crm-projects__icon">
					<FolderOutlineIcon :size="18" />
				</div>
				<div class="crm-projects__body">
					<div class="crm-projects__name">{{ project.projectName }}</div>
					<div class="crm-projects__meta">
						<span v-if="project.projectType" class="crm-projects__type">{{ project.projectType }}</span>
						<span class="crm-projects__status" :class="`crm-projects__status--${project.status}`">
							{{ project.status }}
						</span>
						<span v-if="project.assignedUserId" class="crm-projects__assignee">
							{{ project.assignedUserId }}
						</span>
					</div>
					<div v-if="project.folderPath" class="crm-projects__folder">
						<FolderOutlineIcon :size="12" />
						<a :href="ncFolderLink(project.folderPath)" target="_blank" class="crm-projects__folder-link">
							{{ project.folderPath }}
						</a>
					</div>
					<div v-if="project.dueDate" class="crm-projects__due">
						{{ t('crmhub_nc_app', 'Due') }}: {{ formatDate(project.dueDate) }}
					</div>
				</div>
				<a
					:href="projectLink(project.id)"
					class="crm-projects__link"
					:title="t('crmhub_nc_app', 'Open project')"
					target="_blank"
				>
					<OpenInNewIcon :size="14" />
				</a>
			</li>
		</ul>

		<!-- Footer new-project button -->
		<div v-if="projects.length" class="crm-projects__footer">
			<NcButton type="tertiary" @click="openCreate">
				<template #icon><FolderPlusOutlineIcon :size="14" /></template>
				{{ t('crmhub_nc_app', 'New Project') }}
			</NcButton>
		</div>

		<!-- ── CREATE PROJECT DIALOG — same workflow as CRM ContactDetail.vue ── -->
		<NcDialog
			v-if="showCreateModal"
			v-model:open="showCreateModal"
			:name="t('crmhub_nc_app', 'New project')"
		>
			<div class="crm-projects__form">
				<NcTextField v-model="draft.projectName" :label="t('crmhub_nc_app', 'Name')" autofocus />
				<NcTextField v-model="draft.projectType" :label="t('crmhub_nc_app', 'Type')" />

				<!-- Folder picker trigger -->
				<div class="crm-projects__folder-row">
					<NcButton @click="pickFolder">
						<template #icon><FolderOutlineIcon :size="16" /></template>
						{{ draft.folderPath || t('crmhub_nc_app', 'Pick folder') }}
					</NcButton>
					<NcButton v-if="draft.folderNodeId" type="tertiary" @click="clearFolder">
						{{ t('crmhub_nc_app', 'Clear folder') }}
					</NcButton>
				</div>

				<!-- Deck integration (optional) -->
				<DeckPicker
					:board-id="draft.deckBoardId"
					:card-id="draft.deckCardId"
					@update:board-id="draft.deckBoardId = $event"
					@update:card-id="draft.deckCardId = $event"
				/>

				<div v-if="createError" class="crm-projects__error">{{ createError }}</div>
			</div>

			<template #actions>
				<NcButton :disabled="saving" @click="showCreateModal = false">
					{{ t('crmhub_nc_app', 'Cancel') }}
				</NcButton>
				<NcButton type="primary" :disabled="!draft.projectName.trim() || saving" @click="createProject">
					{{ saving ? t('crmhub_nc_app', 'Creating…') : t('crmhub_nc_app', 'Create') }}
				</NcButton>
			</template>
		</NcDialog>

		<!-- Folder picker — lives outside the project dialog (same pattern as ContactDetail.vue) -->
		<FolderPickerDialog
			v-model:open="folderPickerOpen"
			@select="onFolderSelected"
		/>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import api from '../../api.js'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import FolderOutlineIcon from 'vue-material-design-icons/FolderOutline.vue'
import FolderPlusOutlineIcon from 'vue-material-design-icons/FolderPlusOutline.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import FolderPickerDialog from '../FolderPickerDialog.vue'
import DeckPicker from '../DeckPicker.vue'

export default {
	name: 'ProjectsPanel',
	components: {
		NcButton, NcDialog, NcTextField,
		FolderOutlineIcon, FolderPlusOutlineIcon, OpenInNewIcon,
		FolderPickerDialog, DeckPicker,
	},
	props: {
		projects:  { type: Array,  default: () => [] },
		contactId: { type: Number, required: true },
	},
	emits: ['project-created'],

	data() {
		return {
			showCreateModal: false,
			folderPickerOpen: false,
			saving:           false,
			createError:      '',
			draft: {
				projectName: '',
				projectType: '',
				folderNodeId: null,
				folderPath:   '',
				deckBoardId:  null,
				deckCardId:   null,
			},
		}
	},

	watch: {
		// Re-open the project dialog when folder picker closes — same as ContactDetail.vue
		folderPickerOpen(val) {
			if (!val) this.showCreateModal = true
		},
	},

	methods: {
		t,
		projectLink:  (id) => generateUrl('/apps/crmhub_nc_app') + '#/projects/' + id,
		ncFolderLink: (path) => generateUrl('/apps/files') + '?dir=' + encodeURIComponent(path),

		formatDate(ts) {
			if (!ts) return ''
			const d = typeof ts === 'number' ? new Date(ts * 1000) : new Date(ts)
			return isNaN(d.getTime()) ? String(ts) : d.toLocaleDateString()
		},

		openCreate() {
			this.draft       = { projectName: '', projectType: '', folderNodeId: null, folderPath: '', deckBoardId: null, deckCardId: null }
			this.createError = ''
			this.showCreateModal = true
		},

		// Temporarily close project dialog, open folder picker — same pattern as ContactDetail.vue
		pickFolder() {
			this.showCreateModal = false
			this.$nextTick(() => { this.folderPickerOpen = true })
		},

		onFolderSelected({ path, nodeId }) {
			this.draft.folderPath   = path
			this.draft.folderNodeId = nodeId
		},

		clearFolder() {
			this.draft.folderPath   = ''
			this.draft.folderNodeId = null
		},

		// Identical project creation logic to ContactDetail.vue
		async createProject() {
			if (!this.draft.projectName.trim() || this.saving) return
			this.saving      = true
			this.createError = ''
			try {
				await api.projects.create(this.contactId, this.draft)
				showSuccess(t('crmhub_nc_app', 'Project created'))
				this.showCreateModal = false
				this.$emit('project-created')
			} catch (e) {
				const msg = e?.response?.data?.message || e?.response?.data?.error
				this.createError = msg || t('crmhub_nc_app', 'Failed to create project')
				// 5xx errors are also shown by api.js withError
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>
@use '../_tokens.scss' as *;

.crm-projects {
	&__empty {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 12px;
		padding: 28px 20px;
		color: $rd-text-muted;
		font-size: $rd-font-sm;
		text-align: center;
	}

	&__list {
		list-style: none;
		margin: 0;
		padding: 0;
	}

	&__item {
		display: flex;
		align-items: flex-start;
		gap: 10px;
		padding: 10px 4px;
		border-bottom: 1px solid $rd-border-soft;
		&:last-child { border: none; }
		&:hover { background: var(--color-background-hover, #f7f7f7); border-radius: 6px; }
	}

	&__icon {
		color: $rd-primary;
		flex-shrink: 0;
		padding-top: 2px;
	}

	&__body { flex: 1; min-width: 0; }

	&__name {
		font-size: $rd-font-sm;
		font-weight: 600;
		color: $rd-text;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	&__meta {
		display: flex;
		flex-wrap: wrap;
		gap: 6px;
		margin-top: 3px;
	}

	&__type {
		font-size: $rd-font-xs;
		color: $rd-text-muted;
		text-transform: capitalize;
	}

	&__status {
		font-size: $rd-font-xs;
		font-weight: 600;
		padding: 1px 7px;
		border-radius: 999px;
		text-transform: capitalize;

		&--active    { background: rgba(16,185,129,0.12); color: #065f46; }
		&--completed,
		&--won       { background: rgba(59,130,246,0.10); color: #1d4ed8; }
		&--pending   { background: rgba(245,158,11,0.10); color: #92400e; }
		&--archived,
		&--closed,
		&--lost      { background: rgba(156,163,175,0.12); color: #4b5563; }
		&--overdue   { background: rgba(239,68,68,0.10); color: #991b1b; }
	}

	&__assignee { font-size: $rd-font-xs; color: $rd-text-muted; }

	&__folder {
		display: flex;
		align-items: center;
		gap: 4px;
		margin-top: 2px;
		color: $rd-text-muted;
	}

	&__folder-link {
		font-size: $rd-font-xs;
		color: $rd-primary;
		text-decoration: none;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		&:hover { text-decoration: underline; }
	}

	&__due { font-size: $rd-font-xs; color: $rd-text-muted; margin-top: 2px; }

	&__link {
		flex-shrink: 0;
		color: $rd-text-muted;
		padding: 4px;
		border-radius: 4px;
		display: flex;
		align-items: center;
		&:hover { color: $rd-primary; background: $rd-primary-soft; }
	}

	&__footer {
		display: flex;
		justify-content: flex-end;
		padding-top: 8px;
	}

	// Form inside NcDialog
	&__form {
		display: flex;
		flex-direction: column;
		gap: $rd-space-3;
		padding: $rd-space-3 $rd-space-2;
	}

	&__folder-row {
		display: flex;
		align-items: center;
		gap: $rd-space-2;
		flex-wrap: wrap;
	}

	&__error {
		padding: $rd-space-2 $rd-space-3;
		background: rgba(239, 68, 68, 0.1);
		color: #dc2626;
		border-radius: $rd-radius-sm;
		font-size: $rd-font-sm;
	}
}
</style>
