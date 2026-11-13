<template>
	<div class="rd-notes-card">
		<!-- ── Add note form ── -->
		<form class="rd-note-form" @submit.prevent="submit">
			<NcTextArea
				v-model="draft"
				:label="t('crmhub_nc_app', 'Add a note')"
				:placeholder="t('crmhub_nc_app', 'Write a note about this contact…')"
				:rows="3"
			/>
			<div class="rd-note-form__actions">
				<NcButton type="primary" native-type="submit" :disabled="!draft.trim() || saving">
					<template #icon><ContentSaveOutlineIcon :size="16" /></template>
					{{ saving ? t('crmhub_nc_app', 'Saving…') : t('crmhub_nc_app', 'Save Note') }}
				</NcButton>
			</div>
		</form>

		<!-- ── Notes list ── -->
		<div v-if="sortedNotes.length" class="rd-notes">
			<div v-for="n in sortedNotes" :key="n.id" class="rd-note">
				<div class="rd-note__avatar">{{ noteInitials(n) }}</div>
				<div class="rd-note__body">
					<div class="rd-note__head">
						<span class="rd-note__author">{{ noteAuthor(n) || t('crmhub_nc_app', 'Unknown') }}</span>
						<span class="rd-note__time">{{ noteDate(n) }}</span>
					</div>
					<p class="rd-note__content">{{ noteText(n) }}</p>
				</div>
			</div>
		</div>

		<!-- Empty state -->
		<p v-else class="rd-notes-empty">
			{{ t('crmhub_nc_app', 'No notes yet. Add the first note above.') }}
		</p>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import ContentSaveOutlineIcon from 'vue-material-design-icons/ContentSaveOutline.vue'

export default {
	name: 'NotesCard',
	components: { NcButton, NcTextArea, ContentSaveOutlineIcon },

	props: {
		notes:  { type: Array,   default: () => [] },
		// inline kept for backward compat — no longer changes rendering
		inline: { type: Boolean, default: false },
	},
	emits: ['add-note'],

	data() {
		return { draft: '', saving: false }
	},

	computed: {
		sortedNotes() {
			const stamp = (n) => {
				const v = n.createdAt ?? n.created_at ?? n.createdAtTs ?? n.timestamp ?? n.date ?? 0
				if (!v) return 0
				return typeof v === 'string' ? new Date(v).getTime() : Number(v) * 1000
			}
			return [...this.notes].sort((a, b) => stamp(b) - stamp(a))
		},
	},

	methods: {
		t,

		noteAuthor(n) {
			return n.authorUserId || n.author || n.authorName || n.userId || n.user_id || n.createdBy || ''
		},

		noteText(n) {
			return n.content || n.body || n.text || n.note || n.message || ''
		},

		noteDate(n) {
			const v = n.createdAt ?? n.created_at ?? n.createdAtTs ?? n.timestamp ?? n.date ?? null
			if (!v) return ''
			const d = typeof v === 'string' ? new Date(v) : new Date(Number(v) * 1000)
			return isNaN(d.getTime()) ? String(v) : d.toLocaleString()
		},

		noteInitials(n) {
			return (this.noteAuthor(n) || '?').slice(0, 2).toUpperCase()
		},

		async submit() {
			const content = this.draft.trim()
			if (!content || this.saving) return
			this.saving = true
			try {
				this.$emit('add-note', content)
				this.draft = ''
			} finally {
				// Parent's API call is async; reset saving after a brief moment
				setTimeout(() => { this.saving = false }, 1500)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
@use './_tokens.scss' as *;

.rd-notes-card {
	display: flex;
	flex-direction: column;
	gap: $rd-space-3;
}

/* ── Add note form ─────────────────────────────────────────────── */
.rd-note-form {
	display: flex;
	flex-direction: column;
	gap: $rd-space-2;

	&__actions {
		display: flex;
		justify-content: flex-end;
	}
}

/* ── Notes list ────────────────────────────────────────────────── */
.rd-notes {
	display: flex;
	flex-direction: column;
	gap: $rd-space-3;
}

.rd-notes-empty {
	color: $rd-text-muted;
	font-size: $rd-font-sm;
	font-style: italic;
	text-align: center;
	padding: $rd-space-3 0;
	margin: 0;
}

/* ── Single note card ──────────────────────────────────────────── */
.rd-note {
	display: flex;
	gap: $rd-space-3;
	padding: $rd-space-3;
	background: $rd-bg-page;
	border-radius: $rd-radius-md;
	border: 1px solid $rd-border-soft;

	&__avatar {
		width: 32px;
		height: 32px;
		min-width: 32px;
		border-radius: 50%;
		background: $rd-primary;
		color: #fff;
		font-size: $rd-font-xs;
		font-weight: 700;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	&__body {
		flex: 1;
		min-width: 0;
	}

	&__head {
		display: flex;
		justify-content: space-between;
		align-items: baseline;
		gap: $rd-space-2;
		margin-bottom: 4px;
		flex-wrap: wrap;
	}

	&__author {
		font-size: $rd-font-sm;
		font-weight: 600;
		color: $rd-text;
	}

	&__time {
		font-size: $rd-font-xs;
		color: $rd-text-muted;
		white-space: nowrap;
	}

	&__content {
		margin: 0;
		font-size: $rd-font-sm;
		line-height: 1.6;
		color: $rd-text;
		white-space: pre-wrap;
		word-break: break-word;
	}
}
</style>
