<template>
	<div class="ci">

		<!-- ── VIEW MODE ──────────────────────────────────────────── -->
		<dl v-if="!editing" class="ci__grid">

			<!-- Status -->
			<div class="ci__row">
				<dt>{{ t('crmhub_nc_app', 'Status') }}</dt>
				<dd><span class="ci__chip" :class="`ci__chip--${contact.status}`">{{ contact.status || '—' }}</span></dd>
			</div>

			<!-- Pipeline Stage -->
			<div class="ci__row">
				<dt>{{ t('crmhub_nc_app', 'Pipeline Stage') }}</dt>
				<dd>
					<span v-if="contact.pipelineStage" class="ci__chip ci__chip--stage">{{ contact.pipelineStage }}</span>
					<span v-else class="ci__empty">—</span>
				</dd>
			</div>

			<!-- Lead Source -->
			<div class="ci__row">
				<dt>{{ t('crmhub_nc_app', 'Lead Source') }}</dt>
				<dd>{{ contact.ghContactId ? 'Groundhogg' : (contact.leadSource || '—') }}</dd>
			</div>

			<!-- Contact Type -->
			<div v-if="contact.contactType" class="ci__row">
				<dt>{{ t('crmhub_nc_app', 'Contact Type') }}</dt>
				<dd>{{ contact.contactType }}</dd>
			</div>

			<!-- Organization -->
			<div class="ci__row">
				<dt>{{ t('crmhub_nc_app', 'Organization') }}</dt>
				<dd>{{ contact.organization || '—' }}</dd>
			</div>

			<!-- Assigned User -->
			<div class="ci__row">
				<dt>{{ t('crmhub_nc_app', 'Assigned To') }}</dt>
				<dd>{{ contact.assignedUserId || '—' }}</dd>
			</div>

			<!-- Phone (quick reference) -->
			<div v-if="contact.phone" class="ci__row">
				<dt>{{ t('crmhub_nc_app', 'Phone') }}</dt>
				<dd><a :href="`tel:${contact.phone}`" class="ci__link">{{ contact.phone }}</a></dd>
			</div>

			<!-- Email (quick reference) -->
			<div v-if="contact.email" class="ci__row">
				<dt>{{ t('crmhub_nc_app', 'Email') }}</dt>
				<dd><a :href="`mailto:${contact.email}`" class="ci__link ci__link--ellipsis">{{ contact.email }}</a></dd>
			</div>

			<!-- Health Score -->
			<div class="ci__row">
				<dt>{{ t('crmhub_nc_app', 'Health Score') }}</dt>
				<dd class="ci__health">
					<span class="ci__health-val" :style="{ color: healthColor }">{{ healthScore }}</span>
					<div class="ci__health-bar">
						<div class="ci__health-fill" :style="{ width: healthScore + '%', background: healthColor }" />
					</div>
				</dd>
			</div>

			<!-- Tags -->
			<div v-if="tags.length" class="ci__row">
				<dt>{{ t('crmhub_nc_app', 'Tags') }}</dt>
				<dd class="ci__tags">
					<span v-for="tag in tags" :key="tag" class="ci__chip ci__chip--tag">{{ tag }}</span>
				</dd>
			</div>

			<!-- Last Activity (passed from parent) -->
			<div v-if="lastActivityAt" class="ci__row">
				<dt>{{ t('crmhub_nc_app', 'Last Activity') }}</dt>
				<dd>{{ formatDate(lastActivityAt) }}</dd>
			</div>

			<!-- Created -->
			<div v-if="contact.createdAt" class="ci__row">
				<dt>{{ t('crmhub_nc_app', 'Created') }}</dt>
				<dd>{{ formatDate(contact.createdAt) }}</dd>
			</div>

			<!-- Updated -->
			<div v-if="contact.updatedAt" class="ci__row">
				<dt>{{ t('crmhub_nc_app', 'Last Updated') }}</dt>
				<dd>{{ formatDate(contact.updatedAt) }}</dd>
			</div>

			<!-- Edit button -->
			<div class="ci__actions">
				<NcButton type="tertiary" @click="startEdit">
					<template #icon><PencilOutlineIcon :size="14" /></template>
					{{ t('crmhub_nc_app', 'Edit CRM Info') }}
				</NcButton>
			</div>
		</dl>

		<!-- ── EDIT MODE ──────────────────────────────────────────── -->
		<div v-else class="ci__edit">

			<div class="ci__field">
				<label class="ci__label">{{ t('crmhub_nc_app', 'Status') }}</label>
				<NcSelect v-model="form.status" :options="statusOptions" :placeholder="t('crmhub_nc_app', 'Select status')" />
			</div>

			<div class="ci__field">
				<label class="ci__label">{{ t('crmhub_nc_app', 'Pipeline Stage') }}</label>
				<NcSelect v-model="form.pipelineStage" :options="stageOptions" :placeholder="t('crmhub_nc_app', 'Select stage')" />
			</div>

			<div class="ci__field">
				<label class="ci__label">{{ t('crmhub_nc_app', 'Lead Source') }}</label>
				<NcTextField v-model="form.leadSource" :placeholder="t('crmhub_nc_app', 'e.g. Website, Referral, Partner')" />
			</div>

			<div class="ci__field">
				<label class="ci__label">{{ t('crmhub_nc_app', 'Contact Type') }}</label>
				<NcSelect v-model="form.contactType" :options="contactTypeOptions" :placeholder="t('crmhub_nc_app', 'Select type')" />
			</div>

			<div class="ci__field">
				<label class="ci__label">{{ t('crmhub_nc_app', 'Organization') }}</label>
				<NcTextField v-model="form.organization" :placeholder="t('crmhub_nc_app', 'Company name')" />
			</div>

			<div class="ci__field">
				<label class="ci__label">{{ t('crmhub_nc_app', 'Assigned To') }}</label>
				<NcTextField v-model="form.assignedUserId" :placeholder="t('crmhub_nc_app', 'Nextcloud username')" />
			</div>

			<div class="ci__edit-actions">
				<NcButton type="tertiary" :disabled="saving" @click="cancelEdit">
					{{ t('crmhub_nc_app', 'Cancel') }}
				</NcButton>
				<NcButton type="primary" :disabled="saving" @click="saveEdit">
					<template #icon><ContentSaveOutlineIcon :size="14" /></template>
					{{ saving ? t('crmhub_nc_app', 'Saving…') : t('crmhub_nc_app', 'Save') }}
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import PencilOutlineIcon from 'vue-material-design-icons/PencilOutline.vue'
import ContentSaveOutlineIcon from 'vue-material-design-icons/ContentSaveOutline.vue'

export default {
	name: 'CrmInfoPanel',
	components: { NcButton, NcSelect, NcTextField, PencilOutlineIcon, ContentSaveOutlineIcon },

	props: {
		contact:        { type: Object, required: true },
		lastActivityAt: { type: Number, default: null },
	},
	emits: ['updated'],

	data() {
		return {
			editing: false,
			saving:  false,
			form:    {},
			statusOptions: [
				{ id: 'lead',      label: t('crmhub_nc_app', 'Lead') },
				{ id: 'prospect',  label: t('crmhub_nc_app', 'Prospect') },
				{ id: 'active',    label: t('crmhub_nc_app', 'Active') },
				{ id: 'inactive',  label: t('crmhub_nc_app', 'Inactive') },
				{ id: 'archived',  label: t('crmhub_nc_app', 'Archived') },
			],
			stageOptions: [
				{ id: 'lead',        label: t('crmhub_nc_app', 'Lead') },
				{ id: 'prospect',    label: t('crmhub_nc_app', 'Prospect') },
				{ id: 'qualified',   label: t('crmhub_nc_app', 'Qualified') },
				{ id: 'proposal',    label: t('crmhub_nc_app', 'Proposal') },
				{ id: 'negotiation', label: t('crmhub_nc_app', 'Negotiation') },
				{ id: 'won',         label: t('crmhub_nc_app', 'Won') },
				{ id: 'lost',        label: t('crmhub_nc_app', 'Lost') },
			],
			contactTypeOptions: [
				{ id: 'customer',  label: t('crmhub_nc_app', 'Customer') },
				{ id: 'lead',      label: t('crmhub_nc_app', 'Lead') },
				{ id: 'partner',   label: t('crmhub_nc_app', 'Partner') },
				{ id: 'vendor',    label: t('crmhub_nc_app', 'Vendor') },
				{ id: 'other',     label: t('crmhub_nc_app', 'Other') },
			],
		}
	},

	computed: {
		tags() {
			return Array.isArray(this.contact.ghTags) ? this.contact.ghTags : []
		},
		healthScore() {
			if (this.contact.healthScore != null) return Math.round(this.contact.healthScore)
			// Derive from last activity if not stored
			if (!this.lastActivityAt) return 20
			const days = Math.floor((Date.now() / 1000 - this.lastActivityAt) / 86400)
			if (days <= 7)  return 90
			if (days <= 30) return 65
			if (days <= 90) return 35
			return 15
		},
		healthColor() {
			const s = this.healthScore
			return s >= 70 ? '#10b981' : s >= 40 ? '#f59e0b' : '#ef4444'
		},
	},

	methods: {
		t,

		formatDate(val) {
			if (!val) return '—'
			const d = typeof val === 'number' ? new Date(val * 1000) : new Date(val)
			return isNaN(d.getTime()) ? String(val) : d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
		},

		startEdit() {
			const opt = (arr, id) => arr.find(o => o.id === id) || null
			this.form = {
				status:        opt(this.statusOptions,      this.contact.status)        || { id: this.contact.status || '', label: this.contact.status || '' },
				pipelineStage: opt(this.stageOptions,       this.contact.pipelineStage) || null,
				contactType:   opt(this.contactTypeOptions, this.contact.contactType)   || null,
				leadSource:    this.contact.leadSource    || '',
				organization:  this.contact.organization  || '',
				assignedUserId: this.contact.assignedUserId || '',
			}
			this.editing = true
		},

		cancelEdit() { this.editing = false },

		async saveEdit() {
			this.saving = true
			try {
				await axios.put(
					generateUrl(`/apps/crmhub_nc_app/api/contacts/${this.contact.id}`),
					{
						status:         this.form.status?.id        || this.form.status        || null,
						pipelineStage:  this.form.pipelineStage?.id || this.form.pipelineStage || null,
						contactType:    this.form.contactType?.id   || this.form.contactType   || null,
						leadSource:     this.form.leadSource     || null,
						organization:   this.form.organization   || null,
						assignedUserId: this.form.assignedUserId || null,
					}
				)
				showSuccess(t('crmhub_nc_app', 'CRM information updated'))
				this.editing = false
				this.$emit('updated')
			} catch {
				showError(t('crmhub_nc_app', 'Failed to update CRM information'))
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>
@use '../_tokens.scss' as *;

.ci {
	font-size: $rd-font-sm;
}

/* ── Data grid ───────────────────────────────────────── */
.ci__grid {
	margin: 0;
	display: flex;
	flex-direction: column;
}

.ci__row {
	display: grid;
	grid-template-columns: 120px 1fr;
	gap: 6px 12px;
	align-items: start;
	padding: 7px 0;
	border-bottom: 1px solid var(--color-border, #f0f0f0);
	&:last-of-type { border: none; }

	dt {
		font-size: 11px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.4px;
		color: var(--color-text-lighter, #9ca3af);
		padding-top: 2px;
		white-space: nowrap;
	}

	dd {
		margin: 0;
		color: var(--color-main-text, #1f2937);
		font-size: $rd-font-sm;
		word-break: break-word;
	}
}

.ci__empty { color: var(--color-text-lighter, #9ca3af); }

/* ── Chips ────────────────────────────────────────────── */
.ci__chip {
	display: inline-flex;
	align-items: center;
	padding: 2px 10px;
	border-radius: 999px;
	font-size: 11px;
	font-weight: 600;
	text-transform: capitalize;

	&--active    { background: rgba(16,185,129,0.15); color: #065f46; }
	&--prospect  { background: rgba(245,158,11,0.15); color: #92400e; }
	&--lead      { background: rgba(59,130,246,0.12); color: #1d4ed8; }
	&--inactive,
	&--archived  { background: rgba(156,163,175,0.15); color: #374151; }
	&--stage     { background: rgba(99,102,241,0.12); color: #4338ca; }
	&--tag       { background: rgba(16,185,129,0.10); color: #047857; font-size: 11px; }
}

/* ── Tags row ─────────────────────────────────────────── */
.ci__tags { display: flex; flex-wrap: wrap; gap: 4px; }

/* ── Links ────────────────────────────────────────────── */
.ci__link {
	color: $rd-primary;
	text-decoration: none;
	&:hover { text-decoration: underline; }

	&--ellipsis {
		display: block;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		max-width: 100%;
	}
}

/* ── Health bar ───────────────────────────────────────── */
.ci__health {
	display: flex;
	align-items: center;
	gap: 8px;
}

.ci__health-val {
	font-weight: 700;
	font-size: 15px;
	min-width: 28px;
}

.ci__health-bar {
	flex: 1;
	height: 5px;
	background: var(--color-border, #e5e7eb);
	border-radius: 3px;
	overflow: hidden;
}

.ci__health-fill {
	height: 100%;
	border-radius: 3px;
	transition: width 0.4s ease;
}

/* ── Actions row ──────────────────────────────────────── */
.ci__actions {
	display: flex;
	justify-content: flex-end;
	padding-top: 6px;
}

/* ── Edit form ────────────────────────────────────────── */
.ci__edit {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.ci__field {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.ci__label {
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.4px;
	color: var(--color-text-lighter, #9ca3af);
}

.ci__edit-actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	padding-top: 4px;
	border-top: 1px solid var(--color-border, #f0f0f0);
}
</style>
