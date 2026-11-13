<template>
	<div class="crm-section" :class="{ 'crm-section--open': isOpen }">
		<button
			class="crm-section__header"
			:aria-expanded="isOpen"
			@click="toggle"
		>
			<span class="crm-section__icon" :class="`icon-${iconName}`" aria-hidden="true" />
			<span class="crm-section__title">{{ title }}</span>
			<span
				v-if="badge !== null && badge !== undefined"
				class="crm-section__badge"
				:class="{ 'crm-section__badge--warn': badgeWarn }"
			>{{ badge }}</span>
			<span class="crm-section__chevron" :class="{ 'crm-section__chevron--open': isOpen }" aria-hidden="true" />
		</button>

		<!-- Only render slot content after first open (lazy) -->
		<transition name="crm-expand">
			<div v-if="isOpen && hasMounted" class="crm-section__body">
				<slot />
			</div>
		</transition>
	</div>
</template>

<script>
export default {
	name: 'CollapsibleSection',

	props: {
		sectionKey:  { type: String, required: true },
		title:       { type: String, required: true },
		iconName:    { type: String, default: 'menu' },
		badge:       { type: [Number, String], default: null },
		badgeWarn:   { type: Boolean, default: false },
		defaultOpen: { type: Boolean, default: false },
	},

	data() {
		const stored = localStorage.getItem(`crm-section-${this.sectionKey}`)
		const open   = stored !== null ? stored === 'true' : this.defaultOpen
		return {
			isOpen:    open,
			hasMounted: open,
		}
	},

	methods: {
		toggle() {
			this.isOpen = !this.isOpen
			if (this.isOpen) this.hasMounted = true
			try {
				localStorage.setItem(`crm-section-${this.sectionKey}`, String(this.isOpen))
			} catch (_) { /* storage not available */ }
		},
	},
}
</script>

<style lang="scss" scoped>
@use '../_tokens.scss' as *;

.crm-section {
	border-bottom: 1px solid $rd-border-soft;

	&__header {
		display: flex;
		align-items: center;
		gap: 8px;
		width: 100%;
		padding: 10px 4px;
		background: none;
		border: none;
		cursor: pointer;
		color: $rd-text;
		font-size: $rd-font-sm;
		font-weight: 600;
		text-align: left;
		border-radius: $rd-radius-sm;
		transition: background 0.15s;

		&:hover { background: var(--color-background-hover, #f5f5f5); }
		&:focus-visible {
			outline: 2px solid $rd-primary;
			outline-offset: 2px;
		}
	}

	&__icon {
		width: 20px;
		height: 20px;
		flex-shrink: 0;
		opacity: 0.7;
		background-size: 18px;
		background-position: center;
		background-repeat: no-repeat;
	}

	&__title { flex: 1; }

	&__badge {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-width: 20px;
		height: 20px;
		padding: 0 6px;
		border-radius: 10px;
		font-size: $rd-font-xs;
		font-weight: 700;
		background: $rd-bg-page;
		color: $rd-text-muted;

		&--warn {
			background: rgba(239, 68, 68, 0.12);
			color: #dc2626;
		}
	}

	&__chevron {
		width: 16px;
		height: 16px;
		flex-shrink: 0;
		position: relative;

		&::before, &::after {
			content: '';
			position: absolute;
			top: 50%;
			width: 7px;
			height: 2px;
			background: $rd-text-muted;
			border-radius: 1px;
			transition: transform 0.2s;
		}
		&::before { left: 1px;  transform: translateY(-50%) rotate(-45deg); }
		&::after  { right: 1px; transform: translateY(-50%) rotate(45deg); }

		&--open {
			&::before { transform: translateY(-50%) rotate(45deg); }
			&::after  { transform: translateY(-50%) rotate(-45deg); }
		}
	}

	&__body { padding: 4px 0 12px; }
}

// Expand/collapse transition
.crm-expand-enter-active,
.crm-expand-leave-active {
	transition: max-height 0.22s ease, opacity 0.18s ease;
	overflow: hidden;
}
.crm-expand-enter-from,
.crm-expand-leave-to { max-height: 0; opacity: 0; }
.crm-expand-enter-to,
.crm-expand-leave-from { max-height: 2000px; opacity: 1; }
</style>
