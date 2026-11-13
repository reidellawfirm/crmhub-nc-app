<template>
	<SectionCard :title="t('crmhub_nc_app', 'Projects')" :count="projects.length">
		<template #icon><FolderOutlineIcon :size="18" /></template>
		<template #actions>
			<NcButton type="primary" @click="$emit('new-project')">
				<template #icon><PlusIcon :size="16" /></template>
				{{ t('crmhub_nc_app', 'New project') }}
			</NcButton>
		</template>

		<EmptyState v-if="!projects.length"
			:message="t('crmhub_nc_app', 'No projects yet')"
			:hint="t('crmhub_nc_app', 'Create one to link a Files folder and a Deck card.')">
			<template #icon><FolderOutlineIcon :size="40" /></template>
		</EmptyState>

		<ul v-else class="rd-projects">
			<li v-for="p in projects" :key="p.id" class="rd-project">
				<router-link :to="{ name: 'project', params: { id: p.id } }" class="rd-project__link">
					<div class="rd-project__icon">
						<FolderOutlineIcon :size="20" />
					</div>
					<div class="rd-project__body">
						<div class="rd-project__name">{{ p.projectName || t('crmhub_nc_app', 'Untitled') }}</div>
						<div class="rd-project__meta">
							<span>{{ p.projectType || t('crmhub_nc_app', 'No type') }}</span>
							<span class="rd-project__sep">·</span>
							<span class="rd-chip" :class="`rd-chip--${chipKey(p.status)}`">{{ p.status }}</span>
							<template v-if="p.folderPath">
								<span class="rd-project__sep">·</span>
								<span class="rd-project__path" :title="p.folderPath">{{ p.folderPath }}</span>
							</template>
						</div>
					</div>
					<ChevronRightIcon :size="18" class="rd-project__chev" />
				</router-link>
			</li>
		</ul>
	</SectionCard>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import FolderOutlineIcon from 'vue-material-design-icons/FolderOutline.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import SectionCard from './SectionCard.vue'
import EmptyState from './EmptyState.vue'

export default {
	name: 'ProjectsCard',
	components: { NcButton, SectionCard, EmptyState, FolderOutlineIcon, PlusIcon, ChevronRightIcon },
	props: {
		projects: { type: Array, default: () => [] },
	},
	emits: ['new-project'],
	methods: {
		t,
		chipKey(status) {
			const s = (status || '').toLowerCase()
			return ['active', 'prospect', 'archived', 'inactive'].includes(s) ? s : 'active'
		},
	},
}
</script>

<style lang="scss" scoped>
@use './_tokens.scss' as *;

.rd-projects {
	list-style: none;
	margin: 0;
	padding: 0;
}

.rd-project {
	& + & { border-top: 1px solid $rd-border-soft; }

	&__link {
		display: flex;
		align-items: center;
		gap: $rd-space-3;
		padding: $rd-space-3 $rd-space-2;
		text-decoration: none;
		color: $rd-text;
		border-radius: $rd-radius-md;
		transition: background 120ms ease;

		&:hover, &:focus-visible {
			background: $rd-primary-soft;
			outline: none;
		}
	}

	&__icon {
		width: 36px;
		height: 36px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		background: $rd-bg-page;
		border-radius: $rd-radius-md;
		color: $rd-primary;
		flex-shrink: 0;

		:deep(.material-design-icon) { color: $rd-primary; display: inline-flex; }
	}

	&__body { flex: 1; min-width: 0; }

	&__name {
		font-size: $rd-font-md;
		font-weight: 600;
		color: $rd-text;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__meta {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: $rd-space-1;
		margin-top: 2px;
		color: $rd-text-muted;
		font-size: $rd-font-sm;
	}

	&__sep { opacity: 0.6; }

	&__path {
		max-width: 280px;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__chev { color: $rd-text-muted; flex-shrink: 0; }
}

.rd-chip {
	display: inline-flex;
	padding: 1px 8px;
	border-radius: 999px;
	font-size: $rd-font-xs;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.3px;
	color: #fff;

	&--active   { background: $rd-status-active; }
	&--prospect { background: $rd-status-prospect; }
	&--archived { background: $rd-status-archived; }
	&--inactive { background: $rd-status-inactive; }
}
</style>
