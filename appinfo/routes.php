<?php

declare(strict_types=1);

return [
	'routes' => [
		// Page
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

		// Settings API
		['name' => 'settings#get_settings', 'url' => '/api/v1/settings', 'verb' => 'GET'],
		['name' => 'settings#save_settings', 'url' => '/api/v1/settings', 'verb' => 'POST'],
		['name' => 'settings#test_connection', 'url' => '/api/v1/settings/test', 'verb' => 'POST'],

		// Analytics API
		['name' => 'analytics#get_kpis', 'url' => '/api/v1/analytics/kpi', 'verb' => 'GET'],
		['name' => 'analytics#get_trends', 'url' => '/api/v1/analytics/trends', 'verb' => 'GET'],
		['name' => 'analytics#get_bestsellers', 'url' => '/api/v1/analytics/bestsellers', 'verb' => 'GET'],
		['name' => 'analytics#get_customers', 'url' => '/api/v1/analytics/customers', 'verb' => 'GET'],
		['name' => 'analytics#get_customer_orders', 'url' => '/api/v1/analytics/customers/{customerId}/orders', 'verb' => 'GET'],
		['name' => 'analytics#get_shipments', 'url' => '/api/v1/analytics/shipments', 'verb' => 'GET'],
		['name' => 'analytics#get_summary', 'url' => '/api/v1/analytics/summary', 'verb' => 'GET'],
		['name' => 'analytics#get_low_stock', 'url' => '/api/v1/analytics/lowstock', 'verb' => 'GET'],
		['name' => 'analytics#export_summary', 'url' => '/api/v1/analytics/export', 'verb' => 'POST'],
		['name' => 'analytics#export_bestsellers', 'url' => '/api/v1/analytics/export/bestsellers', 'verb' => 'POST'],

		// Sync API
		['name' => 'sync#run_sync', 'url' => '/api/v1/sync/run', 'verb' => 'POST'],
		['name' => 'sync#get_status', 'url' => '/api/v1/sync/status', 'verb' => 'GET'],

		// Webhook Receiver (public)
		['name' => 'webhook#receive', 'url' => '/api/v1/webhook', 'verb' => 'POST'],
	],
];
