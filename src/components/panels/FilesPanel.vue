<template>
	<div class="crm-files">

		<!-- Files list -->
		<ul v-if="allFiles.length" class="crm-files__list">
			<li v-for="file in allFiles" :key="file.path + file.name" class="crm-files__item">
				<div class="crm-files__icon">
					<component :is="fileIcon(file.name)" :size="18" />
				</div>
				<div class="crm-files__body">
					<div class="crm-files__name">{{ file.name }}</div>
					<div class="crm-files__meta">
						<span v-if="file.projectName" class="crm-files__project">{{ file.projectName }}</span>
						<span v-if="file.size" class="crm-files__size">{{ formatSize(file.size) }}</span>
					</div>
				</div>
				<a
					v-if="file.ncPath"
					:href="ncFilesLink(file.ncPath)"
					class="crm-files__link"
					:title="t('crmhub_nc_app', 'Open in Files')"
					target="_blank"
				>
					<OpenInNewIcon :size="14" />
				</a>
			</li>
		</ul>

		<!-- Folder shortcuts -->
		<div v-if="folders.length" class="crm-files__folders">
			<h4 class="crm-files__section-title">{{ t('crmhub_nc_app', 'Project Folders') }}</h4>
			<div v-for="folder in folders" :key="folder.path" class="crm-files__folder-row">
				<FolderOutlineIcon :size="16" />
				<a :href="ncFolderLink(folder.path)" target="_blank" class="crm-files__folder-link">
					{{ folder.name || folder.path }}
				</a>
				<span class="crm-files__folder-hint">{{ t('crmhub_nc_app', '↗ open in Files') }}</span>
			</div>
		</div>

		<!-- Empty state with helpful guidance -->
		<div v-if="!allFiles.length && !folders.length" class="crm-files__empty">
			<FileDocumentOutlineIcon :size="36" />
			<p class="crm-files__empty-title">{{ t('crmhub_nc_app', 'No files linked yet') }}</p>
			<p class="crm-files__empty-hint">
				{{ t('crmhub_nc_app', 'Create a contact folder using the Folder button above, then upload files there in Nextcloud Files. They will appear here automatically.') }}
			</p>
			<a :href="filesAppLink" target="_blank" class="crm-files__open-files-btn">
				<FolderOutlineIcon :size="14" />
				{{ t('crmhub_nc_app', 'Open Nextcloud Files') }}
			</a>
		</div>

		<!-- Has folders but no files yet -->
		<div v-else-if="folders.length && !allFiles.length" class="crm-files__no-files-hint">
			{{ t('crmhub_nc_app', 'Folders linked — upload files there to see them here.') }}
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import FileDocumentOutlineIcon from 'vue-material-design-icons/FileDocumentOutline.vue'
import FilePdfBoxIcon from 'vue-material-design-icons/FilePdfBox.vue'
import FileImageOutlineIcon from 'vue-material-design-icons/FileImageOutline.vue'
import FileExcelOutlineIcon from 'vue-material-design-icons/FileExcelOutline.vue'
import FolderOutlineIcon from 'vue-material-design-icons/FolderOutline.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'

export default {
	name: 'FilesPanel',
	components: {
		FileDocumentOutlineIcon, FilePdfBoxIcon, FileImageOutlineIcon,
		FileExcelOutlineIcon, FolderOutlineIcon, OpenInNewIcon,
	},
	props: {
		projects: { type: Array, default: () => [] },
	},
	computed: {
		filesAppLink() {
			return generateUrl('/apps/files') + '?dir=' + encodeURIComponent('/CRM Contacts')
		},
		folders() {
			return this.projects
				.filter(p => p.folderPath)
				.map(p => ({
					path: p.folderPath,
					name: p.projectName || p.name || p.folderPath.split('/').pop(),
				}))
		},
		allFiles() {
			const files = []
			for (const project of this.projects) {
				if (project.recentFiles && Array.isArray(project.recentFiles)) {
					for (const f of project.recentFiles) {
						files.push({ ...f, projectName: project.projectName || project.name })
					}
				}
			}
			return files
		},
	},
	methods: {
		t,
		fileIcon(name) {
			const ext = (name || '').split('.').pop().toLowerCase()
			if (ext === 'pdf') return FilePdfBoxIcon
			if (['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'].includes(ext)) return FileImageOutlineIcon
			if (['xlsx', 'xls', 'csv'].includes(ext)) return FileExcelOutlineIcon
			return FileDocumentOutlineIcon
		},
		formatSize(bytes) {
			if (!bytes) return ''
			if (bytes < 1024) return bytes + ' B'
			if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB'
			return (bytes / 1048576).toFixed(1) + ' MB'
		},
		ncFilesLink: (path) => generateUrl('/apps/files') + '?dir=' + encodeURIComponent(path),
		ncFolderLink: (path) => generateUrl('/apps/files') + '?dir=' + encodeURIComponent(path),
	},
}
</script>

<style lang="scss" scoped>
@use '../_tokens.scss' as *;

.crm-files {
	&__empty {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 10px;
		padding: 28px 20px;
		color: $rd-text-muted;
		text-align: center;
	}

	&__empty-title {
		margin: 0;
		font-size: $rd-font-sm;
		font-weight: 600;
		color: $rd-text;
	}

	&__empty-hint {
		margin: 0;
		font-size: $rd-font-xs;
		color: $rd-text-muted;
		line-height: 1.6;
		max-width: 320px;
	}

	&__open-files-btn {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		margin-top: 4px;
		padding: 7px 16px;
		background: $rd-primary;
		color: #fff;
		border-radius: $rd-radius-sm;
		font-size: $rd-font-sm;
		font-weight: 600;
		text-decoration: none;
		transition: opacity 0.15s;

		&:hover { opacity: 0.85; color: #fff; }
	}

	&__no-files-hint {
		padding: 10px 4px;
		font-size: $rd-font-xs;
		color: $rd-text-muted;
		text-align: center;
		font-style: italic;
	}

	&__list {
		list-style: none;
		margin: 0;
		padding: 0;
	}

	&__item {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 7px 4px;
		border-bottom: 1px solid $rd-border-soft;
		&:last-child { border: none; }
		&:hover { background: var(--color-background-hover, #f7f7f7); border-radius: 6px; }
	}

	&__icon { color: $rd-primary; flex-shrink: 0; }

	&__body { flex: 1; min-width: 0; }

	&__name {
		font-size: $rd-font-sm;
		color: $rd-text;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	&__meta { display: flex; gap: 8px; margin-top: 2px; }

	&__project, &__size {
		font-size: $rd-font-xs;
		color: $rd-text-muted;
	}

	&__link {
		flex-shrink: 0;
		color: $rd-text-muted;
		padding: 4px;
		border-radius: 4px;
		display: flex;
		align-items: center;
		&:hover { color: $rd-primary; }
	}

	&__folders {
		margin-top: 14px;
		padding-top: 10px;
		border-top: 1px solid $rd-border-soft;
	}

	&__section-title {
		font-size: $rd-font-xs;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.5px;
		color: $rd-text-muted;
		margin: 0 0 8px;
	}

	&__folder-row {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 6px 4px;
		color: $rd-primary;
		border-radius: 4px;
		&:hover { background: var(--color-background-hover, #f7f7f7); }
	}

	&__folder-link {
		font-size: $rd-font-sm;
		color: $rd-primary;
		text-decoration: none;
		flex: 1;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		&:hover { text-decoration: underline; }
	}

	&__folder-hint {
		font-size: $rd-font-xs;
		color: $rd-text-muted;
		flex-shrink: 0;
	}
}
</style>
