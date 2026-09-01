<script setup lang="ts">
import { ref } from 'vue'
import { api } from '../services/api'

const exporting = ref(false)
const exportResult = ref<string | null>(null)
const errorMsg = ref<string | null>(null)

const triggerExport = async (type: 'day' | 'month' | 'bestsellers' = 'day') => {
	exporting.value = true
	exportResult.value = null
	errorMsg.value = null
	try {
		let res: any
		if (type === 'bestsellers') {
			res = await api.exportBestsellers(50)
		} else {
			res = await api.exportSummary({ groupBy: type })
		}
		exportResult.value = res.Message || 'CSV report saved to Nextcloud Files.'
	} catch (e: any) {
		errorMsg.value = e.message || 'Failed to export report.'
	} finally {
		exporting.value = false
	}
}
</script>

<template>
	<div class="exports-container">
		<div class="header-bar">
			<h2>Scheduled Reports &amp; Data Exports</h2>
			<p class="subtitle">Generate and archive compliance-ready e-commerce reports into Nextcloud Files</p>
		</div>

		<div v-if="exportResult" class="banner success">
			{{ exportResult }}
		</div>
		<div v-if="errorMsg" class="banner error">
			{{ errorMsg }}
		</div>

		<div class="export-cards-grid">
			<!-- Card 1 -->
			<div class="export-card">
				<div class="icon">📊</div>
				<h3>Daily Sales Summary</h3>
				<p>Export daily revenue, orders count, taxes, fulfillment, and profit figures in CSV format.</p>
				<button class="primary-btn" :disabled="exporting" @click="triggerExport('day')">
					{{ exporting ? 'Exporting...' : 'Generate Daily CSV' }}
				</button>
			</div>

			<!-- Card 2 -->
			<div class="export-card">
				<div class="icon">📈</div>
				<h3>Monthly Trend Summary</h3>
				<p>Monthly aggregate sales numbers for financial accounting, quarterly reviews, and tax audits.</p>
				<button class="primary-btn" :disabled="exporting" @click="triggerExport('month')">
					{{ exporting ? 'Exporting...' : 'Generate Monthly CSV' }}
				</button>
			</div>

			<!-- Card 3 -->
			<div class="export-card">
				<div class="icon">🏆</div>
				<h3>Top Selling Products</h3>
				<p>Product performance breakdown by quantity sold and revenue contributions.</p>
				<button class="primary-btn" :disabled="exporting" @click="triggerExport('bestsellers')">
					{{ exporting ? 'Exporting...' : 'Export Bestsellers CSV' }}
				</button>
			</div>
		</div>

		<div class="info-box">
			<h4>📁 Nextcloud Storage Location</h4>
			<p>
				All generated reports are automatically saved to your personal Nextcloud Files folder at
				<code>/NopCommerce_Analytics/Reports/</code>. You can access, share, download, or sync them with the Nextcloud desktop app anytime.
			</p>
		</div>
	</div>
</template>

<style scoped>
.exports-container {
	padding: 24px;
	display: flex;
	flex-direction: column;
	gap: 24px;
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

.export-cards-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
	gap: 20px;
}

.export-card {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: 12px;
	padding: 24px;
	display: flex;
	flex-direction: column;
	gap: 12px;
	box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
}

.export-card .icon {
	font-size: 28px;
}

.export-card h3 {
	margin: 0;
	font-size: 16px;
	font-weight: 600;
}

.export-card p {
	margin: 0;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	line-height: 1.5;
	flex-grow: 1;
}

.primary-btn {
	padding: 10px 16px;
	background: var(--color-primary-element, #0082c9);
	color: var(--color-primary-element-text, #ffffff);
	border: none;
	border-radius: 8px;
	font-weight: 600;
	cursor: pointer;
	align-self: flex-start;
}

.primary-btn:hover {
	opacity: 0.9;
}

.info-box {
	background: var(--color-background-hover);
	border-radius: 10px;
	padding: 20px;
	border-left: 4px solid var(--color-primary-element, #0082c9);
}

.info-box h4 {
	margin: 0 0 8px 0;
	font-size: 14px;
}

.info-box p {
	margin: 0;
	font-size: 13px;
	color: var(--color-main-text);
	line-height: 1.5;
}

.info-box code {
	background: var(--color-main-background);
	padding: 2px 6px;
	border-radius: 4px;
	font-weight: 600;
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
</style>
