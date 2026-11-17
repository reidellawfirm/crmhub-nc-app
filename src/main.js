import { createApp } from 'vue'
import { createRouter, createWebHashHistory } from 'vue-router'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
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
	],
})

router.afterEach(() => {
    document.title = 'Reidel CRM Hub - Nextcloud'

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
