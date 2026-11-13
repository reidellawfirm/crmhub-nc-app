<template>
	<div class="crm-deck">
		<div v-if="!deckProjects.length" class="crm-deck__empty">
			<ViewColumnOutlineIcon :size="28" />
			<span>{{ t('crmhub_nc_app', 'No Deck cards linked') }}</span>
		</div>

		<ul v-else class="crm-deck__list">
			<li v-for="project in deckProjects" :key="project.id" class="crm-deck__item">
				<div class="crm-deck__board">
					<ViewColumnOutlineIcon :size="16" />
					<span class="crm-deck__board-name">{{ project.projectName }}</span>
				</div>
				<div class="crm-deck__stage-row">
					<span class="crm-deck__stage">
						{{ project.pipelineStage || project.status || t('crmhub_nc_app', 'No stage') }}
					</span>
					<span class="crm-deck__deck-id" v-if="project.deckCardId">
						{{ t('crmhub_nc_app', 'Card') }} #{{ project.deckCardId }}
					</span>
				</div>
				<div v-if="project.assignedUserId" class="crm-deck__assignee">
					{{ t('crmhub_nc_app', 'Assigned') }}: {{ project.assignedUserId }}
				</div>
			</li>
		</ul>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import ViewColumnOutlineIcon from 'vue-material-design-icons/ViewColumnOutline.vue'

export default {
	name: 'DeckPanel',
	components: { ViewColumnOutlineIcon },
	props: {
		projects: { type: Array, default: () => [] },
	},
	computed: {
		deckProjects() {
			return this.projects.filter(p => p.deckCardId || p.deckBoardId)
		},
	},
	methods: { t },
}
</script>

<style lang="scss" scoped>
@use '../_tokens.scss' as *;

.crm-deck {
	&__empty {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 8px;
		padding: 20px;
		color: $rd-text-muted;
		font-size: $rd-font-sm;
		text-align: center;
	}

	&__list { list-style: none; margin: 0; padding: 0; }

	&__item {
		padding: 8px;
		border: 1px solid $rd-border-soft;
		border-radius: $rd-radius-sm;
		margin-bottom: 8px;
		background: $rd-bg-page;
		&:last-child { margin-bottom: 0; }
	}

	&__board {
		display: flex;
		align-items: center;
		gap: 6px;
		color: $rd-text-muted;
		margin-bottom: 4px;
	}

	&__board-name {
		font-size: $rd-font-xs;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.4px;
	}

	&__stage-row {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	&__stage {
		font-size: $rd-font-sm;
		font-weight: 600;
		color: $rd-text;
		background: rgba(99,102,241,0.10);
		color: #4338ca;
		padding: 2px 10px;
		border-radius: 999px;
		text-transform: capitalize;
	}

	&__deck-id {
		font-size: $rd-font-xs;
		color: $rd-text-muted;
	}

	&__assignee {
		font-size: $rd-font-xs;
		color: $rd-text-muted;
		margin-top: 4px;
	}
}
</style>
