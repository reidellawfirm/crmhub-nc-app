<template>
	<NcContent app-name="crmhub_nc_app">
		<NcAppNavigation>
			<Sidebar />
		</NcAppNavigation>
		<NcAppContent>
			<main class="rd-main">
				<router-view />
			</main>
		</NcAppContent>
	</NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import Sidebar from './components/Sidebar.vue'

export default {
	name: 'App',
	components: { NcContent, NcAppNavigation, NcAppContent, Sidebar }
}
</script>

<style lang="scss">
@use './components/_tokens.scss' as *;

.rd-main {
	background: $rd-bg-page;
	min-height: 100%;
	width: 100%;
}
</style>
