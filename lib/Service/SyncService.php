<?php

declare(strict_types=1);

namespace OCA\NopStationAnalytics\Service;

use Exception;
use OCA\NopStationAnalytics\Db\CustomerEntity;
use OCA\NopStationAnalytics\Db\CustomerMapper;
use OCA\NopStationAnalytics\Db\OrderEntity;
use OCA\NopStationAnalytics\Db\OrderItemEntity;
use OCA\NopStationAnalytics\Db\OrderItemMapper;
use OCA\NopStationAnalytics\Db\OrderMapper;
use OCA\NopStationAnalytics\Db\ProductEntity;
use OCA\NopStationAnalytics\Db\ProductMapper;
use OCA\NopStationAnalytics\Db\SyncLogMapper;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class SyncService {
	private const APP_ID = 'nopstation_analytics';

	public function __construct(
		private NopApiClient $apiClient,
		private OrderMapper $orderMapper,
		private OrderItemMapper $orderItemMapper,
		private CustomerMapper $customerMapper,
		private ProductMapper $productMapper,
		private SyncLogMapper $syncLogMapper,
		private IConfig $config,
		private LoggerInterface $logger
	) {
	}

	public function getLastSyncTime(): ?string {
		$time = $this->config->getAppValue(self::APP_ID, 'last_sync_timestamp', '');
		return $time !== '' ? $time : null;
	}

	public function setLastSyncTime(string $timestamp): void {
		$this->config->setAppValue(self::APP_ID, 'last_sync_timestamp', $timestamp);
	}

	public function syncAll(string $syncType = 'full'): array {
		$startTime = date('Y-m-d H:i:s');
		$results = [
			'products' => 0,
			'customers' => 0,
			'orders' => 0,
			'errors' => [],
		];

		// 1. Sync Products
		try {
			$results['products'] = $this->syncProducts();
			$this->syncLogMapper->log($syncType, 'products', $results['products'], 'success');
		} catch (Exception $e) {
			$this->logger->error('Failed to sync products: ' . $e->getMessage(), ['app' => self::APP_ID]);
			$results['errors'][] = 'Products sync: ' . $e->getMessage();
			$this->syncLogMapper->log($syncType, 'products', 0, 'error', $e->getMessage());
		}

		// 2. Sync Customers
		try {
			$results['customers'] = $this->syncCustomers();
			$this->syncLogMapper->log($syncType, 'customers', $results['customers'], 'success');
		} catch (Exception $e) {
			$this->logger->error('Failed to sync customers: ' . $e->getMessage(), ['app' => self::APP_ID]);
			$results['errors'][] = 'Customers sync: ' . $e->getMessage();
			$this->syncLogMapper->log($syncType, 'customers', 0, 'error', $e->getMessage());
		}

		// 3. Sync Orders & Order Items
		try {
			$startDate = ($syncType === 'incremental') ? $this->getLastSyncTime() : null;
			$results['orders'] = $this->syncOrders($startDate);
			$this->syncLogMapper->log($syncType, 'orders', $results['orders'], 'success');
		} catch (Exception $e) {
			$this->logger->error('Failed to sync orders: ' . $e->getMessage(), ['app' => self::APP_ID]);
			$results['errors'][] = 'Orders sync: ' . $e->getMessage();
			$this->syncLogMapper->log($syncType, 'orders', 0, 'error', $e->getMessage());
		}

		$this->setLastSyncTime($startTime);
		return $results;
	}

	public function syncProducts(): int {
		$page = 1;
		$pageSize = 100;
		$totalProcessed = 0;

		do {
			$products = $this->apiClient->fetchProducts($page, $pageSize);
			if (empty($products)) {
				break;
			}

			foreach ($products as $pData) {
				$nopId = (int)($pData['Id'] ?? 0);
				if ($nopId <= 0) {
					continue;
				}

				$product = new ProductEntity();
				$product->setNopProductId($nopId);
				$product->setName((string)($pData['Name'] ?? 'Product ' . $nopId));
				$product->setSku($pData['Sku'] ?? null);
				$product->setPrice($this->apiClient->parseMoney($pData['Price'] ?? 0));
				$product->setCost($this->apiClient->parseMoney($pData['ProductCost'] ?? 0));
				$product->setStockQuantity((int)($pData['StockQuantity'] ?? 0));
				$product->setPublished((bool)($pData['Published'] ?? true));

				$this->productMapper->upsert($product);
				$totalProcessed++;
			}

			$page++;
		} while (count($products) === $pageSize);

		return $totalProcessed;
	}

	public function syncCustomers(): int {
		$page = 1;
		$pageSize = 100;
		$totalProcessed = 0;

		do {
			$customers = $this->apiClient->fetchCustomers($page, $pageSize);
			if (empty($customers)) {
				break;
			}

			foreach ($customers as $cData) {
				$nopId = (int)($cData['Id'] ?? 0);
				if ($nopId <= 0) {
					continue;
				}

				$customer = new CustomerEntity();
				$customer->setNopCustomerId($nopId);
				$customer->setCustomerGuid($cData['CustomerGuid'] ?? null);
				$customer->setEmail($cData['Email'] ?? null);
				$customer->setUsername($cData['Username'] ?? null);
				$customer->setFullName($cData['FullName'] ?? null);
				$customer->setActive((bool)($cData['Active'] ?? true));
				
				$rawCreated = $cData['CreatedOn'] ?? null;
				if ($rawCreated !== null && trim((string)$rawCreated) !== '') {
					$ts = strtotime((string)$rawCreated);
					$customer->setCreatedOnUtc($ts ? gmdate('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s'));
				} else {
					$customer->setCreatedOnUtc(date('Y-m-d H:i:s'));
				}

				$this->customerMapper->upsert($customer);
				$totalProcessed++;
			}

			$page++;
		} while (count($customers) === $pageSize);

		return $totalProcessed;
	}

	public function syncOrders(?string $startDate = null): int {
		$page = 1;
		$pageSize = 100;
		$totalProcessed = 0;

		do {
			$orders = $this->apiClient->fetchOrders($startDate, null, $page, $pageSize);
			if (empty($orders)) {
				break;
			}

			foreach ($orders as $oData) {
				$nopId = (int)($oData['Id'] ?? 0);
				if ($nopId <= 0) {
					continue;
				}

				$order = new OrderEntity();
				$order->setNopOrderId($nopId);
				$order->setOrderGuid($oData['OrderGuid'] ?? null);
				$order->setCustomOrderNumber((string)($oData['CustomOrderNumber'] ?? $nopId));
				$order->setStoreId((int)($oData['StoreId'] ?? 0));
				$order->setStoreName($oData['StoreName'] ?? null);
				$order->setCustomerId((int)($oData['CustomerId'] ?? 0));
				$order->setCustomerEmail($oData['CustomerEmail'] ?? null);
				$order->setCustomerFullName($oData['CustomerFullName'] ?? null);
				$order->setOrderStatusId((int)($oData['OrderStatusId'] ?? 10));
				$order->setPaymentStatusId((int)($oData['PaymentStatusId'] ?? 10));
				$order->setShippingStatusId((int)($oData['ShippingStatusId'] ?? 10));

				$rawTotal = (!empty($oData['OrderTotalValue']) && (float)$oData['OrderTotalValue'] > 0)
					? $oData['OrderTotalValue']
					: ($oData['OrderTotal'] ?? 0);
				$orderTotal = $this->apiClient->parseMoney($rawTotal);

				$rawProfit = (!empty($oData['ProfitValue']) && (float)$oData['ProfitValue'] > 0)
					? $oData['ProfitValue']
					: ($oData['Profit'] ?? 0);
				$profit = $this->apiClient->parseMoney($rawProfit);

				$rawShipping = (!empty($oData['OrderShippingInclTaxValue']) && (float)$oData['OrderShippingInclTaxValue'] > 0)
					? $oData['OrderShippingInclTaxValue']
					: ($oData['OrderShippingInclTax'] ?? $oData['OrderShipping'] ?? 0);
				$shipping = $this->apiClient->parseMoney($rawShipping);

				$rawTax = (!empty($oData['TaxValue']) && (float)$oData['TaxValue'] > 0)
					? $oData['TaxValue']
					: ($oData['Tax'] ?? 0);
				$tax = $this->apiClient->parseMoney($rawTax);

				$rawSubtotal = (!empty($oData['OrderSubtotalInclTaxValue']) && (float)$oData['OrderSubtotalInclTaxValue'] > 0)
					? $oData['OrderSubtotalInclTaxValue']
					: ($oData['OrderSubtotalInclTax'] ?? $orderTotal);
				$subtotal = $this->apiClient->parseMoney($rawSubtotal);

				$order->setOrderTotal($orderTotal);
				$order->setProfit($profit);
				$order->setOrderShipping($shipping);
				$order->setOrderTax($tax);
				$order->setOrderSubtotalInclTax($subtotal);
				$order->setOrderSubtotalExclTax($this->apiClient->parseMoney($oData['OrderSubtotalExclTax'] ?? $subtotal));
				$order->setPaymentMethodSystemName($oData['PaymentMethodSystemName'] ?? null);
				$order->setShippingMethod($oData['ShippingMethod'] ?? null);

				$createdOn = $oData['CreatedOnUtc'] ?? $oData['CreatedOn'] ?? date('Y-m-d H:i:s');
				$timestamp = strtotime((string)$createdOn);
				$order->setCreatedOnUtc($timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : date('Y-m-d H:i:s'));

				$savedOrder = $this->orderMapper->upsert($order);
				$totalProcessed++;

				// Fetch and sync line items for this order
				try {
					$details = $this->apiClient->fetchOrderDetails($nopId);
					$items = $details['Items'] ?? [];
					foreach ($items as $it) {
						$nopItemId = (int)($it['Id'] ?? 0);
						if ($nopItemId <= 0) {
							continue;
						}
						$itemEntity = new OrderItemEntity();
						$itemEntity->setNopItemId($nopItemId);
						$itemEntity->setOrderId($nopId);
						$itemEntity->setProductId((int)($it['ProductId'] ?? 0));
						$itemEntity->setProductName((string)($it['ProductName'] ?? ''));
						$itemEntity->setProductSku($it['Sku'] ?? null);
						$itemEntity->setQuantity((int)($it['Quantity'] ?? 1));
						$unitPrice = $this->apiClient->parseMoney($it['UnitPriceInclTaxValue'] ?? $it['UnitPriceInclTax'] ?? 0);
						$totalPrice = $this->apiClient->parseMoney($it['SubTotalInclTaxValue'] ?? $it['SubTotalInclTax'] ?? ($unitPrice * (int)($it['Quantity'] ?? 1)));
						$itemEntity->setUnitPrice($unitPrice);
						$itemEntity->setTotalPrice($totalPrice);
						$this->orderItemMapper->upsert($itemEntity);
					}
				} catch (Exception $itemEx) {
					$this->logger->warning("Could not sync line items for order {$nopId}: " . $itemEx->getMessage(), ['app' => self::APP_ID]);
				}
			}

			$page++;
		} while (count($orders) === $pageSize);

		return $totalProcessed;
	}
}
