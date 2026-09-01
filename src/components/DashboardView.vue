<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { api, type KpiData, type TrendData, type BestsellerItem } from '../services/api'
import { Line } from 'vue-chartjs'
import {
	Chart as ChartJS,
	Title,
	Tooltip,
	Legend,
	LineElement,
	PointElement,
	CategoryScale,
	LinearScale,
	Filler,
} from 'chart.js'

ChartJS.register(Title, Tooltip, Legend, LineElement, PointElement, CategoryScale, LinearScale, Filler)

const loading = ref(false)
const selectedRange = ref('30days')
const selectedStore = ref(0)

const kpis = ref<KpiData>({
	totalOrders: 0,
	totalRevenue: 0,
	avgOrderValue: 0,
	totalProfit: 0,
	totalShipping: 0,
	totalTax: 0,
	uniqueCustomers: 0,
	completedOrders: 0,
	completionRate: 0,
})

const trends = ref<TrendData>({
	labels: [],
	revenue: [],
	orders: [],
	profit: [],
})

const bestsellers = ref<BestsellerItem[]>([])

const dateFilters = computed(() => {
	const now = new Date()
	let startDate = ''
	const endDate = now.toISOString().slice(0, 19).replace('T', ' ')

	if (selectedRange.value === 'today') {
		const start = new Date(now.getFullYear(), now.getMonth(), now.getDate())
		startDate = start.toISOString().slice(0, 19).replace('T', ' ')
	} else if (selectedRange.value === '7days') {
		const start = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000)
		startDate = start.toISOString().slice(0, 19).replace('T', ' ')
	} else if (selectedRange.value === '30days') {
		const start = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000)
		startDate = start.toISOString().slice(0, 19).replace('T', ' ')
	} else if (selectedRange.value === 'year') {
		const start = new Date(now.getFullYear(), 0, 1)
		startDate = start.toISOString().slice(0, 19).replace('T', ' ')
	}

	return {
		startDate: startDate || undefined,
		endDate: selectedRange.value !== 'all' ? endDate : undefined,
		storeId: selectedStore.value > 0 ? selectedStore.value : undefined,
	}
})

const chartData = computed(() => ({
	labels: trends.value.labels.length > 0 ? trends.value.labels : ['No data'],
	datasets: [
		{
			label: 'Revenue ($)',
			data: trends.value.revenue.length > 0 ? trends.value.revenue : [0],
			borderColor: '#0082c9',
			backgroundColor: 'rgba(0, 130, 201, 0.1)',
			fill: true,
			tension: 0.35,
			yAxisID: 'y',
		},
		{
			label: 'Orders Count',
			data: trends.value.orders.length > 0 ? trends.value.orders : [0],
			borderColor: '#46ba61',
			backgroundColor: 'rgba(70, 186, 97, 0.1)',
			fill: false,
			tension: 0.35,
			yAxisID: 'y1',
		},
	],
}))

const chartOptions = {
	responsive: true,
	maintainAspectRatio: false,
	interaction: {
		mode: 'index' as const,
		intersect: false,
	},
	scales: {
		y: {
			type: 'linear' as const,
			display: true,
			position: 'left' as const,
			title: { display: true, text: 'Revenue ($)' },
		},
		y1: {
			type: 'linear' as const,
			display: true,
			position: 'right' as const,
			grid: { drawOnChartArea: false },
			title: { display: true, text: 'Orders' },
		},
	},
}

const loadDashboardData = async () => {
	loading.value = true
	try {
		const params = dateFilters.value
		const [kpiRes, trendRes, bestsellersRes] = await Promise.all([
			api.getKpis(params),
			api.getTrends({ ...params, interval: selectedRange.value === 'year' ? 'month' : 'day' }),
			api.getBestsellers({ ...params, limit: 7 }),
		])
		kpis.value = kpiRes
		trends.value = trendRes
		bestsellers.value = bestsellersRes
	} catch (err) {
		console.error('Failed to load dashboard metrics', err)
	} finally {
		loading.value = false
	}
}

watch([selectedRange, selectedStore], () => {
	loadDashboardData()
})

onMounted(() => {
	loadDashboardData()
})

const formatCurrency = (val: number): string => {
	return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val || 0)
}
</script>

<template>
	<div class="dashboard-container">
		<!-- Header Controls -->
		<div class="header-bar">
			<div class="header-title">
				<h2>Sales Analytics Dashboard</h2>
				<p class="subtitle">Real-time e-commerce performance overview</p>
			</div>
			<div class="controls-row">
				<select v-model="selectedRange" class="filter-select">
					<option value="today">Today</option>
					<option value="7days">Last 7 Days</option>
					<option value="30days">Last 30 Days</option>
					<option value="year">This Year</option>
					<option value="all">All Time</option>
				</select>
				<button class="primary-btn" :disabled="loading" @click="loadDashboardData">
					<span v-if="loading">Loading...</span>
					<span v-else>↻ Refresh</span>
				</button>
			</div>
		</div>

		<!-- KPI Metric Cards Grid -->
		<div class="kpi-grid">
			<div class="kpi-card highlight-card">
				<div class="kpi-header">
					<span class="kpi-label">Total Revenue</span>
					<span class="kpi-icon">💰</span>
				</div>
				<div class="kpi-value">{{ formatCurrency(kpis.totalRevenue) }}</div>
				<div class="kpi-footer">Net sales volume</div>
			</div>

			<div class="kpi-card">
				<div class="kpi-header">
					<span class="kpi-label">Total Orders</span>
					<span class="kpi-icon">📦</span>
				</div>
				<div class="kpi-value">{{ kpis.totalOrders }}</div>
				<div class="kpi-footer text-success">{{ kpis.completedOrders }} Completed ({{ kpis.completionRate }}%)</div>
			</div>

			<div class="kpi-card">
				<div class="kpi-header">
					<span class="kpi-label">Average Order Value</span>
					<span class="kpi-icon">📊</span>
				</div>
				<div class="kpi-value">{{ formatCurrency(kpis.avgOrderValue) }}</div>
				<div class="kpi-footer">Revenue / Order</div>
			</div>

			<div class="kpi-card">
				<div class="kpi-header">
					<span class="kpi-label">Estimated Profit</span>
					<span class="kpi-icon">📈</span>
				</div>
				<div class="kpi-value">{{ formatCurrency(kpis.totalProfit) }}</div>
				<div class="kpi-footer text-info">Tax: {{ formatCurrency(kpis.totalTax) }}</div>
			</div>

			<div class="kpi-card">
				<div class="kpi-header">
					<span class="kpi-label">Unique Customers</span>
					<span class="kpi-icon">👥</span>
				</div>
				<div class="kpi-value">{{ kpis.uniqueCustomers }}</div>
				<div class="kpi-footer">Active shoppers</div>
			</div>

			<div class="kpi-card">
				<div class="kpi-header">
					<span class="kpi-label">Shipping Collected</span>
					<span class="kpi-icon">🚚</span>
				</div>
				<div class="kpi-value">{{ formatCurrency(kpis.totalShipping) }}</div>
				<div class="kpi-footer">Fulfillment fees</div>
			</div>
		</div>

		<!-- Charts & Tables Section -->
		<div class="content-row">
			<!-- Trend Chart -->
			<div class="chart-panel">
				<div class="panel-header">
					<h3>Sales &amp; Order Trends</h3>
					<span class="badge">{{ selectedRange.toUpperCase() }}</span>
				</div>
				<div class="chart-wrapper">
					<Line :data="chartData" :options="chartOptions" />
				</div>
			</div>

			<!-- Bestsellers Panel -->
			<div class="bestsellers-panel">
				<div class="panel-header">
					<h3>Top Selling Products</h3>
				</div>
				<div v-if="bestsellers.length === 0" class="empty-state">
					No product sales data available for this range.
				</div>
				<table v-else class="styled-table">
					<thead>
						<tr>
							<th>Product</th>
							<th class="text-right">Qty</th>
							<th class="text-right">Total</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="item in bestsellers" :key="item.productId">
							<td class="product-cell">
								<span class="product-name">{{ item.productName }}</span>
							</td>
							<td class="text-right font-bold">{{ item.totalQuantity }}</td>
							<td class="text-right text-success">{{ formatCurrency(item.totalAmount) }}</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</template>

<style scoped>
.dashboard-container {
	padding: 24px;
	display: flex;
	flex-direction: column;
	gap: 24px;
	font-family: var(--font-face, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);
}

.header-bar {
	display: flex;
	justify-content: space-between;
	align-items: center;
	flex-wrap: wrap;
	gap: 16px;
}

.header-title h2 {
	margin: 0;
	font-size: 24px;
	font-weight: 700;
	color: var(--color-main-text);
}

.subtitle {
	margin: 4px 0 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.controls-row {
	display: flex;
	gap: 12px;
	align-items: center;
}

.filter-select {
	padding: 8px 14px;
	border-radius: 8px;
	border: 1px solid var(--color-border);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 13px;
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
	transition: opacity 0.2s;
}

.primary-btn:hover {
	opacity: 0.9;
}

.kpi-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 16px;
}

.kpi-card {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: 12px;
	padding: 20px;
	display: flex;
	flex-direction: column;
	gap: 8px;
	box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
}

.highlight-card {
	border-left: 4px solid var(--color-primary-element, #0082c9);
}

.kpi-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.kpi-label {
	font-size: 12px;
	text-transform: uppercase;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	letter-spacing: 0.5px;
}

.kpi-icon {
	font-size: 18px;
}

.kpi-value {
	font-size: 26px;
	font-weight: 700;
	color: var(--color-main-text);
}

.kpi-footer {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.text-success {
	color: #46ba61;
}

.text-info {
	color: #0082c9;
}

.text-right {
	text-align: right;
}

.font-bold {
	font-weight: 600;
}

.content-row {
	display: grid;
	grid-template-columns: 2fr 1fr;
	gap: 20px;
}

@media (max-width: 1000px) {
	.content-row {
		grid-template-columns: 1fr;
	}
}

.chart-panel, .bestsellers-panel {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: 12px;
	padding: 20px;
	display: flex;
	flex-direction: column;
	gap: 16px;
	box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
}

.panel-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.panel-header h3 {
	margin: 0;
	font-size: 16px;
	font-weight: 600;
}

.badge {
	background: var(--color-background-hover);
	padding: 4px 10px;
	border-radius: 20px;
	font-size: 11px;
	font-weight: 600;
}

.chart-wrapper {
	height: 320px;
	position: relative;
}

.styled-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 13px;
}

.styled-table th {
	text-align: left;
	padding: 10px 8px;
	border-bottom: 2px solid var(--color-border);
	color: var(--color-text-maxcontrast);
	font-weight: 600;
}

.styled-table td {
	padding: 12px 8px;
	border-bottom: 1px solid var(--color-border);
}

.product-name {
	font-weight: 500;
	color: var(--color-main-text);
}

.empty-state {
	padding: 30px;
	text-align: center;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}
</style>
