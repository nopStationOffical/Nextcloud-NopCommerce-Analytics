<script setup lang="ts">
import { ref } from 'vue'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'

import DashboardView from './components/DashboardView.vue'
import ReportsView from './components/ReportsView.vue'
import ExportsView from './components/ExportsView.vue'
import SettingsView from './components/SettingsView.vue'

const currentTab = ref<'dashboard' | 'reports' | 'exports' | 'settings'>('dashboard')
</script>

<template>
	<NcContent app-name="nopstation_analytics">
		<NcAppNavigation>
			<template #list>
				<NcAppNavigationItem
					:active="currentTab === 'dashboard'"
					name="Sales Dashboard"
					@click="currentTab = 'dashboard'"
				>
					<template #icon>
						<span class="nav-emoji">📊</span>
					</template>
				</NcAppNavigationItem>

				<NcAppNavigationItem
					:active="currentTab === 'reports'"
					name="Sales &amp; Customer Reports"
					@click="currentTab = 'reports'"
				>
					<template #icon>
						<span class="nav-emoji">📑</span>
					</template>
				</NcAppNavigationItem>

				<NcAppNavigationItem
					:active="currentTab === 'exports'"
					name="Scheduled Exports"
					@click="currentTab = 'exports'"
				>
					<template #icon>
						<span class="nav-emoji">📥</span>
					</template>
				</NcAppNavigationItem>

				<NcAppNavigationItem
					:active="currentTab === 'settings'"
					name="Settings &amp; Data Sync"
					@click="currentTab = 'settings'"
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
				<ReportsView v-else-if="currentTab === 'reports'" />
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
