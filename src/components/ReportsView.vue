<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import {
	api,
	type SalesSummaryItem,
	type CustomerSegmentData,
	type CustomerItem,
	type CustomerOrder,
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

// Customer Segmentation Filter & Expand State
const searchQuery = ref('')
const segmentFilter = ref<'all' | 'new' | 'returning'>('all')
const sortBy = ref<'spent_desc' | 'orders_desc' | 'name_asc' | 'recent'>('spent_desc')
const expandedCustomers = ref<Set<number>>(new Set())
const customerOrders = ref<Record<number, CustomerOrder[]>>({})
const loadingOrders = ref<Record<number, boolean>>({})

const filteredCustomers = computed(() => {
	let list = customerData.value.topCustomers || []
	const q = searchQuery.value.trim().toLowerCase()

	if (q) {
		list = list.filter((c: CustomerItem) =>
			(c.fullName && c.fullName.toLowerCase().includes(q)) ||
			(c.email && c.email.toLowerCase().includes(q)) ||
			c.customerId.toString().includes(q)
		)
	}

	if (segmentFilter.value !== 'all') {
		list = list.filter((c: CustomerItem) => c.segment === segmentFilter.value)
	}

	list = [...list].sort((a: CustomerItem, b: CustomerItem) => {
		if (sortBy.value === 'spent_desc') return b.totalSpent - a.totalSpent
		if (sortBy.value === 'orders_desc') return b.orderCount - a.orderCount
		if (sortBy.value === 'name_asc') return (a.fullName || '').localeCompare(b.fullName || '')
		if (sortBy.value === 'recent') return (b.lastOrderDate || '').localeCompare(a.lastOrderDate || '')
		return 0
	})

	return list
})

const toggleCustomerExpand = async (customerId: number) => {
	if (expandedCustomers.value.has(customerId)) {
		expandedCustomers.value.delete(customerId)
	} else {
		expandedCustomers.value.add(customerId)
		if (!customerOrders.value[customerId]) {
			loadingOrders.value[customerId] = true
			try {
				customerOrders.value[customerId] = await api.getCustomerOrders(customerId)
			} catch (e) {
				console.error('Failed to load orders for customer', customerId, e)
			} finally {
				loadingOrders.value[customerId] = false
			}
		}
	}
}

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

const formatDate = (dateStr?: string): string => {
	if (!dateStr) return '-'
	try {
		const d = new Date(dateStr)
		return isNaN(d.getTime()) ? dateStr : d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
	} catch {
		return dateStr
	}
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

		<!-- 2. Customer Segmentation Table with Expandable Orders & Filters -->
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

			<!-- Customer Filters Toolbar -->
			<div class="customer-filter-toolbar">
				<div class="search-box">
					<span class="search-icon">🔍</span>
					<input
						v-model="searchQuery"
						type="text"
						placeholder="Filter by customer name, email, or ID..."
						class="search-input"
					/>
					<button v-if="searchQuery" class="clear-btn" @click="searchQuery = ''">✕</button>
				</div>

				<div class="filter-controls">
					<div class="filter-item">
						<label>Segment:</label>
						<select v-model="segmentFilter" class="filter-select">
							<option value="all">All Customers</option>
							<option value="new">First-Time Buyers (1 Order)</option>
							<option value="returning">Returning (>1 Orders)</option>
						</select>
					</div>

					<div class="filter-item">
						<label>Sort By:</label>
						<select v-model="sortBy" class="filter-select">
							<option value="spent_desc">Highest Total Spend</option>
							<option value="orders_desc">Most Orders</option>
							<option value="recent">Most Recent Order</option>
							<option value="name_asc">Customer Name (A-Z)</option>
						</select>
					</div>
				</div>
			</div>

			<div class="table-header-row">
				<h3 class="section-title">
					Customer Directory
					<span class="count-badge">Showing {{ filteredCustomers.length }} of {{ customerData.totalActiveCustomers }}</span>
				</h3>
				<span class="hint-text">💡 Click on any customer to view their complete order history</span>
			</div>

			<div v-if="loading" class="loading-state">Loading customers...</div>
			<div v-else-if="filteredCustomers.length === 0" class="empty-state">
				No customers found matching the search criteria.
			</div>
			<table v-else class="styled-table customer-table">
				<thead>
					<tr>
						<th class="expand-col"></th>
						<th>Customer ID</th>
						<th>Name &amp; Segment</th>
						<th>Email Address</th>
						<th>Last Order</th>
						<th class="text-right">Orders</th>
						<th class="text-right">Total Spent</th>
					</tr>
				</thead>
				<tbody>
					<template v-for="c in filteredCustomers" :key="c.customerId">
						<!-- Main Customer Row -->
						<tr
							class="clickable-row"
							:class="{ 'row-expanded': expandedCustomers.has(c.customerId) }"
							@click="toggleCustomerExpand(c.customerId)"
						>
							<td class="expand-col">
								<span class="expand-toggle" :class="{ 'is-open': expandedCustomers.has(c.customerId) }">
									{{ expandedCustomers.has(c.customerId) ? '▼' : '▶' }}
								</span>
							</td>
							<td>
								<span class="id-badge">#{{ c.customerId }}</span>
							</td>
							<td>
								<div class="customer-name-cell">
									<span class="font-bold">{{ c.fullName }}</span>
									<span
										class="segment-badge"
										:class="c.segment === 'returning' ? 'segment-returning' : 'segment-new'"
									>
										{{ c.segmentLabel || (c.orderCount > 1 ? 'Returning' : 'First-Time') }}
									</span>
								</div>
							</td>
							<td class="email-cell">{{ c.email }}</td>
							<td>{{ formatDate(c.lastOrderDate) }}</td>
							<td class="text-right font-bold">{{ c.orderCount }}</td>
							<td class="text-right text-success font-bold">{{ formatCurrency(c.totalSpent) }}</td>
						</tr>

						<!-- Nested Order History Row -->
						<tr v-if="expandedCustomers.has(c.customerId)" class="nested-orders-row">
							<td colspan="7" class="nested-orders-cell">
								<div class="nested-orders-container">
									<div class="nested-header">
										<h4>📦 Order History for {{ c.fullName }} ({{ c.orderCount }} orders)</h4>
										<span v-if="loadingOrders[c.customerId]" class="mini-loader">Loading orders...</span>
									</div>

									<div v-if="loadingOrders[c.customerId]" class="loading-state mini">
										Fetching order history from local database...
									</div>
									<div v-else-if="!customerOrders[c.customerId] || customerOrders[c.customerId].length === 0" class="empty-state mini">
										No historical orders found for this customer.
									</div>
									<table v-else class="nested-table">
										<thead>
											<tr>
												<th>Order ID</th>
												<th>Date</th>
												<th>Ordered Products</th>
												<th>Order Status</th>
												<th>Payment Status</th>
												<th>Shipping Status</th>
												<th class="text-right">Total</th>
											</tr>
										</thead>
										<tbody>
											<tr v-for="order in customerOrders[c.customerId]" :key="order.orderId">
												<td class="font-bold">
													<span class="order-id-tag">{{ order.customOrderNumber || ('#' + order.orderId) }}</span>
												</td>
												<td class="date-cell">{{ formatDate(order.createdOnUtc) }}</td>
												<td class="items-cell">
													<div class="order-items-list">
														<span
															v-for="(it, iIdx) in order.items"
															:key="iIdx"
															class="item-chip"
														>
															{{ it.productName }} <strong class="qty">&times;{{ it.quantity }}</strong>
														</span>
														<span v-if="order.items.length === 0" class="text-muted">
															{{ order.itemCount || 1 }} item(s)
														</span>
													</div>
												</td>
												<td>
													<span class="status-pill" :class="order.orderStatusClass">
														{{ order.orderStatus }}
													</span>
												</td>
												<td>
													<span class="status-pill" :class="order.paymentStatusClass">
														{{ order.paymentStatus }}
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
							</td>
						</tr>
					</template>
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
	font-size: 13px;
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
	margin: 0;
	font-size: 16px;
	font-weight: 600;
	display: flex;
	align-items: center;
	gap: 10px;
}

.table-header-row {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 14px;
	flex-wrap: wrap;
	gap: 8px;
}

.count-badge {
	background: var(--color-background-hover);
	padding: 2px 8px;
	border-radius: 12px;
	font-size: 12px;
	font-weight: 500;
	color: var(--color-text-maxcontrast);
}

.hint-text {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
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

.text-muted {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}

.kpi-mini-row {
	display: flex;
	gap: 20px;
	margin-bottom: 20px;
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

/* Customer Filter Toolbar */
.customer-filter-toolbar {
	display: flex;
	justify-content: space-between;
	align-items: center;
	flex-wrap: wrap;
	gap: 16px;
	background: var(--color-background-hover);
	padding: 12px 16px;
	border-radius: 10px;
	margin-bottom: 20px;
}

.search-box {
	display: flex;
	align-items: center;
	position: relative;
	flex: 1;
	min-width: 260px;
	max-width: 420px;
}

.search-icon {
	position: absolute;
	left: 10px;
	font-size: 14px;
	color: var(--color-text-maxcontrast);
}

.search-input {
	width: 100%;
	padding: 8px 32px 8px 32px;
	border-radius: 8px;
	border: 1px solid var(--color-border);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 13px;
	outline: none;
}

.search-input:focus {
	border-color: var(--color-primary-element, #0082c9);
}

.clear-btn {
	position: absolute;
	right: 8px;
	background: transparent;
	border: none;
	color: var(--color-text-maxcontrast);
	cursor: pointer;
	font-size: 12px;
}

.filter-controls {
	display: flex;
	align-items: center;
	gap: 16px;
	flex-wrap: wrap;
}

.filter-item {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 13px;
	font-weight: 600;
}

/* Customer Table & Expandable Rows */
.customer-table .clickable-row {
	cursor: pointer;
	transition: background-color 0.15s ease;
}

.customer-table .clickable-row:hover {
	background-color: var(--color-background-hover);
}

.customer-table .row-expanded {
	background-color: var(--color-background-hover);
}

.expand-col {
	width: 32px;
	text-align: center;
	padding-left: 4px;
	padding-right: 4px;
}

.expand-toggle {
	font-size: 11px;
	color: var(--color-text-maxcontrast);
	display: inline-block;
	transition: transform 0.2s ease;
}

.id-badge {
	font-family: monospace;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
}

.customer-name-cell {
	display: flex;
	align-items: center;
	gap: 8px;
}

.segment-badge {
	font-size: 11px;
	padding: 2px 8px;
	border-radius: 12px;
	font-weight: 600;
}

.segment-returning {
	background: rgba(70, 186, 97, 0.15);
	color: #27883d;
}

.segment-new {
	background: rgba(0, 130, 201, 0.15);
	color: #0082c9;
}

.email-cell {
	color: var(--color-text-maxcontrast);
}

/* Nested Orders Sub-Table */
.nested-orders-row td {
	padding: 0;
	border-bottom: 2px solid var(--color-border);
}

.nested-orders-cell {
	background: var(--color-background-hover);
	padding: 12px 16px 20px 36px !important;
}

.nested-orders-container {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: 10px;
	padding: 16px;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.nested-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 12px;
}

.nested-header h4 {
	margin: 0;
	font-size: 14px;
	font-weight: 600;
	color: var(--color-main-text);
}

.mini-loader {
	font-size: 12px;
	color: var(--color-primary-element, #0082c9);
}

.nested-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 12px;
}

.nested-table th {
	text-align: left;
	padding: 8px 6px;
	border-bottom: 1px solid var(--color-border);
	color: var(--color-text-maxcontrast);
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
}

.nested-table td {
	padding: 10px 6px;
	border-bottom: 1px solid var(--color-border);
}

.order-id-tag {
	font-family: monospace;
	background: var(--color-background-hover);
	padding: 2px 6px;
	border-radius: 4px;
}

.items-cell {
	max-width: 320px;
}

.order-items-list {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
}

.item-chip {
	background: var(--color-background-hover);
	border: 1px solid var(--color-border);
	padding: 2px 6px;
	border-radius: 6px;
	font-size: 11px;
	white-space: nowrap;
}

.item-chip .qty {
	color: var(--color-primary-element, #0082c9);
}

/* Status Badges */
.status-pill {
	display: inline-block;
	padding: 2px 8px;
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

.payment-paid {
	background: rgba(70, 186, 97, 0.15);
	color: #27883d;
}

.payment-pending, .payment-authorized {
	background: rgba(234, 179, 8, 0.15);
	color: #a16207;
}

.payment-refunded {
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

.empty-state, .loading-state {
	padding: 40px;
	text-align: center;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.empty-state.mini, .loading-state.mini {
	padding: 20px;
	font-size: 12px;
}
</style>
