<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import {
	api,
	type SalesSummaryItem,
	type CustomerSegmentData,
	type LowStockItem,
} from '../services/api'

const activeSubTab = ref<'summary' | 'customers' | 'lowstock'>('summary')
const loading = ref(false)
const groupBy = ref('day')
const exportMsg = ref('')

const summaryList = ref<SalesSummaryItem[]>([])
const customerData = ref<CustomerSegmentData>({
	newCustomers: 0,
	returningCustomers: 0,
	totalActiveCustomers: 0,
	topCustomers: [],
})
const lowStockList = ref<LowStockItem[]>([])

const loadReportData = async () => {
	loading.value = true
	exportMsg.value = ''
	try {
		if (activeSubTab.value === 'summary') {
			summaryList.value = await api.getSummary({ groupBy: groupBy.value })
		} else if (activeSubTab.value === 'customers') {
			customerData.value = await api.getCustomers()
		} else if (activeSubTab.value === 'lowstock') {
			lowStockList.value = await api.getLowStock(10)
		}
	} catch (err) {
		console.error('Failed to load reports', err)
	} finally {
		loading.value = false
	}
}

const handleExportCsv = async () => {
	loading.value = true
	exportMsg.value = ''
	try {
		const res = await api.exportSummary({ groupBy: groupBy.value })
		exportMsg.value = res.Message || 'Report generated successfully in Nextcloud Files.'
	} catch (err: any) {
		exportMsg.value = 'Export failed: ' + (err.message || 'Unknown error')
	} finally {
		loading.value = false
	}
}

watch([activeSubTab, groupBy], () => {
	loadReportData()
})

onMounted(() => {
	loadReportData()
})

const formatCurrency = (val: number): string => {
	return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val || 0)
}
</script>

<template>
	<div class="reports-container">
		<!-- Header & Tab Navigation -->
		<div class="header-bar">
			<div>
				<h2>Sales &amp; Operational Reports</h2>
				<p class="subtitle">Detailed breakdown tables, customer analytics, and inventory alerts</p>
			</div>

			<div class="tab-pill-group">
				<button
					:class="['tab-pill', { active: activeSubTab === 'summary' }]"
					@click="activeSubTab = 'summary'"
				>
					Sales Summary
				</button>
				<button
					:class="['tab-pill', { active: activeSubTab === 'customers' }]"
					@click="activeSubTab = 'customers'"
				>
					Customer Segmentation
				</button>
				<button
					:class="['tab-pill', { active: activeSubTab === 'lowstock' }]"
					@click="activeSubTab = 'lowstock'"
				>
					Low Stock Alerts
				</button>
			</div>
		</div>

		<!-- Action bar for Sales Summary -->
		<div v-if="activeSubTab === 'summary'" class="actions-bar">
			<div class="filter-group">
				<label>Group by:</label>
				<select v-model="groupBy" class="filter-select">
					<option value="day">Day</option>
					<option value="week">Week</option>
					<option value="month">Month</option>
				</select>
			</div>

			<div class="export-group">
				<button class="primary-btn" :disabled="loading" @click="handleExportCsv">
					📥 Export to Nextcloud Files (CSV)
				</button>
			</div>
		</div>

		<div v-if="exportMsg" class="alert-banner">
			{{ exportMsg }}
		</div>

		<!-- 1. Sales Summary Table -->
		<div v-if="activeSubTab === 'summary'" class="panel-card">
			<div v-if="loading" class="loading-state">Loading summary data...</div>
			<div v-else-if="summaryList.length === 0" class="empty-state">
				No sales records available. Try running a data sync from Settings.
			</div>
			<table v-else class="styled-table">
				<thead>
					<tr>
						<th>Date / Period</th>
						<th class="text-right">Orders</th>
						<th class="text-right">Profit</th>
						<th class="text-right">Shipping</th>
						<th class="text-right">Tax</th>
						<th class="text-right">Order Total</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="(row, idx) in summaryList" :key="idx">
						<td class="font-bold">{{ row.summary }}</td>
						<td class="text-right">{{ row.numberOfOrders }}</td>
						<td class="text-right text-success">{{ formatCurrency(row.profit) }}</td>
						<td class="text-right">{{ formatCurrency(row.shipping) }}</td>
						<td class="text-right">{{ formatCurrency(row.tax) }}</td>
						<td class="text-right font-bold">{{ formatCurrency(row.orderTotal) }}</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- 2. Customer Segmentation Table -->
		<div v-if="activeSubTab === 'customers'" class="panel-card">
			<div class="kpi-mini-row">
				<div class="kpi-mini">
					<span class="label">Total Active Customers</span>
					<span class="val">{{ customerData.totalActiveCustomers }}</span>
				</div>
				<div class="kpi-mini">
					<span class="label">First-Time Buyers</span>
					<span class="val text-info">{{ customerData.newCustomers }}</span>
				</div>
				<div class="kpi-mini">
					<span class="label">Repeat / Returning</span>
					<span class="val text-success">{{ customerData.returningCustomers }}</span>
				</div>
			</div>

			<h3 class="section-title">Top Customers by Total Spend</h3>
			<table class="styled-table">
				<thead>
					<tr>
						<th>Customer ID</th>
						<th>Name</th>
						<th>Email</th>
						<th class="text-right">Orders Count</th>
						<th class="text-right">Total Spent</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="c in customerData.topCustomers" :key="c.customerId">
						<td>#{{ c.customerId }}</td>
						<td class="font-bold">{{ c.fullName }}</td>
						<td>{{ c.email }}</td>
						<td class="text-right">{{ c.orderCount }}</td>
						<td class="text-right text-success font-bold">{{ formatCurrency(c.totalSpent) }}</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- 3. Low Stock Alerts Table -->
		<div v-if="activeSubTab === 'lowstock'" class="panel-card">
			<h3 class="section-title">Inventory Alerts (Stock &le; 10 units)</h3>
			<div v-if="lowStockList.length === 0" class="empty-state">
				All catalog items have healthy stock levels.
			</div>
			<table v-else class="styled-table">
				<thead>
					<tr>
						<th>Product ID</th>
						<th>Product Name</th>
						<th>SKU</th>
						<th class="text-right">In-Stock Qty</th>
						<th class="text-right">Unit Price</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="item in lowStockList" :key="item.productId">
						<td>#{{ item.productId }}</td>
						<td class="font-bold">{{ item.name }}</td>
						<td>{{ item.sku || '-' }}</td>
						<td class="text-right font-bold text-danger">{{ item.stockQuantity }}</td>
						<td class="text-right">{{ formatCurrency(item.price) }}</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</template>

<style scoped>
.reports-container {
	padding: 24px;
	display: flex;
	flex-direction: column;
	gap: 20px;
	font-family: var(--font-face, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);
}

.header-bar {
	display: flex;
	justify-content: space-between;
	align-items: center;
	flex-wrap: wrap;
	gap: 16px;
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

.tab-pill-group {
	display: flex;
	background: var(--color-background-hover);
	padding: 4px;
	border-radius: 10px;
	gap: 4px;
}

.tab-pill {
	padding: 8px 16px;
	border: none;
	background: transparent;
	color: var(--color-main-text);
	border-radius: 8px;
	font-size: 13px;
	font-weight: 600;
	cursor: pointer;
	transition: all 0.2s;
}

.tab-pill.active {
	background: var(--color-main-background);
	box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
	color: var(--color-primary-element, #0082c9);
}

.actions-bar {
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.filter-group {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 13px;
	font-weight: 600;
}

.filter-select {
	padding: 8px 12px;
	border-radius: 8px;
	border: 1px solid var(--color-border);
	background: var(--color-main-background);
	color: var(--color-main-text);
	outline: none;
}

.primary-btn {
	padding: 8px 16px;
	background: var(--color-primary-element, #0082c9);
	color: var(--color-primary-element-text, #ffffff);
	border: none;
	border-radius: 8px;
	font-weight: 600;
	cursor: pointer;
}

.primary-btn:hover {
	opacity: 0.9;
}

.alert-banner {
	padding: 12px 16px;
	background: rgba(0, 130, 201, 0.1);
	border-left: 4px solid var(--color-primary-element, #0082c9);
	border-radius: 8px;
	font-size: 13px;
	color: var(--color-main-text);
}

.panel-card {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: 12px;
	padding: 20px;
	box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
}

.section-title {
	margin: 0 0 16px 0;
	font-size: 16px;
	font-weight: 600;
}

.styled-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 13px;
}

.styled-table th {
	text-align: left;
	padding: 12px 8px;
	border-bottom: 2px solid var(--color-border);
	color: var(--color-text-maxcontrast);
	font-weight: 600;
}

.styled-table td {
	padding: 12px 8px;
	border-bottom: 1px solid var(--color-border);
}

.text-right {
	text-align: right;
}

.font-bold {
	font-weight: 600;
}

.text-success {
	color: #46ba61;
}

.text-info {
	color: #0082c9;
}

.text-danger {
	color: #e9322d;
}

.kpi-mini-row {
	display: flex;
	gap: 20px;
	margin-bottom: 24px;
}

.kpi-mini {
	flex: 1;
	background: var(--color-background-hover);
	padding: 16px;
	border-radius: 8px;
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.kpi-mini .label {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	font-weight: 600;
}

.kpi-mini .val {
	font-size: 22px;
	font-weight: 700;
}

.empty-state, .loading-state {
	padding: 40px;
	text-align: center;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}
</style>
