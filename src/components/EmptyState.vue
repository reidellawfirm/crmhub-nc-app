<template>
	<div class="rd-empty">
		<div class="rd-empty__icon">
			<slot name="icon" />
		</div>
		<p class="rd-empty__message">{{ message }}</p>
		<p v-if="hint" class="rd-empty__hint">{{ hint }}</p>
	</div>
</template>

<script>
export default {
	name: 'EmptyState',
	props: {
		message: { type: String, required: true },
		hint: { type: String, default: '' },
	},
}
</script>

<style lang="scss" scoped>
@use './_tokens.scss' as *;

.rd-empty {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: $rd-space-6 $rd-space-4;
	color: $rd-text-muted;
	text-align: center;

	&__icon :deep(.material-design-icon) {
		color: $rd-text-muted;
		opacity: 0.6;
		display: inline-flex;
	}

	&__message {
		margin: $rd-space-2 0 0 0;
		font-size: $rd-font-md;
		font-weight: 500;
		color: $rd-text;
	}

	&__hint {
		margin: $rd-space-1 0 0 0;
		font-size: $rd-font-sm;
	}
}
</style>
