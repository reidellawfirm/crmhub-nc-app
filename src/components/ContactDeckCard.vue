<template>
	<SectionCard :title="t('crmhub_nc_app', 'Deck integration')" :count="linkedDeckProjects.length">
		<template v-if="linkedDeckProjects.length">
			<ul class="rd-deck-list">
				<li v-for="project in linkedDeckProjects" :key="project.id" class="rd-deck-list__item">
					<div>
						<div class="rd-deck-list__project">{{ project.projectName || t('crmhub_nc_app', 'Untitled project') }}</div>
						<div class="rd-deck-list__meta">
							<span>{{ t('crmhub_nc_app', 'Board') }} {{ project.deckBoardId }}</span>
							<span>·</span>
							<span>{{ t('crmhub_nc_app', 'Card') }} {{ project.deckCardId }}</span>
						</div>
					</div>
					<router-link :to="{ name: 'project', params: { id: project.id } }" class="rd-deck-list__link">
						{{ t('crmhub_nc_app', 'Open matter') }}
					</router-link>
				</li>
			</ul>
		</template>
		<EmptyState v-else
			:message="t('crmhub_nc_app', 'No Deck cards linked yet')"
			:hint="t('crmhub_nc_app', 'Link a Deck card to a matter to surface workflow status here.')">
			<template #icon><FolderOutlineIcon :size="40" /></template>
		</EmptyState>
	</SectionCard>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import SectionCard from './SectionCard.vue'
import EmptyState from './EmptyState.vue'
import FolderOutlineIcon from 'vue-material-design-icons/FolderOutline.vue'

export default {
	name: 'ContactDeckCard',
	components: { SectionCard, EmptyState, FolderOutlineIcon },
	props: {
		projects: { type: Array, default: () => [] },
	},
	computed: {
		linkedDeckProjects() {
			return this.projects.filter(p => p.deckCardId || p.deckBoardId)
		},
	},
	methods: { t },
}
</script>

<style lang="scss" scoped>
@use './_tokens.scss' as *;

.rd-deck-list {
	list-style: none;
	margin: 0;
	padding: 0;
	display: grid;
	gap: $rd-space-3;
}

.rd-deck-list__item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: $rd-space-3;
	padding: $rd-space-3;
	background: $rd-bg-page;
	border-radius: $rd-radius-md;
}

.rd-deck-list__project {
	font-weight: 600;
	color: $rd-text;
}

.rd-deck-list__meta {
	font-size: $rd-font-sm;
	color: $rd-text-muted;
	display: flex;
	gap: $rd-space-1;
	align-items: center;
}

.rd-deck-list__link {
	font-size: $rd-font-sm;
	font-weight: 600;
	color: $rd-primary;
	text-decoration: none;
}
</style>
