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

	public static function getOrderStatusLabel(int $statusId): array {
		return match ($statusId) {
			10 => ['label' => 'Pending', 'class' => 'status-pending'],
			20 => ['label' => 'Processing', 'class' => 'status-processing'],
			30 => ['label' => 'Complete', 'class' => 'status-complete'],
			40 => ['label' => 'Cancelled', 'class' => 'status-cancelled'],
			default => ['label' => 'Unknown (' . $statusId . ')', 'class' => 'status-default'],
		};
	}

	public static function getPaymentStatusLabel(int $statusId): array {
		return match ($statusId) {
			10 => ['label' => 'Pending', 'class' => 'payment-pending'],
			20 => ['label' => 'Authorized', 'class' => 'payment-authorized'],
			30 => ['label' => 'Paid', 'class' => 'payment-paid'],
			35 => ['label' => 'Partially Refunded', 'class' => 'payment-refunded'],
			40 => ['label' => 'Refunded', 'class' => 'payment-refunded'],
			50 => ['label' => 'Voided', 'class' => 'payment-voided'],
			default => ['label' => 'Unknown (' . $statusId . ')', 'class' => 'payment-default'],
		};
	}

	public static function getShippingStatusLabel(int $statusId): array {
		return match ($statusId) {
			10 => ['label' => 'Shipping Not Required', 'class' => 'shipping-not-required'],
			20 => ['label' => 'Not Yet Shipped', 'class' => 'shipping-pending'],
			25 => ['label' => 'Partially Shipped', 'class' => 'shipping-partial'],
			30 => ['label' => 'Shipped', 'class' => 'shipping-shipped'],
			40 => ['label' => 'Delivered', 'class' => 'shipping-delivered'],
			default => ['label' => 'Unknown (' . $statusId . ')', 'class' => 'shipping-default'],
		};
	}

	public function getCustomerSegmentation(?string $startDate = null, ?string $endDate = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select(
			'customer_id',
			'customer_email',
			'customer_full_name',
			$qb->createFunction('COUNT(*) as order_count'),
			$qb->createFunction('COALESCE(SUM(order_total), 0) as total_spent'),
			$qb->createFunction('MAX(created_on_utc) as last_order_date'),
			$qb->createFunction('MIN(created_on_utc) as first_order_date')
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
			$segment = $orders === 1 ? 'new' : 'returning';
			if ($orders === 1) {
				$newCustomers++;
			} elseif ($orders > 1) {
				$returningCustomers++;
			}

			$topCustomers[] = [
				'customerId' => (int)$row['customer_id'],
				'email' => (string)($row['customer_email'] ?? 'Customer ' . $row['customer_id']),
				'fullName' => (string)($row['customer_full_name'] ?? 'Anonymous'),
				'orderCount' => $orders,
				'totalSpent' => round((float)$row['total_spent'], 2),
				'lastOrderDate' => (string)($row['last_order_date'] ?? ''),
				'firstOrderDate' => (string)($row['first_order_date'] ?? ''),
				'segment' => $segment,
				'segmentLabel' => $orders === 1 ? 'First-Time' : 'Returning',
			];
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

	public static function formatPaymentMethod(?string $systemName): string {
		if (empty($systemName)) {
			return 'N/A';
		}
		return match ($systemName) {
			'Payments.CheckMoneyOrder' => 'Check / Money Order',
			'Payments.Manual' => 'Credit Card',
			'Payments.PayPalStandard' => 'PayPal Standard',
			'Payments.PayPalSmartPaymentButtons' => 'PayPal',
			'Payments.CashOnDelivery' => 'Cash On Delivery',
			'Payments.PurchaseOrder' => 'Purchase Order',
			default => str_replace(['Payments.', '_'], ['', ' '], $systemName),
		};
	}

	public function getCustomerOrders(int $customerId, ?string $startDate = null, ?string $endDate = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'o.nop_order_id',
			'o.custom_order_number',
			'o.order_status_id',
			'o.payment_status_id',
			'o.shipping_status_id',
			'o.order_subtotal_incl_tax',
			'o.order_shipping',
			'o.order_discount',
			'o.order_tax',
			'o.order_total',
			'o.profit',
			'o.payment_method_system_name',
			'o.shipping_method',
			'o.created_on_utc'
		)
		->from('nop_orders', 'o')
		->where($qb->expr()->eq('o.customer_id', $qb->createNamedParameter($customerId)));

		if ($startDate !== null && $startDate !== '') {
			$qb->andWhere($qb->expr()->gte('o.created_on_utc', $qb->createNamedParameter($startDate)));
		}
		if ($endDate !== null && $endDate !== '') {
			$qb->andWhere($qb->expr()->lte('o.created_on_utc', $qb->createNamedParameter($endDate)));
		}

		$qb->orderBy('o.created_on_utc', 'DESC');

		$orders = $qb->executeQuery()->fetchAll();

		if (empty($orders)) {
			return [];
		}

		$orderIds = array_column($orders, 'nop_order_id');
		$itemsQb = $this->db->getQueryBuilder();
		$itemsQb->select(
			'order_id',
			'product_id',
			'product_name',
			'product_sku',
			'quantity',
			'unit_price',
			'total_price'
		)
		->from('nop_order_items')
		->where($itemsQb->expr()->in('order_id', $itemsQb->createNamedParameter($orderIds, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT_ARRAY)));

		$itemsRows = $itemsQb->executeQuery()->fetchAll();

		$itemsByOrder = [];
		foreach ($itemsRows as $item) {
			$oid = (int)$item['order_id'];
			$itemsByOrder[$oid][] = [
				'productId' => (int)$item['product_id'],
				'productName' => (string)$item['product_name'],
				'sku' => (string)($item['product_sku'] ?? ''),
				'quantity' => (int)$item['quantity'],
				'unitPrice' => round((float)$item['unit_price'], 2),
				'totalPrice' => round((float)$item['total_price'], 2),
			];
		}

		return array_map(function($row) use ($itemsByOrder) {
			$oid = (int)$row['nop_order_id'];
			$orderStatus = self::getOrderStatusLabel((int)$row['order_status_id']);
			$paymentStatus = self::getPaymentStatusLabel((int)$row['payment_status_id']);
			$shippingStatus = self::getShippingStatusLabel((int)$row['shipping_status_id']);
			$items = $itemsByOrder[$oid] ?? [];

			return [
				'orderId' => $oid,
				'customOrderNumber' => (string)($row['custom_order_number'] ?? '#' . $oid),
				'createdOnUtc' => (string)$row['created_on_utc'],
				'orderStatusId' => (int)$row['order_status_id'],
				'orderStatus' => $orderStatus['label'],
				'orderStatusClass' => $orderStatus['class'],
				'paymentStatusId' => (int)$row['payment_status_id'],
				'paymentStatus' => $paymentStatus['label'],
				'paymentStatusClass' => $paymentStatus['class'],
				'shippingStatusId' => (int)$row['shipping_status_id'],
				'shippingStatus' => $shippingStatus['label'],
				'shippingStatusClass' => $shippingStatus['class'],
				'paymentMethod' => self::formatPaymentMethod($row['payment_method_system_name'] ?? null),
				'shippingMethod' => (string)($row['shipping_method'] ?? 'Standard'),
				'orderDiscount' => round((float)($row['order_discount'] ?? 0), 2),
				'orderTotal' => round((float)$row['order_total'], 2),
				'shipping' => round((float)$row['order_shipping'], 2),
				'tax' => round((float)$row['order_tax'], 2),
				'profit' => round((float)$row['profit'], 2),
				'itemCount' => array_sum(array_column($items, 'quantity')),
				'items' => $items,
			];
		}, $orders);
	}

	public function getOrdersByPeriod(string $period, string $groupBy = 'day', int $storeId = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'o.nop_order_id',
			'o.custom_order_number',
			'o.customer_id',
			'o.customer_full_name',
			'o.customer_email',
			'o.order_status_id',
			'o.payment_status_id',
			'o.shipping_status_id',
			'o.order_subtotal_incl_tax',
			'o.order_shipping',
			'o.order_discount',
			'o.order_tax',
			'o.order_total',
			'o.profit',
			'o.payment_method_system_name',
			'o.shipping_method',
			'o.created_on_utc'
		)
		->from('nop_orders', 'o')
		->where($qb->expr()->neq('o.order_status_id', $qb->createNamedParameter(40)));

		$qb->andWhere($qb->expr()->like('o.created_on_utc', $qb->createNamedParameter($period . '%')));

		if ($storeId > 0) {
			$qb->andWhere($qb->expr()->eq('o.store_id', $qb->createNamedParameter($storeId)));
		}

		$qb->orderBy('o.created_on_utc', 'DESC');

		$orders = $qb->executeQuery()->fetchAll();

		if (empty($orders)) {
			return [];
		}

		$orderIds = array_column($orders, 'nop_order_id');
		$itemsQb = $this->db->getQueryBuilder();
		$itemsQb->select(
			'order_id',
			'product_id',
			'product_name',
			'product_sku',
			'quantity',
			'unit_price',
			'total_price'
		)
		->from('nop_order_items')
		->where($itemsQb->expr()->in('order_id', $itemsQb->createNamedParameter($orderIds, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT_ARRAY)));

		$itemsRows = $itemsQb->executeQuery()->fetchAll();

		$itemsByOrder = [];
		foreach ($itemsRows as $item) {
			$oid = (int)$item['order_id'];
			$itemsByOrder[$oid][] = [
				'productId' => (int)$item['product_id'],
				'productName' => (string)$item['product_name'],
				'sku' => (string)($item['product_sku'] ?? ''),
				'quantity' => (int)$item['quantity'],
				'unitPrice' => round((float)$item['unit_price'], 2),
				'totalPrice' => round((float)$item['total_price'], 2),
			];
		}

		return array_map(function($row) use ($itemsByOrder) {
			$oid = (int)$row['nop_order_id'];
			$orderStatus = self::getOrderStatusLabel((int)$row['order_status_id']);
			$paymentStatus = self::getPaymentStatusLabel((int)$row['payment_status_id']);
			$shippingStatus = self::getShippingStatusLabel((int)$row['shipping_status_id']);
			$items = $itemsByOrder[$oid] ?? [];

			return [
				'orderId' => $oid,
				'customOrderNumber' => (string)($row['custom_order_number'] ?? '#' . $oid),
				'customerId' => (int)$row['customer_id'],
				'customerName' => (string)($row['customer_full_name'] ?? 'Guest'),
				'customerEmail' => (string)($row['customer_email'] ?? ''),
				'createdOnUtc' => (string)$row['created_on_utc'],
				'orderStatusId' => (int)$row['order_status_id'],
				'orderStatus' => $orderStatus['label'],
				'orderStatusClass' => $orderStatus['class'],
				'paymentStatusId' => (int)$row['payment_status_id'],
				'paymentStatus' => $paymentStatus['label'],
				'paymentStatusClass' => $paymentStatus['class'],
				'shippingStatusId' => (int)$row['shipping_status_id'],
				'shippingStatus' => $shippingStatus['label'],
				'shippingStatusClass' => $shippingStatus['class'],
				'paymentMethod' => self::formatPaymentMethod($row['payment_method_system_name'] ?? null),
				'shippingMethod' => (string)($row['shipping_method'] ?? 'Standard'),
				'orderDiscount' => round((float)($row['order_discount'] ?? 0), 2),
				'orderTotal' => round((float)$row['order_total'], 2),
				'shipping' => round((float)$row['order_shipping'], 2),
				'tax' => round((float)$row['order_tax'], 2),
				'profit' => round((float)$row['profit'], 2),
				'itemCount' => array_sum(array_column($items, 'quantity')),
				'items' => $items,
			];
		}, $orders);
	}

	public function getShipmentOverview(?string $startDate = null, ?string $endDate = null, int $storeId = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'shipping_status_id',
			$qb->createFunction('COUNT(*) as cnt'),
			$qb->createFunction('COALESCE(SUM(order_shipping), 0) as shipping_fee')
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

		$qb->groupBy('shipping_status_id');
		$rows = $qb->executeQuery()->fetchAll();

		$notYetShipped = 0;
		$partiallyShipped = 0;
		$shipped = 0;
		$delivered = 0;
		$shippingNotRequired = 0;
		$totalShippingFees = 0.0;

		foreach ($rows as $row) {
			$statusId = (int)$row['shipping_status_id'];
			$cnt = (int)$row['cnt'];
			$fee = (float)$row['shipping_fee'];
			$totalShippingFees += $fee;

			match ($statusId) {
				10 => $shippingNotRequired += $cnt,
				20 => $notYetShipped += $cnt,
				25 => $partiallyShipped += $cnt,
				30 => $shipped += $cnt,
				40 => $delivered += $cnt,
				default => null,
			};
		}

		$shippableOrders = $notYetShipped + $partiallyShipped + $shipped + $delivered;
		$fulfilledOrders = $shipped + $delivered;
		$fulfillmentRate = $shippableOrders > 0 ? round(($fulfilledOrders / $shippableOrders) * 100, 1) : 0.0;

		$recentQb = $this->db->getQueryBuilder();
		$recentQb->select(
			'nop_order_id',
			'custom_order_number',
			'customer_id',
			'customer_full_name',
			'customer_email',
			'order_status_id',
			'shipping_status_id',
			'shipping_method',
			'order_shipping',
			'order_total',
			'created_on_utc'
		)
		->from('nop_orders')
		->where($recentQb->expr()->neq('order_status_id', $recentQb->createNamedParameter(40)));

		if ($startDate !== null && $startDate !== '') {
			$recentQb->andWhere($recentQb->expr()->gte('created_on_utc', $recentQb->createNamedParameter($startDate)));
		}
		if ($endDate !== null && $endDate !== '') {
			$recentQb->andWhere($recentQb->expr()->lte('created_on_utc', $recentQb->createNamedParameter($endDate)));
		}
		if ($storeId > 0) {
			$recentQb->andWhere($recentQb->expr()->eq('store_id', $recentQb->createNamedParameter($storeId)));
		}

		$recentQb->orderBy('created_on_utc', 'DESC')
			->setMaxResults(8);

		$recentRows = $recentQb->executeQuery()->fetchAll();
		$recentShipments = array_map(function($r) {
			$shippingStatus = self::getShippingStatusLabel((int)$r['shipping_status_id']);
			$orderStatus = self::getOrderStatusLabel((int)$r['order_status_id']);
			return [
				'orderId' => (int)$r['nop_order_id'],
				'customOrderNumber' => (string)($r['custom_order_number'] ?? '#' . $r['nop_order_id']),
				'customerId' => (int)$r['customer_id'],
				'customerName' => (string)($r['customer_full_name'] ?? 'Customer ' . $r['customer_id']),
				'customerEmail' => (string)($r['customer_email'] ?? ''),
				'orderStatus' => $orderStatus['label'],
				'orderStatusClass' => $orderStatus['class'],
				'shippingStatusId' => (int)$r['shipping_status_id'],
				'shippingStatus' => $shippingStatus['label'],
				'shippingStatusClass' => $shippingStatus['class'],
				'shippingMethod' => (string)($r['shipping_method'] ?? 'Standard Ground'),
				'orderShipping' => round((float)$r['order_shipping'], 2),
				'orderTotal' => round((float)$r['order_total'], 2),
				'createdOnUtc' => (string)$r['created_on_utc'],
			];
		}, $recentRows);

		return [
			'notYetShipped' => $notYetShipped,
			'partiallyShipped' => $partiallyShipped,
			'shipped' => $shipped,
			'delivered' => $delivered,
			'shippingNotRequired' => $shippingNotRequired,
			'totalShippableOrders' => $shippableOrders,
			'fulfilledOrders' => $fulfilledOrders,
			'fulfillmentRate' => $fulfillmentRate,
			'totalShippingFees' => round($totalShippingFees, 2),
			'recentShipments' => $recentShipments,
		];
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
