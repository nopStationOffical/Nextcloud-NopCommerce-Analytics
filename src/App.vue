<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'

import DashboardView from './components/DashboardView.vue'
import ReportsView from './components/ReportsView.vue'
import ExportsView from './components/ExportsView.vue'
import SettingsView from './components/SettingsView.vue'

type MainTab = 'dashboard' | 'reports' | 'exports' | 'settings'

const currentTab = ref<MainTab>('dashboard')
const currentSubTab = ref<string>('')

const STORAGE_KEY = 'nopstation_analytics_route'

const parseHash = (hash: string): { mainTab: MainTab; subTab: string } => {
	const clean = hash.replace(/^#\/?/, '').trim()
	if (!clean) {
		return { mainTab: 'dashboard', subTab: '' }
	}
	const parts = clean.split('/')
	const main = parts[0] as MainTab
	const sub = parts[1] || ''

	if (['dashboard', 'reports', 'exports', 'settings'].includes(main)) {
		return { mainTab: main, subTab: sub }
	}
	return { mainTab: 'dashboard', subTab: '' }
}

const syncRouteFromLocation = () => {
	let rawHash = window.location.hash
	if (!rawHash || rawHash === '#' || rawHash === '#/') {
		const stored = localStorage.getItem(STORAGE_KEY)
		if (stored) {
			rawHash = stored
		}
	}
	const { mainTab, subTab } = parseHash(rawHash)
	currentTab.value = mainTab
	currentSubTab.value = subTab
}

const navigateTo = (mainTab: MainTab, subTab: string = '') => {
	currentTab.value = mainTab
	currentSubTab.value = subTab
	const hash = subTab ? `#/${mainTab}/${subTab}` : `#/${mainTab}`
	window.location.hash = hash
	localStorage.setItem(STORAGE_KEY, hash)
}

const handleSubTabChange = (sub: string) => {
	navigateTo(currentTab.value, sub)
}

onMounted(() => {
	syncRouteFromLocation()
	window.addEventListener('hashchange', syncRouteFromLocation)
})

onUnmounted(() => {
	window.removeEventListener('hashchange', syncRouteFromLocation)
})
</script>

<template>
	<NcContent app-name="nopstation_analytics">
		<NcAppNavigation>
			<template #list>
				<NcAppNavigationItem
					:active="currentTab === 'dashboard'"
					name="Sales Dashboard"
					@click="navigateTo('dashboard')"
				>
					<template #icon>
						<span class="nav-emoji">📊</span>
					</template>
				</NcAppNavigationItem>

				<NcAppNavigationItem
					:active="currentTab === 'reports'"
					name="Sales &amp; Customer Reports"
					@click="navigateTo('reports', currentSubTab || 'summary')"
				>
					<template #icon>
						<span class="nav-emoji">📑</span>
					</template>
				</NcAppNavigationItem>

				<NcAppNavigationItem
					:active="currentTab === 'exports'"
					name="Scheduled Exports"
					@click="navigateTo('exports')"
				>
					<template #icon>
						<span class="nav-emoji">📥</span>
					</template>
				</NcAppNavigationItem>

				<NcAppNavigationItem
					:active="currentTab === 'settings'"
					name="Settings &amp; Data Sync"
					@click="navigateTo('settings')"
				>
					<template #icon>
						<span class="nav-emoji">⚙️</span>
					</template>
				</NcAppNavigationItem>
			</template>
		</NcAppNavigation>

		<NcAppContent>
			<div class="main-viewport">
				<DashboardView v-if="currentTab === 'dashboard'" />
				<ReportsView
					v-else-if="currentTab === 'reports'"
					:sub-tab="currentSubTab"
					@update:sub-tab="handleSubTabChange"
				/>
				<ExportsView v-else-if="currentTab === 'exports'" />
				<SettingsView v-else-if="currentTab === 'settings'" />
			</div>
		</NcAppContent>
	</NcContent>
</template>

<style scoped>
.main-viewport {
	height: 100%;
	overflow-y: auto;
	background: var(--color-background-main, #f5f6f8);
}

.nav-emoji {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	font-size: 16px;
	width: 24px;
}
</style>
