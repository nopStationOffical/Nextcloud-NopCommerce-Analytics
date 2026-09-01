<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api, type SettingsData, type SyncLogItem } from '../services/api'

const loading = ref(false)
const testing = ref(false)
const syncing = ref(false)
const statusMessage = ref<string | null>(null)
const statusType = ref<'success' | 'error'>('success')

const form = ref({
	apiUrl: '',
	adminEmail: '',
	adminPassword: '',
	webhookSecret: '',
})

const lastSyncTime = ref<string | null>(null)
const syncLogs = ref<SyncLogItem[]>([])

const loadSettingsAndLogs = async () => {
	loading.value = true
	try {
		const [settings, syncStatus] = await Promise.all([
			api.getSettings(),
			api.getSyncStatus(),
		])
		form.value.apiUrl = settings.apiUrl
		form.value.adminEmail = settings.adminEmail
		form.value.webhookSecret = settings.webhookSecret
		lastSyncTime.value = syncStatus.lastSync
		syncLogs.value = syncStatus.logs
	} catch (e: any) {
		console.error('Failed to load settings', e)
	} finally {
		loading.value = false
	}
}

const saveSettings = async () => {
	loading.value = true
	statusMessage.value = null
	try {
		await api.saveSettings({
			apiUrl: form.value.apiUrl,
			adminEmail: form.value.adminEmail,
			adminPassword: form.value.adminPassword || undefined,
			webhookSecret: form.value.webhookSecret,
		})
		statusType.value = 'success'
		statusMessage.value = 'Settings saved successfully!'
		form.value.adminPassword = ''
	} catch (e: any) {
		statusType.value = 'error'
		statusMessage.value = 'Failed to save settings: ' + (e.message || 'Error')
	} finally {
		loading.value = false
	}
}

const testConnection = async () => {
	testing.value = true
	statusMessage.value = null
	try {
		const res = await api.testConnection()
		statusType.value = 'success'
		statusMessage.value = `Connection verified! Successfully authenticated as ${res.adminEmail}`
	} catch (e: any) {
		statusType.value = 'error'
		statusMessage.value = 'Connection failed: ' + (e.response?.data?.Message || e.message)
	} finally {
		testing.value = false
	}
}

const triggerSync = async (type: 'full' | 'incremental') => {
	syncing.value = true
	statusMessage.value = null
	try {
		const res = await api.runSync(type)
		statusType.value = 'success'
		statusMessage.value = res.Message || 'Synchronization completed!'
		await loadSettingsAndLogs()
	} catch (e: any) {
		statusType.value = 'error'
		statusMessage.value = 'Sync failed: ' + (e.response?.data?.Message || e.message)
	} finally {
		syncing.value = false
	}
}

onMounted(() => {
	loadSettingsAndLogs()
})
</script>

<template>
	<div class="settings-container">
		<div class="header-bar">
			<h2>nopCommerce Integration &amp; Sync Settings</h2>
			<p class="subtitle">Configure API credentials, webhook security, and manual data sync</p>
		</div>

		<div v-if="statusMessage" :class="['banner', statusType]">
			{{ statusMessage }}
		</div>

		<div class="settings-grid">
			<!-- Left: Credentials Form -->
			<div class="panel-card">
				<h3 class="panel-title">nopCommerce REST API Credentials</h3>
				<form @submit.prevent="saveSettings" class="form-body">
					<div class="form-group">
						<label>nopCommerce Base URL</label>
						<input
							v-model="form.apiUrl"
							type="text"
							placeholder="https://yourstore.com"
							class="text-input"
							required
						/>
						<span class="hint">Includes protocol (https://). ngrok or public domain.</span>
					</div>

					<div class="form-group">
						<label>Admin Email</label>
						<input
							v-model="form.adminEmail"
							type="email"
							placeholder="admin@yourstore.com"
							class="text-input"
							required
						/>
					</div>

					<div class="form-group">
						<label>Admin Password</label>
						<input
							v-model="form.adminPassword"
							type="password"
							placeholder="Leave blank to keep existing"
							class="text-input"
						/>
					</div>

					<div class="form-group">
						<label>Webhook Shared Secret</label>
						<input
							v-model="form.webhookSecret"
							type="text"
							class="text-input"
						/>
						<span class="hint">Used for HMAC-SHA256 signature verification on incoming webhooks.</span>
					</div>

					<div class="btn-row">
						<button type="submit" class="primary-btn" :disabled="loading">
							{{ loading ? 'Saving...' : '💾 Save Settings' }}
						</button>
						<button type="button" class="secondary-btn" :disabled="testing" @click="testConnection">
							{{ testing ? 'Testing...' : '⚡ Test Connection' }}
						</button>
					</div>
				</form>
			</div>

			<!-- Right: Data Sync Engine -->
			<div class="panel-card">
				<h3 class="panel-title">Data Synchronization Engine</h3>
				<p class="desc">
					Nextcloud pulls raw orders, customers, and products from nopCommerce into local database tables to calculate metrics locally.
				</p>

				<div class="sync-status-box">
					<span class="label">Last Successful Sync:</span>
					<span class="val">{{ lastSyncTime || 'Never' }}</span>
				</div>

				<div class="sync-actions">
					<button class="primary-btn sync-btn" :disabled="syncing" @click="triggerSync('full')">
						{{ syncing ? 'Syncing...' : '🔄 Run Full Sync (All Entities)' }}
					</button>
					<button class="secondary-btn sync-btn" :disabled="syncing" @click="triggerSync('incremental')">
						{{ syncing ? 'Syncing...' : '⚡ Incremental Sync (New Records Only)' }}
					</button>
				</div>

				<h4 class="sub-title">Recent Sync Operations</h4>
				<div v-if="syncLogs.length === 0" class="empty-hint">No sync logs recorded yet.</div>
				<table v-else class="styled-table mini-table">
					<thead>
						<tr>
							<th>Type</th>
							<th>Entity</th>
							<th class="text-right">Records</th>
							<th>Status</th>
							<th>Time</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="log in syncLogs" :key="log.id">
							<td><span class="pill">{{ log.syncType }}</span></td>
							<td>{{ log.entityType }}</td>
							<td class="text-right font-bold">{{ log.recordsProcessed }}</td>
							<td>
								<span :class="['status-dot', log.status]"></span>
								{{ log.status }}
							</td>
							<td class="text-muted">{{ log.createdAt }}</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</template>

<style scoped>
.settings-container {
	padding: 24px;
	display: flex;
	flex-direction: column;
	gap: 20px;
	font-family: var(--font-face, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);
}

.header-bar h2 {
	margin: 0;
	font-size: 24px;
	font-weight: 700;
}

.subtitle {
	margin: 4px 0 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.settings-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 20px;
}

@media (max-width: 1000px) {
	.settings-grid {
		grid-template-columns: 1fr;
	}
}

.panel-card {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: 12px;
	padding: 24px;
	display: flex;
	flex-direction: column;
	gap: 16px;
	box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
}

.panel-title {
	margin: 0;
	font-size: 16px;
	font-weight: 600;
}

.form-body {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.form-group {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.form-group label {
	font-size: 13px;
	font-weight: 600;
	color: var(--color-main-text);
}

.text-input {
	padding: 10px 14px;
	border-radius: 8px;
	border: 1px solid var(--color-border);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 13px;
	outline: none;
}

.text-input:focus {
	border-color: var(--color-primary-element, #0082c9);
}

.hint {
	font-size: 11px;
	color: var(--color-text-maxcontrast);
}

.btn-row {
	display: flex;
	gap: 12px;
	margin-top: 8px;
}

.primary-btn {
	padding: 10px 18px;
	background: var(--color-primary-element, #0082c9);
	color: var(--color-primary-element-text, #ffffff);
	border: none;
	border-radius: 8px;
	font-weight: 600;
	cursor: pointer;
}

.secondary-btn {
	padding: 10px 18px;
	background: var(--color-background-hover);
	color: var(--color-main-text);
	border: 1px solid var(--color-border);
	border-radius: 8px;
	font-weight: 600;
	cursor: pointer;
}

.primary-btn:hover, .secondary-btn:hover {
	opacity: 0.9;
}

.desc {
	margin: 0;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	line-height: 1.5;
}

.sync-status-box {
	background: var(--color-background-hover);
	padding: 14px 16px;
	border-radius: 8px;
	display: flex;
	justify-content: space-between;
	align-items: center;
	font-size: 13px;
}

.sync-status-box .label {
	color: var(--color-text-maxcontrast);
}

.sync-status-box .val {
	font-weight: 600;
	color: var(--color-main-text);
}

.sync-actions {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.sync-btn {
	width: 100%;
	text-align: center;
	justify-content: center;
}

.sub-title {
	margin: 12px 0 4px 0;
	font-size: 14px;
	font-weight: 600;
}

.styled-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 12px;
}

.styled-table th {
	text-align: left;
	padding: 8px 6px;
	border-bottom: 2px solid var(--color-border);
	color: var(--color-text-maxcontrast);
	font-weight: 600;
}

.styled-table td {
	padding: 8px 6px;
	border-bottom: 1px solid var(--color-border);
}

.text-right {
	text-align: right;
}

.font-bold {
	font-weight: 600;
}

.text-muted {
	color: var(--color-text-maxcontrast);
	font-size: 11px;
}

.pill {
	background: var(--color-background-hover);
	padding: 2px 8px;
	border-radius: 12px;
	font-size: 10px;
	font-weight: 600;
}

.status-dot {
	display: inline-block;
	width: 7px;
	height: 7px;
	border-radius: 50%;
	margin-right: 4px;
}

.status-dot.success {
	background: #46ba61;
}

.status-dot.error {
	background: #e9322d;
}

.banner {
	padding: 12px 16px;
	border-radius: 8px;
	font-size: 13px;
}

.banner.success {
	background: rgba(70, 186, 97, 0.15);
	color: #27883d;
	border-left: 4px solid #46ba61;
}

.banner.error {
	background: rgba(233, 50, 45, 0.15);
	color: #c02824;
	border-left: 4px solid #e9322d;
}

.empty-hint {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	padding: 16px 0;
	text-align: center;
}
</style>
