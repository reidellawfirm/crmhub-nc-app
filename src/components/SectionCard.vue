<template>
	<section class="rd-card">
		<header class="rd-card__header">
			<div class="rd-card__title">
				<slot name="icon" />
				<h2>{{ title }}</h2>
				<span v-if="count !== null" class="rd-card__count">{{ count }}</span>
			</div>
			<div class="rd-card__actions">
				<slot name="actions" />
			</div>
		</header>
		<div class="rd-card__body">
			<slot />
		</div>
	</section>
</template>

<script>
export default {
	name: 'SectionCard',
	props: {
		title: { type: String, required: true },
		count: { type: [Number, String, null], default: null },
	},
}
</script>

<style lang="scss" scoped>
@use './_tokens.scss' as *;

.rd-card {
	background: $rd-bg-card;
	border: 1px solid $rd-border;
	border-radius: $rd-radius-lg;
	box-shadow: $rd-shadow-sm;
	overflow: hidden;

	& + & { margin-top: $rd-space-4; }

	&__header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: $rd-space-3;
		padding: $rd-space-4 $rd-space-5;
		border-bottom: 1px solid $rd-border-soft;
	}

	&__title {
		display: flex;
		align-items: center;
		gap: $rd-space-2;
		min-width: 0;

		h2 {
			margin: 0;
			font-size: $rd-font-md;
			font-weight: 600;
			color: $rd-text;
			letter-spacing: 0.2px;
			text-transform: uppercase;
		}

		:deep(.material-design-icon) {
			color: $rd-primary;
			display: inline-flex;
		}
	}

	&__count {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-width: 22px;
		height: 22px;
		padding: 0 6px;
		font-size: $rd-font-xs;
		font-weight: 600;
		color: $rd-text-muted;
		background: $rd-bg-page;
		border-radius: 999px;
	}

	&__actions {
		display: flex;
		gap: $rd-space-2;
		flex-shrink: 0;
	}

	&__body {
		padding: $rd-space-4 $rd-space-5;
	}
}
</style>
