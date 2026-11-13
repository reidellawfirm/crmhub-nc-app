import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import { createApp, h, ref, onMounted } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

const cfg = loadState('crmhub_nc_app', 'crmhub_nc_app-config', {})

const App = {
	setup() {
		// Integrations
		const windmillToken = ref('')
		const ghBaseUrl = ref(cfg.gh_base_url || '')
		const ghApiKey = ref('')
		const archiveSubfolder = ref(cfg.email_archive_subfolder || 'Emails')

		// Files / project folder
		const defaultProjectFolder = ref(cfg.default_project_folder || 'CRM Projects')

		// Contact onboarding
		const onboardingEnabled = ref(cfg.onboarding_enabled === 'yes')
		const onboardingTemplate = ref(cfg.onboarding_project_template || 'New Matter')
		const onboardingType = ref(cfg.onboarding_project_type || '')
		const onboardingAutoFolder = ref(cfg.onboarding_auto_folder === 'yes')
		const onboardingDeckBoard = ref(cfg.onboarding_deck_board || '')

		const status = ref('')

		async function save() {
			const payload = {
				gh_base_url: ghBaseUrl.value,
				email_archive_subfolder: archiveSubfolder.value,
				default_project_folder: defaultProjectFolder.value,
				onboarding_enabled: onboardingEnabled.value ? 'yes' : 'no',
				onboarding_project_template: onboardingTemplate.value,
				onboarding_project_type: onboardingType.value,
				onboarding_auto_folder: onboardingAutoFolder.value ? 'yes' : 'no',
				onboarding_deck_board: onboardingDeckBoard.value,
			}
			if (windmillToken.value) payload.windmill_token = windmillToken.value
			if (ghApiKey.value) payload.gh_api_key = ghApiKey.value
			await axios.put(generateUrl('/apps/crmhub_nc_app/config'), payload)
			status.value = t('crmhub_nc_app', 'Saved')
			windmillToken.value = ''
			ghApiKey.value = ''
		}

		const field = (val, setter, label, opts = {}) =>
			h(NcTextField, {
				value: val.value,
				'onUpdate:value': v => setter(v),
				label: t('crmhub_nc_app', label),
				...opts,
			})

		return () => h('div', { class: 'reidel-admin' }, [
			// ── Integrations ──────────────────────────────────────────────
			h('h2', t('crmhub_nc_app', 'Reidel CRM Hub')),
			h('h3', t('crmhub_nc_app', 'Integrations')),
			field(windmillToken, v => (windmillToken.value = v),
				cfg.windmill_token_set ? 'Replace Windmill token' : 'Windmill token', { type: 'password' }),
			field(ghBaseUrl, v => (ghBaseUrl.value = v), 'Groundhogg base URL'),
			field(ghApiKey, v => (ghApiKey.value = v),
				cfg.gh_api_key_set ? 'Replace Groundhogg API key' : 'Groundhogg API key (user:secret)', { type: 'password' }),
			field(archiveSubfolder, v => (archiveSubfolder.value = v), 'Email archive subfolder name'),

			// ── Files / Project Folders ───────────────────────────────────
			h('h3', t('crmhub_nc_app', 'Project Folders')),
			field(defaultProjectFolder, v => (defaultProjectFolder.value = v),
				'Default parent folder for auto-provisioned project folders'),

			// ── Contact Onboarding ────────────────────────────────────────
			h('h3', t('crmhub_nc_app', 'Contact Onboarding')),
			h(NcCheckboxRadioSwitch, {
				checked: onboardingEnabled.value,
				'onUpdate:checked': v => (onboardingEnabled.value = v),
			}, () => t('crmhub_nc_app', 'Auto-create project when a new contact is added')),
			field(onboardingTemplate, v => (onboardingTemplate.value = v),
				'Default project name template'),
			field(onboardingType, v => (onboardingType.value = v),
				'Default project type'),
			h(NcCheckboxRadioSwitch, {
				checked: onboardingAutoFolder.value,
				'onUpdate:checked': v => (onboardingAutoFolder.value = v),
			}, () => t('crmhub_nc_app', 'Auto-create project folder on contact onboarding')),
			field(onboardingDeckBoard, v => (onboardingDeckBoard.value = v),
				'Deck board ID for onboarding cards (leave blank to skip)'),

			// ── Save ──────────────────────────────────────────────────────
			h(NcButton, { type: 'primary', onClick: save }, () => t('crmhub_nc_app', 'Save')),
			status.value && h('p', { class: 'status' }, status.value),
		])
	},
}

createApp(App).mount('#reidel-crm-admin-settings')
