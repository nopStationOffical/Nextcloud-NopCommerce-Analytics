import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const buildUrl = (path: string): string => {
	try {
		return generateUrl(`/apps/nopstation_analytics${path}`)
	} catch (e) {
		return `/index.php/apps/nopstation_analytics${path}`
	}
}

export interface KpiData {
	totalOrders: number
	totalRevenue: number
	avgOrderValue: number
	totalProfit: number
	totalShipping: number
	totalTax: number
	uniqueCustomers: number
	completedOrders: number
	completionRate: number
}

export interface TrendData {
	labels: string[]
	revenue: number[]
	orders: number[]
	profit: number[]
}

export interface BestsellerItem {
	productId: number
	productName: string
	totalQuantity: number
	totalAmount: number
}

export interface CustomerSegmentData {
	newCustomers: number
	returningCustomers: number
	totalActiveCustomers: number
	topCustomers: Array<{
		customerId: number
		email: string
		fullName: string
		orderCount: number
		totalSpent: number
	}>
}

export interface SalesSummaryItem {
	summary: string
	numberOfOrders: number
	profit: number
	shipping: number
	tax: number
	orderTotal: number
}

export interface LowStockItem {
	productId: number
	name: string
	sku: string | null
	stockQuantity: number
	price: number
}

export interface SettingsData {
	apiUrl: string
	adminEmail: string
	webhookSecret: string
	lastSyncTimestamp: string
	hasToken: boolean
}

export interface SyncLogItem {
	id: number
	syncType: string
	entityType: string
	recordsProcessed: number
	status: string
	errorMessage: string | null
	createdAt: string
}

export const api = {
	// Settings
	async getSettings(): Promise<SettingsData> {
		const res = await axios.get(buildUrl('/api/v1/settings'))
		return res.data.Data
	},

	async saveSettings(data: {
		apiUrl: string
		adminEmail: string
		adminPassword?: string
		webhookSecret?: string
	}): Promise<void> {
		await axios.post(buildUrl('/api/v1/settings'), data)
	},

	async testConnection(): Promise<{ connected: boolean; baseUrl: string; adminEmail: string }> {
		const res = await axios.post(buildUrl('/api/v1/settings/test'))
		return res.data.Data
	},

	// Analytics
	async getKpis(params: { startDate?: string; endDate?: string; storeId?: number } = {}): Promise<KpiData> {
		const res = await axios.get(buildUrl('/api/v1/analytics/kpi'), { params })
		return res.data.Data
	},

	async getTrends(params: { interval?: string; startDate?: string; endDate?: string; storeId?: number } = {}): Promise<TrendData> {
		const res = await axios.get(buildUrl('/api/v1/analytics/trends'), { params })
		return res.data.Data
	},

	async getBestsellers(params: { limit?: number; startDate?: string; endDate?: string; storeId?: number } = {}): Promise<BestsellerItem[]> {
		const res = await axios.get(buildUrl('/api/v1/analytics/bestsellers'), { params })
		return res.data.Data
	},

	async getCustomers(params: { startDate?: string; endDate?: string } = {}): Promise<CustomerSegmentData> {
		const res = await axios.get(buildUrl('/api/v1/analytics/customers'), { params })
		return res.data.Data
	},

	async getSummary(params: { startDate?: string; endDate?: string; storeId?: number; groupBy?: string } = {}): Promise<SalesSummaryItem[]> {
		const res = await axios.get(buildUrl('/api/v1/analytics/summary'), { params })
		return res.data.Data
	},

	async getLowStock(threshold: number = 10): Promise<LowStockItem[]> {
		const res = await axios.get(buildUrl('/api/v1/analytics/lowstock'), { params: { threshold } })
		return res.data.Data
	},

	async exportSummary(params: { startDate?: string; endDate?: string; storeId?: number; groupBy?: string; reportType?: string } = {}): Promise<any> {
		const res = await axios.post(buildUrl('/api/v1/analytics/export'), null, { params })
		return res.data
	},

	async exportBestsellers(limit: number = 50): Promise<any> {
		const res = await axios.post(buildUrl('/api/v1/analytics/export/bestsellers'), null, { params: { limit } })
		return res.data
	},

	// Sync
	async runSync(syncType: 'full' | 'incremental' = 'full'): Promise<any> {
		const res = await axios.post(buildUrl('/api/v1/sync/run'), { syncType })
		return res.data
	},

	async getSyncStatus(): Promise<{ lastSync: string | null; logs: SyncLogItem[] }> {
		const res = await axios.get(buildUrl('/api/v1/sync/status'))
		return res.data.Data
	},
}
