<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import {
	api,
	type KpiData,
	type TrendData,
	type BestsellerItem,
	type ShipmentOverviewData,
} from '../services/api'
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
const selectedRange = ref('all')
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

const shipments = ref<ShipmentOverviewData>({
	notYetShipped: 0,
	partiallyShipped: 0,
	shipped: 0,
	delivered: 0,
	shippingNotRequired: 0,
	totalShippableOrders: 0,
	fulfilledOrders: 0,
	fulfillmentRate: 0,
	totalShippingFees: 0,
	recentShipments: [],
})

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
		const [kpiRes, trendRes, bestsellersRes, shipmentRes] = await Promise.all([
			api.getKpis(params),
			api.getTrends({ ...params, interval: selectedRange.value === 'year' ? 'month' : 'day' }),
			api.getBestsellers({ ...params, limit: 7 }),
			api.getShipments(params),
		])
		kpis.value = kpiRes
		trends.value = trendRes
		bestsellers.value = bestsellersRes
		shipments.value = shipmentRes
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

const formatDate = (dateStr?: string): string => {
	if (!dateStr) return '-'
	try {
		const d = new Date(dateStr)
		return isNaN(d.getTime()) ? dateStr : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
	} catch {
		return dateStr
	}
}
</script>

<template>
	<div class="dashboard-container">
		<!-- Header Controls -->
		<div class="header-bar">
			<div class="header-title">
				<h2>Sales Analytics Dashboard</h2>
				<p class="subtitle">Real-time e-commerce performance and logistics overview</p>
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

		<!-- Charts & Bestsellers Section -->
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

		<!-- NEW: Shipment & Fulfillment Overview Section -->
		<div class="shipment-overview-section">
			<div class="section-header">
				<div>
					<h3 class="section-title">🚚 Shipment &amp; Order Fulfillment Overview</h3>
					<p class="section-subtitle">Logistics pipeline, shipping status distribution, and fulfillment rate</p>
				</div>
				<div class="fulfillment-badge">
					<span class="fulfillment-label">Overall Fulfillment Rate:</span>
					<span class="fulfillment-pct">{{ shipments.fulfillmentRate }}%</span>
				</div>
			</div>

			<!-- Fulfillment Status KPI Cards -->
			<div class="shipping-kpi-grid">
				<div class="ship-kpi-card card-pending">
					<div class="ship-kpi-header">
						<span class="ship-icon">⏳</span>
						<span v-if="shipments.notYetShipped > 0" class="action-tag">Awaiting Dispatch</span>
					</div>
					<div class="ship-kpi-value">{{ shipments.notYetShipped }}</div>
					<div class="ship-kpi-label">Not Yet Shipped</div>
					<div class="ship-kpi-sub">Needs warehouse fulfillment</div>
				</div>

				<div class="ship-kpi-card card-transit">
					<div class="ship-kpi-header">
						<span class="ship-icon">🚚</span>
					</div>
					<div class="ship-kpi-value">{{ shipments.shipped + shipments.partiallyShipped }}</div>
					<div class="ship-kpi-label">In Transit / Shipped</div>
					<div class="ship-kpi-sub">En route to customers</div>
				</div>

				<div class="ship-kpi-card card-delivered">
					<div class="ship-kpi-header">
						<span class="ship-icon">✅</span>
					</div>
					<div class="ship-kpi-value">{{ shipments.delivered }}</div>
					<div class="ship-kpi-label">Delivered</div>
					<div class="ship-kpi-sub">Successfully fulfilled</div>
				</div>

				<div class="ship-kpi-card card-progress">
					<div class="ship-kpi-header">
						<span class="ship-icon">📦</span>
						<span class="rate-num">{{ shipments.fulfilledOrders }} / {{ shipments.totalShippableOrders }}</span>
					</div>
					<div class="progress-bar-container">
						<div
							class="progress-bar-fill"
							:style="{ width: `${Math.min(shipments.fulfillmentRate, 100)}%` }"
						></div>
					</div>
					<div class="ship-kpi-label">Fulfillment Efficiency</div>
					<div class="ship-kpi-sub">{{ shipments.fulfillmentRate }}% of shippable orders fulfilled</div>
				</div>
			</div>

			<!-- Recent Orders Logistics Feed -->
			<div class="recent-shipments-panel">
				<div class="panel-header">
					<h4>Recent Orders Fulfillment Feed</h4>
					<span class="badge">Showing Latest {{ shipments.recentShipments.length }} Orders</span>
				</div>

				<div v-if="shipments.recentShipments.length === 0" class="empty-state">
					No recent shipments found for this time period.
				</div>
				<table v-else class="styled-table shipment-table">
					<thead>
						<tr>
							<th>Order #</th>
							<th>Customer</th>
							<th>Date</th>
							<th>Shipping Method</th>
							<th>Order Status</th>
							<th>Shipping Status</th>
							<th class="text-right">Total</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="order in shipments.recentShipments" :key="order.orderId">
							<td class="font-bold">
								<span class="order-id-pill">{{ order.customOrderNumber }}</span>
							</td>
							<td>
								<div class="customer-info-cell">
									<span class="cust-name font-bold">{{ order.customerName }}</span>
									<span class="cust-email">{{ order.customerEmail }}</span>
								</div>
							</td>
							<td class="date-cell">{{ formatDate(order.createdOnUtc) }}</td>
							<td>
								<span class="shipping-method-tag">{{ order.shippingMethod || 'Standard' }}</span>
							</td>
							<td>
								<span class="status-pill" :class="order.orderStatusClass">
									{{ order.orderStatus }}
								</span>
							</td>
							<td>
								<span class="status-pill" :class="order.shippingStatusClass">
									{{ order.shippingStatus }}
								</span>
							</td>
							<td class="text-right font-bold text-success">
								{{ formatCurrency(order.orderTotal) }}
							</td>
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

.panel-header h3, .panel-header h4 {
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

/* ==========================================================================
   Shipment Overview Section Styles
   ========================================================================== */
.shipment-overview-section {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: 14px;
	padding: 24px;
	display: flex;
	flex-direction: column;
	gap: 20px;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
}

.section-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	flex-wrap: wrap;
	gap: 12px;
}

.section-title {
	margin: 0;
	font-size: 18px;
	font-weight: 700;
}

.section-subtitle {
	margin: 4px 0 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.fulfillment-badge {
	display: flex;
	align-items: center;
	gap: 8px;
	background: var(--color-background-hover);
	padding: 6px 14px;
	border-radius: 20px;
}

.fulfillment-label {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	font-weight: 600;
}

.fulfillment-pct {
	font-size: 16px;
	font-weight: 700;
	color: #27883d;
}

.shipping-kpi-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
	gap: 16px;
}

.ship-kpi-card {
	background: var(--color-background-hover);
	border: 1px solid var(--color-border);
	border-radius: 12px;
	padding: 16px;
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.card-pending {
	border-left: 4px solid #eab308;
}

.card-transit {
	border-left: 4px solid #0082c9;
}

.card-delivered {
	border-left: 4px solid #46ba61;
}

.card-progress {
	border-left: 4px solid #8b5cf6;
}

.ship-kpi-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.ship-icon {
	font-size: 20px;
}

.action-tag {
	background: rgba(234, 179, 8, 0.2);
	color: #a16207;
	font-size: 11px;
	font-weight: 600;
	padding: 2px 8px;
	border-radius: 10px;
}

.rate-num {
	font-size: 12px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
}

.ship-kpi-value {
	font-size: 26px;
	font-weight: 700;
	color: var(--color-main-text);
}

.ship-kpi-label {
	font-size: 13px;
	font-weight: 600;
	color: var(--color-main-text);
}

.ship-kpi-sub {
	font-size: 11px;
	color: var(--color-text-maxcontrast);
}

.progress-bar-container {
	width: 100%;
	height: 8px;
	background: var(--color-border);
	border-radius: 4px;
	overflow: hidden;
	margin: 6px 0;
}

.progress-bar-fill {
	height: 100%;
	background: #8b5cf6;
	border-radius: 4px;
	transition: width 0.4s ease;
}

/* Recent Shipments Feed */
.recent-shipments-panel {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: 10px;
	padding: 16px;
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.order-id-pill {
	font-family: monospace;
	background: var(--color-background-hover);
	padding: 2px 8px;
	border-radius: 6px;
	font-size: 12px;
}

.customer-info-cell {
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.cust-email {
	font-size: 11px;
	color: var(--color-text-maxcontrast);
}

.shipping-method-tag {
	font-size: 12px;
	background: var(--color-background-hover);
	padding: 2px 8px;
	border-radius: 4px;
	color: var(--color-text-maxcontrast);
}

/* Status Badges */
.status-pill {
	display: inline-block;
	padding: 3px 10px;
	border-radius: 12px;
	font-size: 11px;
	font-weight: 600;
}

.status-complete {
	background: rgba(70, 186, 97, 0.15);
	color: #27883d;
}

.status-processing {
	background: rgba(0, 130, 201, 0.15);
	color: #0082c9;
}

.status-pending {
	background: rgba(234, 179, 8, 0.15);
	color: #a16207;
}

.status-cancelled {
	background: rgba(233, 50, 45, 0.15);
	color: #c02824;
}

.shipping-delivered {
	background: rgba(70, 186, 97, 0.15);
	color: #27883d;
}

.shipping-shipped {
	background: rgba(0, 130, 201, 0.15);
	color: #0082c9;
}

.shipping-pending {
	background: rgba(234, 179, 8, 0.15);
	color: #a16207;
}

.shipping-not-required {
	background: var(--color-background-hover);
	color: var(--color-text-maxcontrast);
}
</style>
