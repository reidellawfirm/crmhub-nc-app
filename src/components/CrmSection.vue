<template>
	<div class="crm-sec" :class="{ 'crm-sec--open': isOpen }">
		<button class="crm-sec__hdr" type="button" @click="isOpen = !isOpen">
			<span v-if="icon" class="crm-sec__icon" aria-hidden="true">{{ icon }}</span>
			<span v-else class="crm-sec__icon-slot"><slot name="icon" /></span>
			<span class="crm-sec__title">{{ title }}</span>
			<span
				v-if="badge !== null && badge !== undefined"
				class="crm-sec__badge"
				:class="{ 'crm-sec__badge--warn': badgeWarn }"
			>{{ badge }}</span>
			<span class="crm-sec__chevron" :class="{ 'crm-sec__chevron--open': isOpen }" aria-hidden="true" />
		</button>
		<div v-if="isOpen" class="crm-sec__body">
			<slot />
		</div>
	</div>
</template>

<script>
export default {
	name: 'CrmSection',
	props: {
		title:       { type: String,  required: true },
		icon:        { type: String,  default: '' },
		badge:       { default: null },
		badgeWarn:   { type: Boolean, default: false },
		defaultOpen: { type: Boolean, default: false },
	},
	data() {
		return { isOpen: this.defaultOpen }
	},
}
</script>

<style lang="scss" scoped>
@use './_tokens.scss' as *;

.crm-sec {
	border-bottom: 1px solid var(--color-border, #f3f4f6);
	background: var(--color-main-background, #fff);

	&__hdr {
		display: flex;
		align-items: center;
		gap: 12px;
		width: 100%;
		padding: 14px 20px;
		background: none;
		border: none;
		cursor: pointer;
		text-align: left;
		transition: background 0.15s;

		&:hover  { background: var(--color-background-hover, #f9fafb); }
		&:focus-visible {
			outline: 2px solid rgba($rd-primary, 0.5);
			outline-offset: -2px;
		}
	}

	&__icon, &__icon-slot {
		font-size: 18px; 
		flex-shrink: 0; 
		line-height: 1;
		display: flex;
		align-items: center;
		color: $rd-primary;
		opacity: 0.9;
	}

	&__title {
		flex: 1;
		font-size: 13px;
		font-weight: 700;
		color: var(--color-main-text, #111827);
		letter-spacing: 0.25px;
	}

	&__badge {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-width: 20px;
		height: 20px;
		padding: 0 6px;
		border-radius: 10px;
		font-size: 11px;
		font-weight: 700;
		background: var(--color-background-dark, #f0f0f0);
		color: var(--color-text-lighter, #6b7280);

		&--warn { background: rgba(239,68,68,0.12); color: #dc2626; }
	}

	// CSS-only chevron — no string template needed
	&__chevron {
		width: 16px;
		height: 16px;
		flex-shrink: 0;
		position: relative;

		&::before,
		&::after {
			content: '';
			position: absolute;
			top: 50%;
			width: 7px;
			height: 2px;
			background: var(--color-text-lighter, #9ca3af);
			border-radius: 1px;
			transition: transform 0.2s;
		}
		&::before { left: 1px;  transform: translateY(-50%) rotate(-45deg); }
		&::after  { right: 1px; transform: translateY(-50%) rotate(45deg); }

		&--open::before { transform: translateY(-50%) rotate(45deg); }
		&--open::after  { transform: translateY(-50%) rotate(-45deg); }
	}

	&__body {
		padding: 8px 20px 20px;
	}
}
</style>
