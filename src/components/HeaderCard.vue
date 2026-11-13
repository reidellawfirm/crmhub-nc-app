<template>
	<section class="rd-header-card">
		<div class="rd-header-card__main">
			<div class="rd-avatar" :style="avatarStyle" :title="displayName">
				{{ initials }}
			</div>
			<div class="rd-header-card__identity">
				<h1 class="rd-header-card__name">{{ displayName }}</h1>
				<div class="rd-header-card__meta">
					<span v-if="email" class="rd-meta-item">
						<EmailOutlineIcon :size="16" />
						<span>{{ email }}</span>
					</span>
					<span v-if="phone" class="rd-meta-item">
						<PhoneOutlineIcon :size="16" />
						<span>{{ phone }}</span>
					</span>
					<span class="rd-status-badge" :class="`rd-status-badge--${statusKey}`">
						{{ statusLabel }}
					</span>
				</div>
			</div>
		</div>
		<div class="rd-header-card__actions">
			<slot name="actions" />
		</div>
	</section>
</template>

<script>
import EmailOutlineIcon from 'vue-material-design-icons/EmailOutline.vue'
import PhoneOutlineIcon from 'vue-material-design-icons/PhoneOutline.vue'

export default {
	name: 'HeaderCard',
	components: { EmailOutlineIcon, PhoneOutlineIcon },
	props: {
		firstName: { type: String, default: '' },
		lastName: { type: String, default: '' },
		email: { type: String, default: '' },
		phone: { type: String, default: '' },
		status: { type: String, default: 'active' },
		fallbackName: { type: String, default: '' },
	},
	computed: {
		displayName() {
			const composed = [this.firstName, this.lastName].filter(Boolean).join(' ').trim()
			return composed || this.fallbackName || this.email || '—'
		},
		initials() {
			const a = (this.firstName || '').trim()[0] || ''
			const b = (this.lastName || '').trim()[0] || ''
			const composed = (a + b).toUpperCase()
			if (composed) return composed
			const fallback = (this.displayName || '?').replace(/[^A-Za-z0-9]/g, '')
			return (fallback[0] || '?').toUpperCase()
		},
		avatarStyle() {
			// Deterministic hue from name for a friendly accent.
			const seed = (this.displayName || '?').split('')
				.reduce((acc, c) => acc + c.charCodeAt(0), 0)
			const hue = seed % 360
			return {
				background: `linear-gradient(135deg, hsl(${hue}, 60%, 55%), hsl(${(hue + 40) % 360}, 60%, 45%))`,
			}
		},
		statusKey() {
			const s = (this.status || '').toLowerCase()
			return ['active', 'prospect', 'archived', 'inactive'].includes(s) ? s : 'active'
		},
		statusLabel() {
			return this.statusKey.charAt(0).toUpperCase() + this.statusKey.slice(1)
		},
	},
}
</script>

<style lang="scss" scoped>
@use './_tokens.scss' as *;

.rd-header-card {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: $rd-space-4;
	padding: $rd-space-5 $rd-space-6;
	background: $rd-bg-card;
	border: 1px solid $rd-border;
	border-radius: $rd-radius-lg;
	box-shadow: $rd-shadow-sm;

	&__main {
		display: flex;
		align-items: center;
		gap: $rd-space-4;
		min-width: 0;
		flex: 1 1 320px;
	}

	&__identity { min-width: 0; }

	&__name {
		margin: 0 0 $rd-space-2 0;
		font-size: $rd-font-2xl;
		font-weight: 600;
		line-height: 1.2;
		color: $rd-text;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__meta {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: $rd-space-3;
		color: $rd-text-muted;
		font-size: $rd-font-sm;
	}

	&__actions {
		display: flex;
		flex-wrap: wrap;
		gap: $rd-space-2;
	}
}

.rd-avatar {
	width: 64px;
	height: 64px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	color: #fff;
	font-weight: 600;
	font-size: $rd-font-xl;
	letter-spacing: 0.5px;
	flex-shrink: 0;
	box-shadow: $rd-shadow-sm;
}

.rd-meta-item {
	display: inline-flex;
	align-items: center;
	gap: $rd-space-1;

	:deep(.material-design-icon) {
		display: inline-flex;
		color: $rd-text-muted;
	}
}

.rd-status-badge {
	display: inline-flex;
	align-items: center;
	padding: 2px 10px;
	border-radius: 999px;
	font-size: $rd-font-xs;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.4px;
	color: #fff;

	&--active   { background: $rd-status-active; }
	&--prospect { background: $rd-status-prospect; }
	&--archived { background: $rd-status-archived; }
	&--inactive { background: $rd-status-inactive; }
}

@media (max-width: 640px) {
	.rd-header-card { padding: $rd-space-4; }
	.rd-avatar { width: 48px; height: 48px; font-size: $rd-font-lg; }
	.rd-header-card__name { font-size: $rd-font-xl; }
}
</style>
