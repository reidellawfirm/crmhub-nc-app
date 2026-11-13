<template>
	<!-- Only render if at least one Deck board exists -->
	<div v-if="boards.length > 0 || loadingBoards" class="deck-picker">
		<label class="deck-picker__label">
			<ViewDashboardOutlineIcon :size="14" />
			{{ t('crmhub_nc_app', 'Deck') }}
		</label>

		<NcSelect
			v-model="selectedBoard"
			:options="boardOptions"
			:loading="loadingBoards"
			:placeholder="t('crmhub_nc_app', 'Select board (optional)')"
			:clearable="true"
			label="label"
			class="deck-picker__select"
			@update:model-value="onBoardChange" />

		<div v-if="selectedBoard" class="deck-picker__card-wrapper">
			<input
				v-model="cardSearch"
				type="search"
				class="deck-picker__card-search"
				:placeholder="t('crmhub_nc_app', 'Filter cards…')">
			<NcSelect
				v-model="selectedCard"
				:options="cardOptions"
				:loading="loadingCards"
				:placeholder="t('crmhub_nc_app', 'Select card (optional)')"
				:clearable="true"
				label="label"
				class="deck-picker__select"
				@update:model-value="onCardChange" />
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import ViewDashboardOutlineIcon from 'vue-material-design-icons/ViewDashboardOutline.vue'
import api from '../api.js'

export default {
	name: 'DeckPicker',
	components: { NcSelect, ViewDashboardOutlineIcon },

	props: {
		/** Currently selected Deck board ID (Number|null) */
		boardId: { type: Number, default: null },
		/** Currently selected Deck card ID (Number|null) */
		cardId: { type: Number, default: null },
	},

	emits: ['update:board-id', 'update:card-id'],

	data() {
		return {
			boards: [],
			stacks: [],         // stacks for the selected board, each with a .cards array
			loadingBoards: true,
			loadingCards: false,
			selectedBoard: null,
			selectedCard: null,
			cardSearch: '',
		}
	},

	computed: {
		boardOptions() {
			return this.boards.map(b => ({ value: b.id, label: b.title, raw: b }))
		},
		allCardOptions() {
			// Flatten all cards across all stacks; prefix card title with stack name
			const cards = []
			for (const stack of this.stacks) {
				for (const card of (stack.cards ?? [])) {
					cards.push({
						value: card.id,
						label: `[${stack.title}] ${card.title}`,
						raw: card,
					})
				}
			}
			return cards
		},
		cardOptions() {
			if (!this.cardSearch.trim()) return this.allCardOptions
			const q = this.cardSearch.toLowerCase()
			return this.allCardOptions.filter(o => o.label.toLowerCase().includes(q))
		},
	},

	async mounted() {
		this.boards = await api.deck.boards()
		this.loadingBoards = false

		// Restore pre-selected board/card from prop values
		if (this.boardId) {
			this.selectedBoard = this.boardOptions.find(o => o.value === this.boardId) ?? null
			if (this.selectedBoard) {
				await this.loadCards()
				if (this.cardId) {
					this.selectedCard = this.cardOptions.find(o => o.value === this.cardId) ?? null
				}
			}
		}
	},

	methods: {
		t,

		async onBoardChange(option) {
			// Emit null when the board is cleared
			const id = option?.value ?? null
			this.$emit('update:board-id', id)
			// Clear card selection whenever board changes
			this.selectedCard = null
			this.$emit('update:card-id', null)
			this.stacks = []
			if (id !== null) {
				await this.loadCards()
			}
		},

		onCardChange(option) {
			this.$emit('update:card-id', option?.value ?? null)
		},

		async loadCards() {
			const boardId = this.selectedBoard?.value
			if (!boardId) return
			this.loadingCards = true
			this.stacks = await api.deck.stacks(boardId)
			this.loadingCards = false
		},
	},
}
</script>

<style lang="scss" scoped>
.deck-picker {
	display: flex;
	flex-direction: column;
	gap: 4px;

	&__label {
		display: inline-flex;
		align-items: center;
		gap: 4px;
		font-size: 0.75rem;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.4px;
		color: var(--color-text-maxcontrast);
		margin-bottom: 2px;
	}

	&__select {
		width: 100%;
	}

	&__card-wrapper {
		display: flex;
		flex-direction: column;
		gap: 4px;
	}

	&__card-search {
		width: 100%;
		padding: 5px 10px;
		border: 1px solid var(--color-border, #ddd);
		border-radius: var(--border-radius, 4px);
		font-size: 0.875rem;
		color: var(--color-main-text);
		background: var(--color-main-background);

		&:focus {
			outline: none;
			border-color: var(--color-primary, #0082c9);
			box-shadow: 0 0 0 3px rgba(0, 130, 201, 0.15);
		}
	}
}
</style>
