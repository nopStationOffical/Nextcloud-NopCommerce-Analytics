<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import {
	api,
	type SalesSummaryItem,
	type CustomerSegmentData,
	type CustomerItem,
	type CustomerOrder,
	type PeriodOrder,
	type LowStockItem,
} from '../services/api'

const props = defineProps<{ subTab?: string }>()
const emit = defineEmits<{ (e: 'update:subTab', tab: 'summary' | 'customers' | 'lowstock'): void }>()

const initialTab = (props.subTab && ['summary', 'customers', 'lowstock'].includes(props.subTab))
	? (props.subTab as 'summary' | 'customers' | 'lowstock')
	: 'summary'

const activeSubTab = ref<'summary' | 'customers' | 'lowstock'>(initialTab)

watch(() => props.subTab, (newVal) => {
	if (newVal && ['summary', 'customers', 'lowstock'].includes(newVal)) {
		activeSubTab.value = newVal as 'summary' | 'customers' | 'lowstock'
	}
})

watch(activeSubTab, (newTab) => {
	emit('update:subTab', newTab)
})

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

// =========================================================================
// 1. Sales Summary Date-Row Expandable Orders State
// =========================================================================
const expandedPeriods = ref<Set<string>>(new Set())
const periodOrders = ref<Record<string, PeriodOrder[]>>({})
const loadingPeriodOrders = ref<Record<string, boolean>>({})
const periodOrderPages = ref<Record<string, number>>({})
const expandedPeriodOrderItems = ref<Set<number>>(new Set())
const PERIOD_ORDERS_PAGE_SIZE = 5

const togglePeriodExpand = async (period: string) => {
	if (expandedPeriods.value.has(period)) {
		expandedPeriods.value.delete(period)
	} else {
		expandedPeriods.value.add(period)
		if (!periodOrderPages.value[period]) {
			periodOrderPages.value[period] = 1
		}
		if (!periodOrders.value[period]) {
			loadingPeriodOrders.value[period] = true
			try {
				periodOrders.value[period] = await api.getPeriodOrders(period, groupBy.value)
			} catch (e) {
				console.error('Failed to load orders for period', period, e)
			} finally {
				loadingPeriodOrders.value[period] = false
			}
		}
	}
}

const togglePeriodOrderItem = (orderId: number) => {
	if (expandedPeriodOrderItems.value.has(orderId)) {
		expandedPeriodOrderItems.value.delete(orderId)
	} else {
		expandedPeriodOrderItems.value.add(orderId)
	}
}

const getPaginatedPeriodOrders = (period: string) => {
	const orders = periodOrders.value[period] || []
	const page = periodOrderPages.value[period] || 1
	const start = (page - 1) * PERIOD_ORDERS_PAGE_SIZE
	return orders.slice(start, start + PERIOD_ORDERS_PAGE_SIZE)
}

const getPeriodOrdersTotalPages = (period: string) => {
	const count = (periodOrders.value[period] || []).length
	return Math.ceil(count / PERIOD_ORDERS_PAGE_SIZE) || 1
}

const setPeriodOrderPage = (period: string, page: number) => {
	const total = getPeriodOrdersTotalPages(period)
	if (page >= 1 && page <= total) {
		periodOrderPages.value[period] = page
	}
}

// =========================================================================
// 2. Customer Segmentation Filter, Dual Pagination & 3-Level State
// =========================================================================
const customerStartDate = ref('')
const customerEndDate = ref('')
const activePreset = ref<'all' | '30days' | '90days' | 'year' | 'custom'>('all')

const searchQuery = ref('')
const segmentFilter = ref<'all' | 'new' | 'returning'>('all')
const sortBy = ref<'spent_desc' | 'orders_desc' | 'name_asc' | 'recent'>('spent_desc')

const customerPage = ref(1)
const customerPageSize = ref(10)

const expandedCustomers = ref<Set<number>>(new Set())
const customerOrders = ref<Record<number, CustomerOrder[]>>({})
const loadingOrders = ref<Record<number, boolean>>({})
const childOrderPages = ref<Record<number, number>>({})
const expandedCustomerOrderItems = ref<Set<number>>(new Set())
const CHILD_ORDERS_PAGE_SIZE = 5

const setCustomerDatePreset = (preset: 'all' | '30days' | '90days' | 'year') => {
	activePreset.value = preset
	const now = new Date()
	if (preset === 'all') {
		customerStartDate.value = ''
		customerEndDate.value = ''
	} else if (preset === '30days') {
		const start = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000)
		customerStartDate.value = start.toISOString().slice(0, 10)
		customerEndDate.value = now.toISOString().slice(0, 10)
	} else if (preset === '90days') {
		const start = new Date(now.getTime() - 90 * 24 * 60 * 60 * 1000)
		customerStartDate.value = start.toISOString().slice(0, 10)
		customerEndDate.value = now.toISOString().slice(0, 10)
	} else if (preset === 'year') {
		const start = new Date(now.getFullYear(), 0, 1)
		customerStartDate.value = start.toISOString().slice(0, 10)
		customerEndDate.value = now.toISOString().slice(0, 10)
	}
	loadCustomerReport()
}

const onCustomDateChange = () => {
	activePreset.value = 'custom'
	loadCustomerReport()
}

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

const totalCustomerPages = computed(() => {
	return Math.ceil(filteredCustomers.value.length / customerPageSize.value) || 1
})

const paginatedCustomers = computed(() => {
	const start = (customerPage.value - 1) * customerPageSize.value
	return filteredCustomers.value.slice(start, start + customerPageSize.value)
})

watch([searchQuery, segmentFilter, sortBy, customerPageSize], () => {
	customerPage.value = 1
})

const setCustomerPage = (p: number) => {
	if (p >= 1 && p <= totalCustomerPages.value) {
		customerPage.value = p
	}
}

const toggleCustomerExpand = async (customerId: number) => {
	if (expandedCustomers.value.has(customerId)) {
		expandedCustomers.value.delete(customerId)
	} else {
		expandedCustomers.value.add(customerId)
		if (!childOrderPages.value[customerId]) {
			childOrderPages.value[customerId] = 1
		}
		if (!customerOrders.value[customerId]) {
			loadingOrders.value[customerId] = true
			try {
				const params: { startDate?: string; endDate?: string } = {}
				if (customerStartDate.value) params.startDate = customerStartDate.value
				if (customerEndDate.value) params.endDate = customerEndDate.value
				customerOrders.value[customerId] = await api.getCustomerOrders(customerId, params)
			} catch (e) {
				console.error('Failed to load orders for customer', customerId, e)
			} finally {
				loadingOrders.value[customerId] = false
			}
		}
	}
}

const toggleCustomerOrderItem = (orderId: number) => {
	if (expandedCustomerOrderItems.value.has(orderId)) {
		expandedCustomerOrderItems.value.delete(orderId)
	} else {
		expandedCustomerOrderItems.value.add(orderId)
	}
}

const getPaginatedCustomerOrders = (customerId: number) => {
	const orders = customerOrders.value[customerId] || []
	const page = childOrderPages.value[customerId] || 1
	const start = (page - 1) * CHILD_ORDERS_PAGE_SIZE
	return orders.slice(start, start + CHILD_ORDERS_PAGE_SIZE)
}

const getCustomerOrdersTotalPages = (customerId: number) => {
	const count = (customerOrders.value[customerId] || []).length
	return Math.ceil(count / CHILD_ORDERS_PAGE_SIZE) || 1
}

const setChildOrderPage = (customerId: number, page: number) => {
	const total = getCustomerOrdersTotalPages(customerId)
	if (page >= 1 && page <= total) {
		childOrderPages.value[customerId] = page
	}
}

// =========================================================================
// 3. API Load Functions
// =========================================================================
const loadCustomerReport = async () => {
	loading.value = true
	expandedCustomers.value.clear()
	customerOrders.value = {}
	customerPage.value = 1
	try {
		const params: { startDate?: string; endDate?: string } = {}
		if (customerStartDate.value) params.startDate = customerStartDate.value
		if (customerEndDate.value) params.endDate = customerEndDate.value
		customerData.value = await api.getCustomers(params)
	} catch (err) {
		console.error('Failed to load customers', err)
	} finally {
		loading.value = false
	}
}

const loadReportData = async () => {
	loading.value = true
	exportMsg.value = ''
	try {
		if (activeSubTab.value === 'summary') {
			expandedPeriods.value.clear()
			periodOrders.value = {}
			summaryList.value = await api.getSummary({ groupBy: groupBy.value })
		} else if (activeSubTab.value === 'customers') {
			await loadCustomerReport()
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

		<!-- ================================================================= -->
		<!-- 1. Sales Summary Table (Expandable Date Rows with Child Orders)     -->
		<!-- ================================================================= -->
		<div v-if="activeSubTab === 'summary'" class="panel-card">
			<div class="table-header-row">
				<h3 class="section-title">
					Sales Breakdown Summary
					<span class="count-badge">{{ summaryList.length }} periods</span>
				</h3>
				<span class="hint-text">💡 Click on any row to expand and inspect the orders for that period</span>
			</div>

			<div v-if="loading" class="loading-state">Loading summary data...</div>
			<div v-else-if="summaryList.length === 0" class="empty-state">
				No sales records available. Try running a data sync from Settings.
			</div>
			<table v-else class="styled-table period-table">
				<thead>
					<tr>
						<th class="expand-col"></th>
						<th>Date / Period</th>
						<th class="text-right">Orders</th>
						<th class="text-right">Profit</th>
						<th class="text-right">Shipping</th>
						<th class="text-right">Tax</th>
						<th class="text-right">Order Total</th>
					</tr>
				</thead>
				<tbody>
					<template v-for="(row, idx) in summaryList" :key="idx">
						<!-- Main Date/Period Row -->
						<tr
							class="clickable-row"
							:class="{ 'row-expanded': expandedPeriods.has(row.summary) }"
							@click="togglePeriodExpand(row.summary)"
						>
							<td class="expand-col">
								<span class="expand-toggle" :class="{ 'is-open': expandedPeriods.has(row.summary) }">
									{{ expandedPeriods.has(row.summary) ? '▼' : '▶' }}
								</span>
							</td>
							<td class="font-bold">{{ row.summary }}</td>
							<td class="text-right font-bold">{{ row.numberOfOrders }}</td>
							<td class="text-right text-success">{{ formatCurrency(row.profit) }}</td>
							<td class="text-right">{{ formatCurrency(row.shipping) }}</td>
							<td class="text-right">{{ formatCurrency(row.tax) }}</td>
							<td class="text-right font-bold text-success">{{ formatCurrency(row.orderTotal) }}</td>
						</tr>

						<!-- Nested Orders Row for this Date/Period -->
						<tr v-if="expandedPeriods.has(row.summary)" class="nested-orders-row">
							<td colspan="7" class="nested-orders-cell">
								<div class="nested-orders-container">
									<div class="nested-header">
										<h4>📦 Orders for {{ row.summary }} ({{ (periodOrders[row.summary] || []).length }} orders)</h4>
										<span v-if="loadingPeriodOrders[row.summary]" class="mini-loader">Loading orders...</span>
									</div>

									<div v-if="loadingPeriodOrders[row.summary]" class="loading-state mini">
										Fetching orders from local database...
									</div>
									<div v-else-if="!periodOrders[row.summary] || periodOrders[row.summary].length === 0" class="empty-state mini">
										No individual orders found for this period.
									</div>
									<div v-else>
										<table class="nested-table">
											<thead>
												<tr>
													<th class="expand-col"></th>
													<th>Order #</th>
													<th>Customer</th>
													<th>Date</th>
													<th>Payment Method</th>
													<th>Shipping Method</th>
													<th class="text-right">Discount</th>
													<th>Order Status</th>
													<th>Payment</th>
													<th>Shipping</th>
													<th class="text-right">Total</th>
												</tr>
											</thead>
											<tbody>
												<template v-for="order in getPaginatedPeriodOrders(row.summary)" :key="order.orderId">
													<!-- Period Order Row -->
													<tr
														class="clickable-subrow"
														:class="{ 'subrow-expanded': expandedPeriodOrderItems.has(order.orderId) }"
														@click="togglePeriodOrderItem(order.orderId)"
													>
														<td class="expand-col">
															<span class="expand-toggle mini">
																{{ expandedPeriodOrderItems.has(order.orderId) ? '▼' : '▶' }}
															</span>
														</td>
														<td class="font-bold">
															<span class="order-id-tag">{{ order.customOrderNumber || ('#' + order.orderId) }}</span>
														</td>
														<td>
															<div class="customer-info-cell">
																<span class="cust-name font-bold">{{ order.customerName }}</span>
																<span class="cust-email">{{ order.customerEmail }}</span>
															</div>
														</td>
														<td class="date-cell">{{ formatDate(order.createdOnUtc) }}</td>
														<td>
															<span class="method-tag">{{ order.paymentMethod || 'N/A' }}</span>
														</td>
														<td>
															<span class="method-tag">{{ order.shippingMethod || 'Standard' }}</span>
														</td>
														<td class="text-right">
															{{ order.orderDiscount > 0 ? formatCurrency(order.orderDiscount) : '-' }}
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

													<!-- Level 3: Nested Order Items Row -->
													<tr v-if="expandedPeriodOrderItems.has(order.orderId)" class="level-3-row">
														<td colspan="11" class="level-3-cell">
															<div class="level-3-container">
																<div class="level-3-header">
																	<h5>🛍️ Line Items for Order #{{ order.customOrderNumber }}</h5>
																</div>
																<table class="items-subtable">
																	<thead>
																		<tr>
																			<th>Product Name</th>
																			<th>SKU</th>
																			<th class="text-right">Quantity</th>
																			<th class="text-right">Unit Price</th>
																			<th class="text-right">Subtotal</th>
																		</tr>
																	</thead>
																	<tbody>
																		<tr v-for="(it, itIdx) in order.items" :key="itIdx">
																			<td class="font-bold">{{ it.productName }}</td>
																			<td class="sku-cell">{{ it.sku || '-' }}</td>
																			<td class="text-right font-bold">{{ it.quantity }}</td>
																			<td class="text-right">{{ formatCurrency(it.unitPrice) }}</td>
																			<td class="text-right font-bold text-success">{{ formatCurrency(it.totalPrice) }}</td>
																		</tr>
																		<tr v-if="!order.items || order.items.length === 0">
																			<td colspan="5" class="empty-state mini">No item breakdown available.</td>
																		</tr>
																	</tbody>
																</table>
															</div>
														</td>
													</tr>
												</template>
											</tbody>
										</table>

										<!-- Compact Child Orders Pagination -->
										<div v-if="(periodOrders[row.summary] || []).length > PERIOD_ORDERS_PAGE_SIZE" class="compact-pagination-bar">
											<span class="pagination-info">
												Showing {{ ((periodOrderPages[row.summary] || 1) - 1) * PERIOD_ORDERS_PAGE_SIZE + 1 }}
												to {{ Math.min((periodOrderPages[row.summary] || 1) * PERIOD_ORDERS_PAGE_SIZE, (periodOrders[row.summary] || []).length) }}
												of {{ (periodOrders[row.summary] || []).length }} orders
											</span>
											<div class="pagination-buttons">
												<button
													class="page-btn-sm"
													:disabled="(periodOrderPages[row.summary] || 1) === 1"
													@click.stop="setPeriodOrderPage(row.summary, (periodOrderPages[row.summary] || 1) - 1)"
												>
													◀ Prev
												</button>
												<span class="page-badge">Page {{ periodOrderPages[row.summary] || 1 }} of {{ getPeriodOrdersTotalPages(row.summary) }}</span>
												<button
													class="page-btn-sm"
													:disabled="(periodOrderPages[row.summary] || 1) >= getPeriodOrdersTotalPages(row.summary)"
													@click.stop="setPeriodOrderPage(row.summary, (periodOrderPages[row.summary] || 1) + 1)"
												>
													Next ▶
												</button>
											</div>
										</div>
									</div>
								</div>
							</td>
						</tr>
					</template>
				</tbody>
			</table>
		</div>

		<!-- ================================================================= -->
		<!-- 2. Customer Segmentation Table (3-Level, Date Filter & Dual Paging) -->
		<!-- ================================================================= -->
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

			<!-- Customer Date Filter Toolbar -->
			<div class="date-filter-toolbar">
				<div class="preset-pills">
					<button
						class="preset-btn"
						:class="{ active: activePreset === 'all' }"
						@click="setCustomerDatePreset('all')"
					>
						All Time
					</button>
					<button
						class="preset-btn"
						:class="{ active: activePreset === '30days' }"
						@click="setCustomerDatePreset('30days')"
					>
						Last 30 Days
					</button>
					<button
						class="preset-btn"
						:class="{ active: activePreset === '90days' }"
						@click="setCustomerDatePreset('90days')"
					>
						Last 90 Days
					</button>
					<button
						class="preset-btn"
						:class="{ active: activePreset === 'year' }"
						@click="setCustomerDatePreset('year')"
					>
						This Year
					</button>
				</div>

				<div class="date-inputs">
					<div class="date-field">
						<label>From:</label>
						<input
							v-model="customerStartDate"
							type="date"
							class="date-input"
							@change="onCustomDateChange"
						/>
					</div>
					<div class="date-field">
						<label>To:</label>
						<input
							v-model="customerEndDate"
							type="date"
							class="date-input"
							@change="onCustomDateChange"
						/>
					</div>
					<button
						v-if="customerStartDate || customerEndDate"
						class="clear-date-btn"
						@click="setCustomerDatePreset('all')"
					>
						✕ Clear Dates
					</button>
				</div>
			</div>

			<!-- Customer Search & Sorter Toolbar -->
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
					<span class="count-badge">Showing {{ paginatedCustomers.length }} of {{ filteredCustomers.length }} customers</span>
				</h3>
				<span class="hint-text">💡 Click on any customer to inspect their complete order history and line items</span>
			</div>

			<div v-if="loading" class="loading-state">Loading customers...</div>
			<div v-else-if="filteredCustomers.length === 0" class="empty-state">
				No customers found matching the search criteria.
			</div>
			<div v-else>
				<table class="styled-table customer-table">
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
						<template v-for="c in paginatedCustomers" :key="c.customerId">
							<!-- Level 1: Main Customer Row -->
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

							<!-- Level 2: Customer Orders Nested Child Row -->
							<tr v-if="expandedCustomers.has(c.customerId)" class="nested-orders-row">
								<td colspan="7" class="nested-orders-cell">
									<div class="nested-orders-container">
										<div class="nested-header">
											<h4>📦 Order History for {{ c.fullName }} ({{ (customerOrders[c.customerId] || []).length }} orders)</h4>
											<span v-if="loadingOrders[c.customerId]" class="mini-loader">Loading orders...</span>
										</div>

										<div v-if="loadingOrders[c.customerId]" class="loading-state mini">
											Fetching order history from local database...
										</div>
										<div v-else-if="!customerOrders[c.customerId] || customerOrders[c.customerId].length === 0" class="empty-state mini">
											No historical orders found for this customer.
										</div>
										<div v-else>
											<table class="nested-table">
												<thead>
													<tr>
														<th class="expand-col"></th>
														<th>Order #</th>
														<th>Date</th>
														<th>Payment Method</th>
														<th>Shipping Method</th>
														<th class="text-right">Discount</th>
														<th>Order Status</th>
														<th>Payment</th>
														<th>Shipping</th>
														<th class="text-right">Total</th>
													</tr>
												</thead>
												<tbody>
													<template v-for="order in getPaginatedCustomerOrders(c.customerId)" :key="order.orderId">
														<!-- Level 2 Order Row -->
														<tr
															class="clickable-subrow"
															:class="{ 'subrow-expanded': expandedCustomerOrderItems.has(order.orderId) }"
															@click.stop="toggleCustomerOrderItem(order.orderId)"
														>
															<td class="expand-col">
																<span class="expand-toggle mini">
																	{{ expandedCustomerOrderItems.has(order.orderId) ? '▼' : '▶' }}
																</span>
															</td>
															<td class="font-bold">
																<span class="order-id-tag">{{ order.customOrderNumber || ('#' + order.orderId) }}</span>
															</td>
															<td class="date-cell">{{ formatDate(order.createdOnUtc) }}</td>
															<td>
																<span class="method-tag">{{ order.paymentMethod || 'N/A' }}</span>
															</td>
															<td>
																<span class="method-tag">{{ order.shippingMethod || 'Standard' }}</span>
															</td>
															<td class="text-right">
																{{ order.orderDiscount > 0 ? formatCurrency(order.orderDiscount) : '-' }}
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

														<!-- Level 3: Order Items Sub-Table Row -->
														<tr v-if="expandedCustomerOrderItems.has(order.orderId)" class="level-3-row">
															<td colspan="10" class="level-3-cell">
																<div class="level-3-container">
																	<div class="level-3-header">
																		<h5>🛍️ Line Items for Order #{{ order.customOrderNumber }}</h5>
																	</div>
																	<table class="items-subtable">
																		<thead>
																			<tr>
																				<th>Product Name</th>
																				<th>SKU</th>
																				<th class="text-right">Quantity</th>
																				<th class="text-right">Unit Price</th>
																				<th class="text-right">Subtotal</th>
																			</tr>
																		</thead>
																		<tbody>
																			<tr v-for="(it, itIdx) in order.items" :key="itIdx">
																				<td class="font-bold">{{ it.productName }}</td>
																				<td class="sku-cell">{{ it.sku || '-' }}</td>
																				<td class="text-right font-bold">{{ it.quantity }}</td>
																				<td class="text-right">{{ formatCurrency(it.unitPrice) }}</td>
																				<td class="text-right font-bold text-success">{{ formatCurrency(it.totalPrice) }}</td>
																			</tr>
																			<tr v-if="!order.items || order.items.length === 0">
																				<td colspan="5" class="empty-state mini">No items breakdown available.</td>
																			</tr>
																		</tbody>
																	</table>
																</div>
															</td>
														</tr>
													</template>
												</tbody>
											</table>

											<!-- Compact Child Orders Pagination -->
											<div v-if="(customerOrders[c.customerId] || []).length > CHILD_ORDERS_PAGE_SIZE" class="compact-pagination-bar">
												<span class="pagination-info">
													Showing {{ ((childOrderPages[c.customerId] || 1) - 1) * CHILD_ORDERS_PAGE_SIZE + 1 }}
													to {{ Math.min((childOrderPages[c.customerId] || 1) * CHILD_ORDERS_PAGE_SIZE, (customerOrders[c.customerId] || []).length) }}
													of {{ (customerOrders[c.customerId] || []).length }} orders
												</span>
												<div class="pagination-buttons">
													<button
														class="page-btn-sm"
														:disabled="(childOrderPages[c.customerId] || 1) === 1"
														@click.stop="setChildOrderPage(c.customerId, (childOrderPages[c.customerId] || 1) - 1)"
													>
														◀ Prev
													</button>
													<span class="page-badge">Page {{ childOrderPages[c.customerId] || 1 }} of {{ getCustomerOrdersTotalPages(c.customerId) }}</span>
													<button
														class="page-btn-sm"
														:disabled="(childOrderPages[c.customerId] || 1) >= getCustomerOrdersTotalPages(c.customerId)"
														@click.stop="setChildOrderPage(c.customerId, (childOrderPages[c.customerId] || 1) + 1)"
													>
														Next ▶
													</button>
												</div>
											</div>
										</div>
									</div>
								</td>
							</tr>
						</template>
					</tbody>
				</table>

				<!-- Parent Customers Pagination Toolbar -->
				<div class="parent-pagination-toolbar">
					<div class="pagination-summary">
						Showing {{ (customerPage - 1) * customerPageSize + 1 }} to {{ Math.min(customerPage * customerPageSize, filteredCustomers.length) }} of {{ filteredCustomers.length }} customers
					</div>

					<div class="pagination-controls">
						<button
							class="page-btn"
							:disabled="customerPage === 1"
							@click="setCustomerPage(customerPage - 1)"
						>
							◀ Previous
						</button>

						<div class="page-numbers">
							<button
								v-for="p in totalCustomerPages"
								:key="p"
								class="page-num-btn"
								:class="{ active: customerPage === p }"
								@click="setCustomerPage(p)"
							>
								{{ p }}
							</button>
						</div>

						<button
							class="page-btn"
							:disabled="customerPage >= totalCustomerPages"
							@click="setCustomerPage(customerPage + 1)"
						>
							Next ▶
						</button>
					</div>

					<div class="page-size-selector">
						<label>Per page:</label>
						<select v-model="customerPageSize" class="filter-select">
							<option :value="5">5</option>
							<option :value="10">10</option>
							<option :value="25">25</option>
							<option :value="50">50</option>
						</select>
					</div>
				</div>
			</div>
		</div>

		<!-- ================================================================= -->
		<!-- 3. Low Stock Alerts Table                                          -->
		<!-- ================================================================= -->
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

/* Date Filter Toolbar */
.date-filter-toolbar {
	display: flex;
	justify-content: space-between;
	align-items: center;
	flex-wrap: wrap;
	gap: 12px;
	background: var(--color-background-hover);
	padding: 10px 16px;
	border-radius: 10px;
	margin-bottom: 16px;
}

.preset-pills {
	display: flex;
	gap: 6px;
	flex-wrap: wrap;
}

.preset-btn {
	padding: 6px 12px;
	border: 1px solid var(--color-border);
	background: var(--color-main-background);
	color: var(--color-main-text);
	border-radius: 6px;
	font-size: 12px;
	font-weight: 600;
	cursor: pointer;
	transition: all 0.15s ease;
}

.preset-btn.active {
	background: var(--color-primary-element, #0082c9);
	color: var(--color-primary-element-text, #ffffff);
	border-color: var(--color-primary-element, #0082c9);
}

.date-inputs {
	display: flex;
	align-items: center;
	gap: 12px;
	flex-wrap: wrap;
}

.date-field {
	display: flex;
	align-items: center;
	gap: 6px;
	font-size: 12px;
	font-weight: 600;
}

.date-input {
	padding: 6px 10px;
	border-radius: 6px;
	border: 1px solid var(--color-border);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 12px;
	outline: none;
}

.clear-date-btn {
	background: transparent;
	border: 1px dashed var(--color-border);
	padding: 6px 10px;
	border-radius: 6px;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	cursor: pointer;
}

.clear-date-btn:hover {
	color: var(--color-main-text);
	border-color: var(--color-main-text);
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

/* Expandable Rows & Chevrons */
.clickable-row {
	cursor: pointer;
	transition: background-color 0.15s ease;
}

.clickable-row:hover {
	background-color: var(--color-background-hover);
}

.row-expanded {
	background-color: var(--color-background-hover);
}

.clickable-subrow {
	cursor: pointer;
	transition: background-color 0.15s ease;
}

.clickable-subrow:hover {
	background-color: rgba(0, 130, 201, 0.05);
}

.subrow-expanded {
	background-color: rgba(0, 130, 201, 0.08);
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

.expand-toggle.mini {
	font-size: 9px;
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

.customer-info-cell {
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.cust-name {
	font-size: 13px;
}

.cust-email {
	font-size: 11px;
	color: var(--color-text-maxcontrast);
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

.method-tag {
	background: var(--color-background-hover);
	padding: 2px 6px;
	border-radius: 4px;
	font-size: 11px;
	color: var(--color-text-maxcontrast);
}

/* Nested Orders Sub-Table (Level 2) */
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

/* Level 3: Order Items Nested Sub-Table */
.level-3-row td {
	padding: 0 !important;
	border-bottom: 1px solid var(--color-border);
}

.level-3-cell {
	background: var(--color-background-hover) !important;
	padding: 10px 14px 16px 36px !important;
}

.level-3-container {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-left: 3px solid var(--color-primary-element, #0082c9);
	border-radius: 8px;
	padding: 12px 16px;
}

.level-3-header {
	margin-bottom: 8px;
}

.level-3-header h5 {
	margin: 0;
	font-size: 12px;
	font-weight: 600;
	color: var(--color-main-text);
}

.items-subtable {
	width: 100%;
	border-collapse: collapse;
	font-size: 12px;
}

.items-subtable th {
	text-align: left;
	padding: 6px 8px;
	border-bottom: 1px solid var(--color-border);
	color: var(--color-text-maxcontrast);
	font-size: 11px;
	font-weight: 600;
}

.items-subtable td {
	padding: 6px 8px;
	border-bottom: 1px solid var(--color-border);
}

.sku-cell {
	font-family: monospace;
	color: var(--color-text-maxcontrast);
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

/* Compact Child Pagination */
.compact-pagination-bar {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-top: 12px;
	padding-top: 10px;
	border-top: 1px solid var(--color-border);
	font-size: 12px;
}

.pagination-buttons {
	display: flex;
	align-items: center;
	gap: 8px;
}

.page-btn-sm {
	padding: 4px 10px;
	border: 1px solid var(--color-border);
	background: var(--color-main-background);
	color: var(--color-main-text);
	border-radius: 4px;
	font-size: 11px;
	font-weight: 600;
	cursor: pointer;
}

.page-btn-sm:disabled {
	opacity: 0.5;
	cursor: not-allowed;
}

.page-badge {
	font-size: 11px;
	color: var(--color-text-maxcontrast);
	font-weight: 600;
}

/* Parent Customer Pagination Toolbar */
.parent-pagination-toolbar {
	display: flex;
	justify-content: space-between;
	align-items: center;
	flex-wrap: wrap;
	gap: 16px;
	margin-top: 16px;
	padding-top: 14px;
	border-top: 1px solid var(--color-border);
}

.pagination-summary {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	font-weight: 500;
}

.pagination-controls {
	display: flex;
	align-items: center;
	gap: 8px;
}

.page-btn {
	padding: 6px 12px;
	border: 1px solid var(--color-border);
	background: var(--color-main-background);
	color: var(--color-main-text);
	border-radius: 6px;
	font-size: 12px;
	font-weight: 600;
	cursor: pointer;
}

.page-btn:disabled {
	opacity: 0.5;
	cursor: not-allowed;
}

.page-numbers {
	display: flex;
	gap: 4px;
}

.page-num-btn {
	min-width: 32px;
	height: 32px;
	padding: 0 8px;
	border: 1px solid var(--color-border);
	background: var(--color-main-background);
	color: var(--color-main-text);
	border-radius: 6px;
	font-size: 12px;
	font-weight: 600;
	cursor: pointer;
}

.page-num-btn.active {
	background: var(--color-primary-element, #0082c9);
	color: var(--color-primary-element-text, #ffffff);
	border-color: var(--color-primary-element, #0082c9);
}

.page-size-selector {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 13px;
	font-weight: 600;
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
