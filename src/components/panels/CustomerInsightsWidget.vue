<template>
	<div class="crm-insights">
		<div class="crm-insights__grid">
			<!-- Health Score -->
			<div class="crm-insights__item">
				<div class="crm-insights__value" :class="healthClass">{{ healthScore }}</div>
				<div class="crm-insights__bar">
					<div class="crm-insights__bar-fill" :style="{ width: healthScore + '%' }" :class="healthClass" />
				</div>
				<div class="crm-insights__label">{{ t('crmhub_nc_app', 'Health') }}</div>
			</div>

			<!-- Last Activity -->
			<div class="crm-insights__item">
				<div class="crm-insights__value crm-insights__value--sm">{{ lastActivityDisplay }}</div>
				<div class="crm-insights__label">{{ t('crmhub_nc_app', 'Last activity') }}</div>
			</div>

			<!-- Open Projects -->
			<div class="crm-insights__item">
				<div class="crm-insights__value">{{ openProjects }}</div>
				<div class="crm-insights__label">{{ t('crmhub_nc_app', 'Projects') }}</div>
			</div>

			<!-- Status badge -->
			<div class="crm-insights__item">
				<div class="crm-insights__status" :class="`crm-insights__status--${statusKey}`">
					{{ statusLabel }}
				</div>
				<div class="crm-insights__label">{{ t('crmhub_nc_app', 'Status') }}</div>
			</div>
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'CustomerInsightsWidget',
	props: {
		contact:  { type: Object, required: true },
		activity: { type: Array, default: () => [] },
		projects: { type: Array, default: () => [] },
	},
	computed: {
		healthScore() {
			// Simple score: 0-100 based on recency + project activity
			let score = 50
			const last = this.lastActivityTs
			if (last) {
				const daysSince = (Date.now() / 1000 - last) / 86400
				if (daysSince < 7)  score = 90
				else if (daysSince < 14) score = 75
				else if (daysSince < 30) score = 55
				else if (daysSince < 60) score = 35
				else score = 15
			}
			if (this.openProjects > 0) score = Math.min(100, score + 10)
			return score
		},
		healthClass() {
			if (this.healthScore >= 70) return 'crm-insights__value--high'
			if (this.healthScore >= 40) return 'crm-insights__value--mid'
			return 'crm-insights__value--low'
		},
		lastActivityTs() {
			if (!this.activity.length) return null
			return Math.max(...this.activity.map(a => Number(a.occurredAt || 0)))
		},
		lastActivityDisplay() {
			const ts = this.lastActivityTs
			if (!ts) return t('crmhub_nc_app', 'Never')
			const diff = Math.floor(Date.now() / 1000 - ts)
			if (diff < 3600)   return Math.floor(diff / 60) + 'm'
			if (diff < 86400)  return Math.floor(diff / 3600) + 'h'
			if (diff < 604800) return Math.floor(diff / 86400) + 'd'
			return new Date(ts * 1000).toLocaleDateString()
		},
		openProjects() {
			return this.projects.filter(p => !['archived', 'closed', 'won', 'lost'].includes(p.status)).length
		},
		statusKey() {
			return (this.contact.status || 'unknown').toLowerCase().replace(/\s/g, '-')
		},
		statusLabel() {
			return this.contact.status
				? this.contact.status.charAt(0).toUpperCase() + this.contact.status.slice(1)
				: t('crmhub_nc_app', 'Unknown')
		},
	},
	methods: { t },
}
</script>

<style lang="scss" scoped>
@use '../_tokens.scss' as *;

.crm-insights {
	background: $rd-bg-page;
	border: 1px solid $rd-border-soft;
	border-radius: $rd-radius-md;
	padding: 12px 14px;
	margin: 4px 0 10px;

	&__grid {
		display: grid;
		grid-template-columns: repeat(4, 1fr);
		gap: 8px;
		text-align: center;
	}

	&__item {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 3px;
	}

	&__value {
		font-size: $rd-font-xl;
		font-weight: 700;
		line-height: 1;

		&--sm { font-size: $rd-font-lg; }

		&--high { color: $rd-status-active; }
		&--mid  { color: $rd-status-prospect; }
		&--low  { color: $rd-status-inactive; }
	}

	&__bar {
		width: 100%;
		height: 3px;
		background: $rd-border;
		border-radius: 2px;
		overflow: hidden;
	}

	&__bar-fill {
		height: 100%;
		border-radius: 2px;
		transition: width 0.4s ease;

		&.crm-insights__value--high { background: $rd-status-active; }
		&.crm-insights__value--mid  { background: $rd-status-prospect; }
		&.crm-insights__value--low  { background: $rd-status-inactive; }
	}

	&__label {
		font-size: 10px;
		color: $rd-text-muted;
		text-transform: uppercase;
		letter-spacing: 0.5px;
	}

	&__status {
		display: inline-block;
		padding: 2px 8px;
		border-radius: 999px;
		font-size: $rd-font-xs;
		font-weight: 600;

		&--active    { background: rgba(16,185,129,0.15); color: #065f46; }
		&--prospect  { background: rgba(245,158,11,0.15); color: #92400e; }
		&--archived,
		&--inactive  { background: rgba(156,163,175,0.15); color: #374151; }
		&--lead      { background: rgba(59,130,246,0.12); color: #1d4ed8; }
	}
}
</style>
