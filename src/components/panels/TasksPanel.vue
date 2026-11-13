<template>
	<div class="crm-tasks">
		<div v-if="loading" class="crm-tasks__loading">
			<div class="crm-skeleton crm-skeleton--wide" />
			<div class="crm-skeleton crm-skeleton--half" />
		</div>

		<div v-else-if="!tasks.length" class="crm-tasks__empty">
			<CheckboxMarkedOutlineIcon :size="28" />
			<span>{{ t('crmhub_nc_app', 'No pending tasks') }}</span>
		</div>

		<ul v-else class="crm-tasks__list">
			<li
				v-for="task in tasks"
				:key="task.id"
				class="crm-tasks__item"
				:class="{ 'crm-tasks__item--overdue': isOverdue(task) }"
			>
				<div class="crm-tasks__check">
					<input
						type="checkbox"
						:checked="task.status === 'done'"
						:disabled="completing === task.id"
						@change="completeTask(task)"
					/>
				</div>
				<div class="crm-tasks__body">
					<div class="crm-tasks__title">{{ task.title }}</div>
					<div class="crm-tasks__meta">
						<span v-if="task.dueAt" class="crm-tasks__due" :class="{ 'crm-tasks__due--overdue': isOverdue(task) }">
							<CalendarAlertIcon v-if="isOverdue(task)" :size="12" />
							<CalendarOutlineIcon v-else :size="12" />
							{{ formatDue(task.dueAt) }}
						</span>
						<span v-if="task.notes" class="crm-tasks__notes">{{ task.notes }}</span>
					</div>
				</div>
			</li>
		</ul>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import CheckboxMarkedOutlineIcon from 'vue-material-design-icons/CheckboxMarkedOutline.vue'
import CalendarOutlineIcon from 'vue-material-design-icons/CalendarOutline.vue'
import CalendarAlertIcon from 'vue-material-design-icons/CalendarAlert.vue'

export default {
	name: 'TasksPanel',
	components: { CheckboxMarkedOutlineIcon, CalendarOutlineIcon, CalendarAlertIcon },
	props: {
		contactId:  { type: Number, required: true },
		refreshKey: { type: Number, default: 0 },
	},
	emits: ['tasks-loaded'],
	data() {
		return { loading: true, tasks: [], completing: null }
	},
	watch: {
		refreshKey() { this.load() },
	},
	async created() { await this.load() },
	methods: {
		t,
		async load() {
			this.loading = true
			try {
				const { data } = await axios.get(
					generateUrl(`/apps/crmhub_nc_app/api/contacts/${this.contactId}/tasks`)
				)
				this.tasks = Array.isArray(data) ? data : []
				const now = Math.floor(Date.now() / 1000)
				this.$emit('tasks-loaded', {
					pending:  this.tasks.filter(t => t.status !== 'done').length,
					overdue:  this.tasks.filter(t => t.status !== 'done' && t.dueAt && t.dueAt < now).length,
				})
			} catch (e) {
				console.error('[Reidel CRM] loadTasks failed', e)
			} finally {
				this.loading = false
			}
		},
		isOverdue(task) {
			if (!task.dueAt || task.status === 'done') return false
			return task.dueAt < Math.floor(Date.now() / 1000)
		},
		formatDue(ts) {
			if (!ts) return ''
			const d = new Date(ts * 1000)
			const today = new Date()
			const diff = Math.floor((d - today) / 86400000)
			if (diff === 0) return t('crmhub_nc_app', 'Today')
			if (diff === 1) return t('crmhub_nc_app', 'Tomorrow')
			if (diff === -1) return t('crmhub_nc_app', 'Yesterday')
			if (diff < 0) return t('crmhub_nc_app', '{days} days overdue', { days: Math.abs(diff) })
			return d.toLocaleDateString()
		},
		async completeTask(task) {
			this.completing = task.id
			try {
				await axios.put(
					generateUrl(`/apps/crmhub_nc_app/api/tasks/${task.id}`),
					{ status: 'done' }
				)
				await this.load()
			} catch (e) {
				showError(t('crmhub_nc_app', 'Failed to complete task'))
			} finally {
				this.completing = null
			}
		},
	},
}
</script>

<style lang="scss" scoped>
@use '../_tokens.scss' as *;

.crm-tasks {
	&__loading { display: flex; flex-direction: column; gap: 8px; }

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
		display: flex;
		align-items: flex-start;
		gap: 10px;
		padding: 7px 4px;
		border-bottom: 1px solid $rd-border-soft;
		&:last-child { border: none; }

		&--overdue .crm-tasks__title { color: #991b1b; }
	}

	&__check input[type="checkbox"] {
		width: 16px;
		height: 16px;
		margin-top: 2px;
		cursor: pointer;
	}

	&__body { flex: 1; min-width: 0; }

	&__title {
		font-size: $rd-font-sm;
		font-weight: 500;
		color: $rd-text;
	}

	&__meta { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 3px; }

	&__due {
		display: flex;
		align-items: center;
		gap: 3px;
		font-size: $rd-font-xs;
		color: $rd-text-muted;

		&--overdue { color: #dc2626; font-weight: 600; }
	}

	&__notes {
		font-size: $rd-font-xs;
		color: $rd-text-muted;
		font-style: italic;
	}
}

// Shared skeleton class
.crm-skeleton {
	border-radius: 6px;
	height: 14px;
	background: linear-gradient(
		90deg,
		var(--color-background-dark, #f0f0f0) 25%,
		var(--color-background-hover, #e0e0e0) 50%,
		var(--color-background-dark, #f0f0f0) 75%
	);
	background-size: 200% 100%;
	animation: shimmer 1.4s ease-in-out infinite;
	&--wide { width: 100%; }
	&--half { width: 60%; }
}
@keyframes shimmer {
	0%   { background-position: 200% 0; }
	100% { background-position: -200% 0; }
}
</style>
