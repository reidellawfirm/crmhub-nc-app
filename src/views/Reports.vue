<template>
	<div class="rd-reports">
		<header class="rd-reports__header">
			<div class="rd-reports__header-left">
				<h1 class="rd-reports__title">{{ t('crmhub_nc_app', 'Reports & Exports') }}</h1>
				<span class="rd-reports__sub">{{ t('crmhub_nc_app', 'Download your CRM data as CSV files') }}</span>
			</div>
			<button class="rd-back-btn" @click="$router.push({ name: 'home' })">
				← {{ t('crmhub_nc_app', 'Dashboard') }}
			</button>
		</header>

		<!-- Export cards -->
		<section class="rd-reports__section">
			<h2 class="rd-reports__section-title">{{ t('crmhub_nc_app', 'Data Exports') }}</h2>
			<div class="rd-export-grid">
				<div class="rd-export-card">
					<div class="rd-export-card__icon rd-export-card__icon--blue">
						<AccountGroupOutlineIcon :size="28" />
					</div>
					<div class="rd-export-card__body">
						<h3 class="rd-export-card__title">{{ t('crmhub_nc_app', 'Contacts') }}</h3>
						<p class="rd-export-card__desc">
							{{ t('crmhub_nc_app', 'Export all contacts with email, phone, organization, and status.') }}
						</p>
					</div>
					<button class="rd-export-btn rd-export-btn--blue" @click="downloadContacts">
						<DownloadOutlineIcon :size="16" />
						{{ t('crmhub_nc_app', 'Download CSV') }}
					</button>
				</div>

				<div class="rd-export-card">
					<div class="rd-export-card__icon rd-export-card__icon--green">
						<FolderMultipleOutlineIcon :size="28" />
					</div>
					<div class="rd-export-card__body">
						<h3 class="rd-export-card__title">{{ t('crmhub_nc_app', 'Projects') }}</h3>
						<p class="rd-export-card__desc">
							{{ t('crmhub_nc_app', 'Export all projects with status, assigned user, due dates, and pipeline stage.') }}
						</p>
					</div>
					<button class="rd-export-btn rd-export-btn--green" @click="downloadProjects">
						<DownloadOutlineIcon :size="16" />
						{{ t('crmhub_nc_app', 'Download CSV') }}
					</button>
				</div>

				<div class="rd-export-card">
					<div class="rd-export-card__icon rd-export-card__icon--purple">
						<ClockOutlineIcon :size="28" />
					</div>
					<div class="rd-export-card__body">
						<h3 class="rd-export-card__title">{{ t('crmhub_nc_app', 'Activities') }}</h3>
						<p class="rd-export-card__desc">
							{{ t('crmhub_nc_app', 'Export the full activity log with type, summary, and timestamps.') }}
						</p>
					</div>
					<button class="rd-export-btn rd-export-btn--purple" @click="downloadActivities">
						<DownloadOutlineIcon :size="16" />
						{{ t('crmhub_nc_app', 'Download CSV') }}
					</button>
				</div>
			</div>
		</section>

		<!-- Webhook section -->
		<section class="rd-reports__section">
			<h2 class="rd-reports__section-title">{{ t('crmhub_nc_app', 'Webhook Endpoints') }}</h2>
			<p class="rd-reports__section-desc">
				{{ t('crmhub_nc_app', 'Configure HTTPS endpoints to receive real-time CRM events. A HMAC-SHA256 signature is sent in X-CRM-Signature when a secret is set.') }}
			</p>

			<div v-if="wLoading" class="rd-loading">{{ t('crmhub_nc_app', 'Loading webhooks…') }}</div>

			<div v-else class="rd-webhook-list">
				<div v-if="webhooks.length === 0" class="rd-webhook-empty">
					{{ t('crmhub_nc_app', 'No webhooks configured yet.') }}
				</div>
				<div v-for="wh in webhooks" :key="wh.id" class="rd-webhook-row">
					<div class="rd-webhook-row__info">
						<span class="rd-webhook-row__label">{{ wh.label || 'Webhook' }}</span>
						<span class="rd-webhook-row__url">{{ wh.url }}</span>
						<div class="rd-webhook-row__tags">
							<span
								v-for="ev in wh.events"
								:key="ev"
								class="rd-tag">{{ ev }}</span>
							<span
								class="rd-webhook-status"
								:class="wh.isActive ? 'rd-webhook-status--active' : 'rd-webhook-status--inactive'">
								{{ wh.isActive ? t('crmhub_nc_app', 'Active') : t('crmhub_nc_app', 'Inactive') }}
							</span>
						</div>
					</div>
					<button class="rd-webhook-row__del" :title="t('crmhub_nc_app', 'Delete')" @click="deleteWebhook(wh.id)">
						<TrashCanOutlineIcon :size="16" />
					</button>
				</div>

				<!-- Add webhook form -->
				<div class="rd-webhook-add">
					<h3 class="rd-webhook-add__title">{{ t('crmhub_nc_app', 'Add webhook') }}</h3>
					<div class="rd-form-row">
						<label>{{ t('crmhub_nc_app', 'Label') }}</label>
						<input v-model="newWh.label" type="text" class="rd-input" :placeholder="t('crmhub_nc_app', 'My webhook')" />
					</div>
					<div class="rd-form-row">
						<label>{{ t('crmhub_nc_app', 'HTTPS URL') }}</label>
						<input v-model="newWh.url" type="url" class="rd-input" :placeholder="t('crmhub_nc_app', 'https://example.com/hook')" />
					</div>
					<div class="rd-form-row">
						<label>{{ t('crmhub_nc_app', 'Events (comma-separated, or * for all)') }}</label>
						<input v-model="newWh.eventsRaw" type="text" class="rd-input" :placeholder="t('crmhub_nc_app', 'contact_created,project_created')" />
					</div>
					<div class="rd-form-row">
						<label>{{ t('crmhub_nc_app', 'Secret (optional)') }}</label>
						<input v-model="newWh.secret" type="password" class="rd-input" autocomplete="off" />
					</div>
					<button
						class="rd-export-btn rd-export-btn--blue"
						:disabled="wSaving || !newWh.url"
						@click="saveWebhook">
						{{ wSaving ? t('crmhub_nc_app', 'Saving…') : t('crmhub_nc_app', 'Add webhook') }}
					</button>
				</div>
			</div>
		</section>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import AccountGroupOutlineIcon from 'vue-material-design-icons/AccountGroupOutline.vue'
import ClockOutlineIcon from 'vue-material-design-icons/ClockOutline.vue'
import DownloadOutlineIcon from 'vue-material-design-icons/DownloadOutline.vue'
import FolderMultipleOutlineIcon from 'vue-material-design-icons/FolderMultipleOutline.vue'
import TrashCanOutlineIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import api from '../api.js'

export default {
	name: 'Reports',
	components: {
		AccountGroupOutlineIcon,
		ClockOutlineIcon,
		DownloadOutlineIcon,
		FolderMultipleOutlineIcon,
		TrashCanOutlineIcon,
	},
	data() {
		return {
			webhooks: [],
			wLoading: false,
			wSaving: false,
			newWh: { label: '', url: '', eventsRaw: '*', secret: '' },
		}
	},
	mounted() {
		this.loadWebhooks()
	},
	methods: {
		t,
		downloadContacts() {
			window.location.href = api.export.contactsUrl()
		},
		downloadProjects() {
			window.location.href = api.export.projectsUrl()
		},
		downloadActivities() {
			window.location.href = api.export.activitiesUrl()
		},
		async loadWebhooks() {
			this.wLoading = true
			try {
				this.webhooks = await api.webhooks.list()
			} catch {
				// silently skip — webhooks table may not exist yet
			} finally {
				this.wLoading = false
			}
		},
		async saveWebhook() {
			if (!this.newWh.url || !this.newWh.url.startsWith('https://')) {
				showError(t('crmhub_nc_app', 'URL must start with https://'))
				return
			}
			this.wSaving = true
			try {
				const events = this.newWh.eventsRaw
					.split(',')
					.map(s => s.trim())
					.filter(Boolean)
				const wh = await api.webhooks.create({
					label: this.newWh.label || 'Webhook',
					url: this.newWh.url,
					events,
					secret: this.newWh.secret || null,
				})
				this.webhooks.unshift(wh)
				this.newWh = { label: '', url: '', eventsRaw: '*', secret: '' }
				showSuccess(t('crmhub_nc_app', 'Webhook saved'))
			} catch {
				showError(t('crmhub_nc_app', 'Failed to save webhook'))
			} finally {
				this.wSaving = false
			}
		},
		async deleteWebhook(id) {
			try {
				await api.webhooks.remove(id)
				this.webhooks = this.webhooks.filter(w => w.id !== id)
				showSuccess(t('crmhub_nc_app', 'Webhook deleted'))
			} catch {
				showError(t('crmhub_nc_app', 'Failed to delete webhook'))
			}
		},
	},
}
</script>

<style lang="scss" scoped>
@use '../components/_tokens.scss' as *;

.rd-reports {
	display: flex;
	flex-direction: column;
	gap: $rd-space-6;
	padding: $rd-space-5 $rd-space-6;
	max-width: 960px;
	margin: 0 auto;
	background: $rd-bg-page;
	min-height: 100%;
}

.rd-reports__header {
	display: flex;
	align-items: flex-start;
	gap: $rd-space-4;
	justify-content: space-between;
}

.rd-reports__header-left {
	display: flex;
	flex-direction: column;
	gap: $rd-space-1;
}

.rd-reports__title {
	margin: 0;
	font-size: $rd-font-xl;
	font-weight: 700;
	color: $rd-text;
}

.rd-reports__sub {
	font-size: $rd-font-sm;
	color: $rd-text-muted;
}

.rd-back-btn {
	all: unset;
	cursor: pointer;
	font-size: $rd-font-sm;
	color: $rd-primary;
	padding: $rd-space-2 $rd-space-3;
	border-radius: $rd-radius-sm;
	transition: background 100ms ease;
	&:hover { background: $rd-primary-soft; }
}

.rd-reports__section {
	display: flex;
	flex-direction: column;
	gap: $rd-space-4;
}

.rd-reports__section-title {
	margin: 0;
	font-size: $rd-font-lg;
	font-weight: 600;
	color: $rd-text;
}

.rd-reports__section-desc {
	margin: 0;
	font-size: $rd-font-sm;
	color: $rd-text-muted;
	max-width: 600px;
}

.rd-export-grid {
	display: flex;
	flex-direction: column;
	gap: $rd-space-3;
}

.rd-export-card {
	display: flex;
	align-items: center;
	gap: $rd-space-4;
	padding: $rd-space-4 $rd-space-5;
	background: $rd-bg-card;
	border: 1px solid $rd-border;
	border-radius: $rd-radius-lg;
	box-shadow: $rd-shadow-sm;

	@media (max-width: 600px) {
		flex-direction: column;
		align-items: flex-start;
	}
}

.rd-export-card__icon {
	flex-shrink: 0;
	width: 52px;
	height: 52px;
	border-radius: $rd-radius-md;
	display: flex;
	align-items: center;
	justify-content: center;

	:deep(.material-design-icon) { display: inline-flex; }

	&--blue   { background: #dbeafe; color: #2563eb; :deep(.material-design-icon) { color: #2563eb; } }
	&--green  { background: #d1fae5; color: #059669; :deep(.material-design-icon) { color: #059669; } }
	&--purple { background: #ede9fe; color: #7c3aed; :deep(.material-design-icon) { color: #7c3aed; } }
}

.rd-export-card__body {
	flex: 1;
	min-width: 0;
}

.rd-export-card__title {
	margin: 0 0 4px;
	font-size: $rd-font-md;
	font-weight: 600;
	color: $rd-text;
}

.rd-export-card__desc {
	margin: 0;
	font-size: $rd-font-sm;
	color: $rd-text-muted;
}

.rd-export-btn {
	all: unset;
	display: inline-flex;
	align-items: center;
	gap: $rd-space-2;
	padding: $rd-space-2 $rd-space-4;
	border-radius: $rd-radius-md;
	font-size: $rd-font-sm;
	font-weight: 600;
	cursor: pointer;
	white-space: nowrap;
	flex-shrink: 0;
	transition: opacity 120ms ease;

	:deep(.material-design-icon) { display: inline-flex; }

	&:disabled { opacity: 0.5; pointer-events: none; }

	&--blue   { background: #2563eb; color: #fff; }
	&--green  { background: #059669; color: #fff; }
	&--purple { background: #7c3aed; color: #fff; }

	&:hover { opacity: 0.88; }
}

// ── Webhooks ─────────────────────────────────────────────────────────────────

.rd-loading {
	color: $rd-text-muted;
	font-size: $rd-font-sm;
}

.rd-webhook-list {
	display: flex;
	flex-direction: column;
	gap: $rd-space-3;
}

.rd-webhook-empty {
	color: $rd-text-muted;
	font-size: $rd-font-sm;
}

.rd-webhook-row {
	display: flex;
	align-items: center;
	gap: $rd-space-4;
	padding: $rd-space-3 $rd-space-4;
	background: $rd-bg-card;
	border: 1px solid $rd-border;
	border-radius: $rd-radius-md;
}

.rd-webhook-row__info {
	display: flex;
	flex-direction: column;
	gap: $rd-space-1;
	flex: 1;
	min-width: 0;
}

.rd-webhook-row__label {
	font-size: $rd-font-sm;
	font-weight: 600;
	color: $rd-text;
}

.rd-webhook-row__url {
	font-size: $rd-font-xs;
	color: $rd-text-muted;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.rd-webhook-row__tags {
	display: flex;
	flex-wrap: wrap;
	gap: $rd-space-1;
	margin-top: 2px;
}

.rd-webhook-row__del {
	all: unset;
	cursor: pointer;
	padding: $rd-space-2;
	border-radius: $rd-radius-sm;
	color: $rd-text-muted;
	transition: color 100ms ease, background 100ms ease;

	:deep(.material-design-icon) { display: inline-flex; }

	&:hover { color: #ef4444; background: #fee2e2; }
}

.rd-webhook-status {
	font-size: $rd-font-xs;
	padding: 1px 8px;
	border-radius: 999px;
	font-weight: 600;

	&--active   { background: #d1fae5; color: #059669; }
	&--inactive { background: #fee2e2; color: #dc2626; }
}

.rd-webhook-add {
	padding: $rd-space-4;
	background: $rd-bg-card;
	border: 1px solid $rd-border;
	border-radius: $rd-radius-lg;
	display: flex;
	flex-direction: column;
	gap: $rd-space-3;
}

.rd-webhook-add__title {
	margin: 0;
	font-size: $rd-font-md;
	font-weight: 600;
	color: $rd-text;
}

.rd-form-row {
	display: flex;
	flex-direction: column;
	gap: $rd-space-1;

	label {
		font-size: $rd-font-xs;
		font-weight: 600;
		color: $rd-text-muted;
		text-transform: uppercase;
		letter-spacing: 0.4px;
	}
}

.rd-input {
	width: 100%;
	box-sizing: border-box;
	padding: $rd-space-2 $rd-space-3;
	border-radius: $rd-radius-sm;
	border: 1px solid $rd-border;
	background: $rd-bg-page;
	color: $rd-text;
	font-size: $rd-font-sm;

	&:focus {
		outline: 2px solid $rd-primary;
		outline-offset: 2px;
	}
}

.rd-tag {
	font-size: $rd-font-xs;
	padding: 1px 8px;
	border-radius: 999px;
	background: $rd-bg-page;
	color: $rd-text-muted;
	text-transform: capitalize;
	border: 1px solid $rd-border;
}
</style>
