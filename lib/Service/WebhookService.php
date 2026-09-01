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
use Psr\Log\LoggerInterface;

class WebhookService {
	private const APP_ID = 'nopstation_analytics';
	private const MAX_TIMESTAMP_AGE = 300; // 5 minutes replay protection

	public function __construct(
		private NopApiClient $apiClient,
		private OrderMapper $orderMapper,
		private OrderItemMapper $orderItemMapper,
		private CustomerMapper $customerMapper,
		private ProductMapper $productMapper,
		private SyncLogMapper $syncLogMapper,
		private LoggerInterface $logger,
	) {
	}

	public function verifySignature(?string $signature, ?string $timestamp, string $rawBody): bool {
		if ($signature === null || $timestamp === null || $signature === '' || $timestamp === '') {
			return false;
		}

		// 1. Replay protection
		$ts = (int)$timestamp;
		if (abs(time() - $ts) > self::MAX_TIMESTAMP_AGE) {
			$this->logger->warning('Webhook rejected: Timestamp expired (replay protection)', ['app' => self::APP_ID]);
			return false;
		}

		// 2. Compute HMAC SHA256
		$secret = $this->apiClient->getWebhookSecret();
		$computed = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

		return hash_equals($computed, $signature);
	}

	public function processEvent(string $eventType, array $payload): array {
		$data = $payload['data'] ?? $payload['Data'] ?? $payload;
		$status = 'success';
		$error = null;
		$affectedId = null;

		try {
			match ($eventType) {
				'order.created', 'order.updated', 'order.paid', 'order.cancelled' => $this->handleOrderEvent($data, $affectedId),
				'customer.created', 'customer.updated' => $this->handleCustomerEvent($data, $affectedId),
				'product.updated', 'product.created' => $this->handleProductEvent($data, $affectedId),
				default => throw new Exception("Unsupported webhook event: {$eventType}"),
			};

			$this->syncLogMapper->log('webhook', $eventType, 1, 'success');
		} catch (Exception $e) {
			$status = 'error';
			$error = $e->getMessage();
			$this->logger->error("Webhook event processing failed [{$eventType}]: " . $e->getMessage(), ['app' => self::APP_ID]);
			$this->syncLogMapper->log('webhook', $eventType, 0, 'error', $e->getMessage());
		}

		return [
			'status' => $status,
			'event' => $eventType,
			'entityId' => $affectedId,
			'error' => $error,
			'processedAt' => date('Y-m-d H:i:s'),
		];
	}

	private function handleOrderEvent(array $data, ?int &$affectedId): void {
		$nopId = (int)($data['Id'] ?? 0);
		if ($nopId <= 0) {
			throw new Exception('Order webhook payload missing valid Id');
		}
		$affectedId = $nopId;

		$order = new OrderEntity();
		$order->setNopOrderId($nopId);
		$order->setOrderGuid($data['OrderGuid'] ?? null);
		$order->setCustomOrderNumber((string)($data['CustomOrderNumber'] ?? $nopId));
		$order->setStoreId((int)($data['StoreId'] ?? 0));
		$order->setStoreName($data['StoreName'] ?? null);
		$order->setCustomerId((int)($data['CustomerId'] ?? 0));
		$order->setCustomerEmail($data['CustomerEmail'] ?? null);
		$order->setCustomerFullName($data['CustomerFullName'] ?? null);
		$order->setOrderStatusId((int)($data['OrderStatusId'] ?? 10));
		$order->setPaymentStatusId((int)($data['PaymentStatusId'] ?? 10));
		$order->setShippingStatusId((int)($data['ShippingStatusId'] ?? 10));

		$orderTotal = $this->apiClient->parseMoney($data['OrderTotal'] ?? 0);
		$profit = $this->apiClient->parseMoney($data['Profit'] ?? 0);
		$shipping = $this->apiClient->parseMoney($data['OrderShippingInclTax'] ?? $data['OrderShipping'] ?? 0);
		$tax = $this->apiClient->parseMoney($data['TaxTotal'] ?? $data['Tax'] ?? 0);
		$subtotal = $this->apiClient->parseMoney($data['OrderSubtotalInclTax'] ?? $orderTotal);

		$order->setOrderTotal($orderTotal);
		$order->setProfit($profit);
		$order->setOrderShipping($shipping);
		$order->setOrderTax($tax);
		$order->setOrderSubtotalInclTax($subtotal);
		$order->setOrderSubtotalExclTax($this->apiClient->parseMoney($data['OrderSubtotalExclTax'] ?? $subtotal));
		$order->setPaymentMethodSystemName($data['PaymentMethodSystemName'] ?? null);
		$order->setShippingMethod($data['ShippingMethod'] ?? null);

		$createdOn = $data['CreatedOnUtc'] ?? date('Y-m-d H:i:s');
		$timestamp = strtotime((string)$createdOn);
		$order->setCreatedOnUtc($timestamp ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d H:i:s'));

		$this->orderMapper->upsert($order);

		// Process items if provided
		if (!empty($data['Items']) && is_array($data['Items'])) {
			foreach ($data['Items'] as $itemData) {
				$itemId = (int)($itemData['Id'] ?? 0);
				if ($itemId <= 0) {
					continue;
				}
				$item = new OrderItemEntity();
				$item->setNopItemId($itemId);
				$item->setOrderId($nopId);
				$item->setProductId((int)($itemData['ProductId'] ?? 0));
				$item->setProductName((string)($itemData['ProductName'] ?? 'Item'));
				$item->setProductSku($itemData['Sku'] ?? null);
				$item->setQuantity((int)($itemData['Quantity'] ?? 1));
				$item->setUnitPrice($this->apiClient->parseMoney($itemData['UnitPriceInclTax'] ?? 0));
				$item->setTotalPrice($this->apiClient->parseMoney($itemData['PriceInclTax'] ?? 0));
				$this->orderItemMapper->upsert($item);
			}
		}
	}

	private function handleCustomerEvent(array $data, ?int &$affectedId): void {
		$nopId = (int)($data['Id'] ?? 0);
		if ($nopId <= 0) {
			throw new Exception('Customer webhook payload missing valid Id');
		}
		$affectedId = $nopId;

		$customer = new CustomerEntity();
		$customer->setNopCustomerId($nopId);
		$customer->setCustomerGuid($data['CustomerGuid'] ?? null);
		$customer->setEmail($data['Email'] ?? null);
		$customer->setUsername($data['Username'] ?? null);
		$customer->setFullName($data['FullName'] ?? null);
		$customer->setActive((bool)($data['Active'] ?? true));
		$customer->setCreatedOnUtc($data['CreatedOnUtc'] ?? date('Y-m-d H:i:s'));

		$this->customerMapper->upsert($customer);
	}

	private function handleProductEvent(array $data, ?int &$affectedId): void {
		$nopId = (int)($data['Id'] ?? 0);
		if ($nopId <= 0) {
			throw new Exception('Product webhook payload missing valid Id');
		}
		$affectedId = $nopId;

		$product = new ProductEntity();
		$product->setNopProductId($nopId);
		$product->setName((string)($data['Name'] ?? 'Product ' . $nopId));
		$product->setSku($data['Sku'] ?? null);
		$product->setPrice($this->apiClient->parseMoney($data['Price'] ?? 0));
		$product->setCost($this->apiClient->parseMoney($data['ProductCost'] ?? 0));
		$product->setStockQuantity((int)($data['StockQuantity'] ?? 0));
		$product->setPublished((bool)($data['Published'] ?? true));

		$this->productMapper->upsert($product);
	}
}
