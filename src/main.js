import { createApp } from 'vue'
import { createRouter, createWebHashHistory } from 'vue-router'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import { loadState } from '@nextcloud/initial-state'
import { imagePath } from '@nextcloud/router'

import App from './App.vue'
import Home from './views/Home.vue'
import ContactDetail from './views/ContactDetail.vue'
import ProjectDetail from './views/ProjectDetail.vue'
import ActivityFeed from './views/ActivityFeed.vue'
import Reports from './views/Reports.vue'

const router = createRouter({
	history: createWebHashHistory(),
	routes: [
		{ path: '/', name: 'home', component: Home },
		// Back-compat: old /contacts links land on the home/empty-state.
		{ path: '/contacts', redirect: '/' },
		{ path: '/contacts/:id', name: 'contact', component: ContactDetail, props: true },
		{ path: '/projects/:id', name: 'project', component: ProjectDetail, props: true },
		{ path: '/activity', name: 'activity', component: ActivityFeed },
		{ path: '/reports', name: 'reports', component: Reports },
		// --- Hugo Preview Routes ---
		{ path: '/hugo/projects', name: 'hugo-projects', component: () => import('./views/HugoProjects.vue') },
		{ path: '/hugo/projects/:id', name: 'hugo-project-detail', component: () => import('./views/HugoProjectDetail.vue'), props: true },
		{ path: '/hugo/preview/:id', name: 'hugo-preview', component: () => import('./views/HugoPreview.vue'), props: true },
	],
})

router.afterEach((to) => {
	if (to.path.startsWith('/hugo')) {
		document.title = 'Hugo Preview - Nextcloud'
	} else {
		document.title = 'Reidel CRM - Nextcloud'
	}
	
	// Update favicon to the app icon
	let link = document.querySelector("link[rel~='icon']");
	if (!link) {
		link = document.createElement('link');
		link.rel = 'icon';
		document.head.appendChild(link);
	}
	link.href = imagePath('crmhub_nc_app', 'favicon.svg');
})

const app = createApp(App)
app.config.globalProperties.t = t
app.config.globalProperties.n = n
app.use(router)
app.mount('#reidel-crm-app')

const startRoute = loadState('crmhub_nc_app', 'start_route')
if (startRoute) {
	// Only force the start route if we are at the root without a specific hash path.
	// This prevents the page from jumping back to projects if you refresh while on a preview.
	if (!window.location.hash || window.location.hash === '#' || window.location.hash === '#/') {
		router.push(startRoute)
	}
}
