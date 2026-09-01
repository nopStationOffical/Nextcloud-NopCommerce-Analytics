<?php

declare(strict_types=1);

namespace OCA\NopStationAnalytics\Service;

use OCA\NopStationAnalytics\Db\ProductMapper;
use OCP\IDBConnection;

class AnalyticsCalculatorService {
	public function __construct(
		private IDBConnection $db,
		private ProductMapper $productMapper
	) {
	}

	public function getKpis(?string $startDate = null, ?string $endDate = null, int $storeId = 0): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select(
			$qb->createFunction('COUNT(*) as total_orders'),
			$qb->createFunction('COALESCE(SUM(order_total), 0) as total_revenue'),
			$qb->createFunction('COALESCE(AVG(order_total), 0) as avg_order_value'),
			$qb->createFunction('COALESCE(SUM(profit), 0) as total_profit'),
			$qb->createFunction('COALESCE(SUM(order_shipping), 0) as total_shipping'),
			$qb->createFunction('COALESCE(SUM(order_tax), 0) as total_tax'),
			$qb->createFunction('COUNT(DISTINCT customer_id) as unique_customers')
		)
		->from('nop_orders');

		// Only include active/valid orders (not cancelled = 40)
		$qb->where($qb->expr()->neq('order_status_id', $qb->createNamedParameter(40)));

		if ($startDate !== null && $startDate !== '') {
			$qb->andWhere($qb->expr()->gte('created_on_utc', $qb->createNamedParameter($startDate)));
		}
		if ($endDate !== null && $endDate !== '') {
			$qb->andWhere($qb->expr()->lte('created_on_utc', $qb->createNamedParameter($endDate)));
		}
		if ($storeId > 0) {
			$qb->andWhere($qb->expr()->eq('store_id', $qb->createNamedParameter($storeId)));
		}

		$result = $qb->executeQuery()->fetch();

		$totalOrders = (int)($result['total_orders'] ?? 0);
		$totalRevenue = (float)($result['total_revenue'] ?? 0.0);
		$avgOrderValue = $totalOrders > 0 ? (float)($result['avg_order_value'] ?? 0.0) : 0.0;
		$totalProfit = (float)($result['total_profit'] ?? 0.0);
		$totalShipping = (float)($result['total_shipping'] ?? 0.0);
		$totalTax = (float)($result['total_tax'] ?? 0.0);
		$uniqueCustomers = (int)($result['unique_customers'] ?? 0);

		// Count completed orders
		$completedQb = $this->db->getQueryBuilder();
		$completedQb->select($completedQb->createFunction('COUNT(*) as completed_count'))
			->from('nop_orders')
			->where($completedQb->expr()->eq('order_status_id', $completedQb->createNamedParameter(30)));
		if ($startDate !== null && $startDate !== '') {
			$completedQb->andWhere($completedQb->expr()->gte('created_on_utc', $completedQb->createNamedParameter($startDate)));
		}
		if ($endDate !== null && $endDate !== '') {
			$completedQb->andWhere($completedQb->expr()->lte('created_on_utc', $completedQb->createNamedParameter($endDate)));
		}
		if ($storeId > 0) {
			$completedQb->andWhere($completedQb->expr()->eq('store_id', $completedQb->createNamedParameter($storeId)));
		}
		$completedRes = $completedQb->executeQuery()->fetch();
		$completedOrders = (int)($completedRes['completed_count'] ?? 0);

		return [
			'totalOrders' => $totalOrders,
			'totalRevenue' => round($totalRevenue, 2),
			'avgOrderValue' => round($avgOrderValue, 2),
			'totalProfit' => round($totalProfit, 2),
			'totalShipping' => round($totalShipping, 2),
			'totalTax' => round($totalTax, 2),
			'uniqueCustomers' => $uniqueCustomers,
			'completedOrders' => $completedOrders,
			'completionRate' => $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 1) : 0.0,
		];
	}

	public function getTrends(string $interval = 'day', ?string $startDate = null, ?string $endDate = null, int $storeId = 0): array {
		$qb = $this->db->getQueryBuilder();

		// Cross-database date grouping expression
		$dateExpr = match ($interval) {
			'week' => "SUBSTRING(created_on_utc, 1, 10)", // Fallback group
			'month' => "SUBSTRING(created_on_utc, 1, 7)",
			default => "SUBSTRING(created_on_utc, 1, 10)",
		};

		$qb->select(
			$qb->createFunction("{$dateExpr} as period"),
			$qb->createFunction('COUNT(*) as orders_count'),
			$qb->createFunction('COALESCE(SUM(order_total), 0) as revenue'),
			$qb->createFunction('COALESCE(SUM(profit), 0) as profit')
		)
		->from('nop_orders')
		->where($qb->expr()->neq('order_status_id', $qb->createNamedParameter(40)));

		if ($startDate !== null && $startDate !== '') {
			$qb->andWhere($qb->expr()->gte('created_on_utc', $qb->createNamedParameter($startDate)));
		}
		if ($endDate !== null && $endDate !== '') {
			$qb->andWhere($qb->expr()->lte('created_on_utc', $qb->createNamedParameter($endDate)));
		}
		if ($storeId > 0) {
			$qb->andWhere($qb->expr()->eq('store_id', $qb->createNamedParameter($storeId)));
		}

		$qb->groupBy('period')
			->orderBy('period', 'ASC');

		$rows = $qb->executeQuery()->fetchAll();

		$labels = [];
		$revenue = [];
		$orders = [];
		$profit = [];

		foreach ($rows as $row) {
			$labels[] = (string)$row['period'];
			$revenue[] = round((float)$row['revenue'], 2);
			$orders[] = (int)$row['orders_count'];
			$profit[] = round((float)$row['profit'], 2);
		}

		return [
			'labels' => $labels,
			'revenue' => $revenue,
			'orders' => $orders,
			'profit' => $profit,
		];
	}

	public function getBestsellers(int $limit = 10, ?string $startDate = null, ?string $endDate = null, int $storeId = 0): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select(
			'i.product_id',
			'i.product_name',
			$qb->createFunction('COALESCE(SUM(i.quantity), 0) as total_quantity'),
			$qb->createFunction('COALESCE(SUM(i.total_price), 0) as total_amount')
		)
		->from('nop_order_items', 'i')
		->join('i', 'nop_orders', 'o', $qb->expr()->eq('i.order_id', 'o.nop_order_id'))
		->where($qb->expr()->neq('o.order_status_id', $qb->createNamedParameter(40)));

		if ($startDate !== null && $startDate !== '') {
			$qb->andWhere($qb->expr()->gte('o.created_on_utc', $qb->createNamedParameter($startDate)));
		}
		if ($endDate !== null && $endDate !== '') {
			$qb->andWhere($qb->expr()->lte('o.created_on_utc', $qb->createNamedParameter($endDate)));
		}
		if ($storeId > 0) {
			$qb->andWhere($qb->expr()->eq('o.store_id', $qb->createNamedParameter($storeId)));
		}

		$qb->groupBy('i.product_id')
			->addGroupBy('i.product_name')
			->orderBy('total_quantity', 'DESC')
			->setMaxResults($limit);

		$rows = $qb->executeQuery()->fetchAll();

		// If no items in order_items yet (e.g. from Order List without items), return products catalog sample
		if (empty($rows)) {
			$prodQb = $this->db->getQueryBuilder();
			$prodQb->select('nop_product_id as product_id', 'name as product_name', 'price as total_amount')
				->from('nop_products')
				->setMaxResults($limit);
			$sample = $prodQb->executeQuery()->fetchAll();
			return array_map(fn($p) => [
				'productId' => (int)$p['product_id'],
				'productName' => (string)$p['product_name'],
				'totalQuantity' => 0,
				'totalAmount' => (float)$p['total_amount'],
			], $sample);
		}

		return array_map(fn($row) => [
			'productId' => (int)$row['product_id'],
			'productName' => (string)$row['product_name'],
			'totalQuantity' => (int)$row['total_quantity'],
			'totalAmount' => round((float)$row['total_amount'], 2),
		], $rows);
	}

	public function getCustomerSegmentation(?string $startDate = null, ?string $endDate = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select(
			'customer_id',
			'customer_email',
			'customer_full_name',
			$qb->createFunction('COUNT(*) as order_count'),
			$qb->createFunction('COALESCE(SUM(order_total), 0) as total_spent')
		)
		->from('nop_orders')
		->where($qb->expr()->neq('order_status_id', $qb->createNamedParameter(40)));

		if ($startDate !== null && $startDate !== '') {
			$qb->andWhere($qb->expr()->gte('created_on_utc', $qb->createNamedParameter($startDate)));
		}
		if ($endDate !== null && $endDate !== '') {
			$qb->andWhere($qb->expr()->lte('created_on_utc', $qb->createNamedParameter($endDate)));
		}

		$qb->groupBy('customer_id')
			->addGroupBy('customer_email')
			->addGroupBy('customer_full_name')
			->orderBy('total_spent', 'DESC');

		$rows = $qb->executeQuery()->fetchAll();

		$newCustomers = 0;
		$returningCustomers = 0;
		$topCustomers = [];

		foreach ($rows as $row) {
			$orders = (int)$row['order_count'];
			if ($orders === 1) {
				$newCustomers++;
			} elseif ($orders > 1) {
				$returningCustomers++;
			}

			if (count($topCustomers) < 10) {
				$topCustomers[] = [
					'customerId' => (int)$row['customer_id'],
					'email' => (string)($row['customer_email'] ?? 'Customer ' . $row['customer_id']),
					'fullName' => (string)($row['customer_full_name'] ?? 'Anonymous'),
					'orderCount' => $orders,
					'totalSpent' => round((float)$row['total_spent'], 2),
				];
			}
		}

		return [
			'newCustomers' => $newCustomers,
			'returningCustomers' => $returningCustomers,
			'totalActiveCustomers' => count($rows),
			'topCustomers' => $topCustomers,
		];
	}

	public function getSalesSummary(?string $startDate = null, ?string $endDate = null, int $storeId = 0, string $groupBy = 'day'): array {
		$dateExpr = match ($groupBy) {
			'week' => "SUBSTRING(created_on_utc, 1, 10)",
			'month' => "SUBSTRING(created_on_utc, 1, 7)",
			default => "SUBSTRING(created_on_utc, 1, 10)",
		};

		$qb = $this->db->getQueryBuilder();
		$qb->select(
			$qb->createFunction("{$dateExpr} as summary_period"),
			$qb->createFunction('COUNT(*) as number_of_orders'),
			$qb->createFunction('COALESCE(SUM(profit), 0) as profit'),
			$qb->createFunction('COALESCE(SUM(order_shipping), 0) as shipping'),
			$qb->createFunction('COALESCE(SUM(order_tax), 0) as tax'),
			$qb->createFunction('COALESCE(SUM(order_total), 0) as order_total')
		)
		->from('nop_orders')
		->where($qb->expr()->neq('order_status_id', $qb->createNamedParameter(40)));

		if ($startDate !== null && $startDate !== '') {
			$qb->andWhere($qb->expr()->gte('created_on_utc', $qb->createNamedParameter($startDate)));
		}
		if ($endDate !== null && $endDate !== '') {
			$qb->andWhere($qb->expr()->lte('created_on_utc', $qb->createNamedParameter($endDate)));
		}
		if ($storeId > 0) {
			$qb->andWhere($qb->expr()->eq('store_id', $qb->createNamedParameter($storeId)));
		}

		$qb->groupBy('summary_period')
			->orderBy('summary_period', 'DESC');

		$rows = $qb->executeQuery()->fetchAll();

		return array_map(fn($row) => [
			'summary' => (string)$row['summary_period'],
			'numberOfOrders' => (int)$row['number_of_orders'],
			'profit' => round((float)$row['profit'], 2),
			'shipping' => round((float)$row['shipping'], 2),
			'tax' => round((float)$row['tax'], 2),
			'orderTotal' => round((float)$row['order_total'], 2),
		], $rows);
	}

	public function getLowStockAlerts(int $threshold = 10): array {
		$entities = $this->productMapper->findLowStock($threshold);
		return array_map(fn($p) => [
			'productId' => $p->getNopProductId(),
			'name' => $p->getName(),
			'sku' => $p->getSku(),
			'stockQuantity' => $p->getStockQuantity(),
			'price' => $p->getPrice(),
		], $entities);
	}
}
