<template>
	<SectionCard :title="t('crmhub_nc_app', 'Files')" :count="folderProjects.length">
		<template v-if="folderProjects.length">
			<ul class="rd-file-list">
				<li v-for="project in folderProjects" :key="project.id" class="rd-file-list__item">
					<div>
						<div class="rd-file-list__project">{{ project.projectName || t('crmhub_nc_app', 'Untitled project') }}</div>
						<div class="rd-file-list__path">{{ project.folderPath }}</div>
					</div>
					<a :href="filesLink(project.folderPath)" target="_blank" rel="noreferrer" class="rd-file-list__link">
						{{ t('crmhub_nc_app', 'Open folder') }}
					</a>
				</li>
			</ul>
		</template>
		<EmptyState v-else
			:message="t('crmhub_nc_app', 'No related project folders yet')"
			:hint="t('crmhub_nc_app', 'Attach a Files folder to a matter to see it here.')">
			<template #icon><FolderOutlineIcon :size="40" /></template>
		</EmptyState>
	</SectionCard>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import SectionCard from './SectionCard.vue'
import EmptyState from './EmptyState.vue'
import FolderOutlineIcon from 'vue-material-design-icons/FolderOutline.vue'

export default {
	name: 'ContactFilesCard',
	components: { SectionCard, EmptyState, FolderOutlineIcon },
	props: {
		projects: { type: Array, default: () => [] },
	},
	computed: {
		folderProjects() {
			return this.projects.filter(p => p.folderPath)
		},
	},
	methods: {
		t,
		filesLink(folderPath) {
			const base = generateUrl('/apps/files/')
			return `${base}?dir=${encodeURIComponent(folderPath)}`
		},
	},
}
</script>

<style lang="scss" scoped>
@use './_tokens.scss' as *;

.rd-file-list {
	list-style: none;
	margin: 0;
	padding: 0;
	display: grid;
	gap: $rd-space-3;
}

.rd-file-list__item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: $rd-space-3;
	padding: $rd-space-3;
	background: $rd-bg-page;
	border-radius: $rd-radius-md;
}

.rd-file-list__project {
	font-weight: 600;
	color: $rd-text;
}

.rd-file-list__path {
	font-size: $rd-font-sm;
	color: $rd-text-muted;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	max-width: 320px;
}

.rd-file-list__link {
	font-size: $rd-font-sm;
	font-weight: 600;
	color: $rd-primary;
	text-decoration: none;
}
</style>
