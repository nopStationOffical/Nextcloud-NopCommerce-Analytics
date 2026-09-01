<?php

declare(strict_types=1);

namespace Service;

use OCA\NopStationAnalytics\Db\CustomerMapper;
use OCA\NopStationAnalytics\Db\OrderItemMapper;
use OCA\NopStationAnalytics\Db\OrderMapper;
use OCA\NopStationAnalytics\Db\ProductMapper;
use OCA\NopStationAnalytics\Db\SyncLogMapper;
use OCA\NopStationAnalytics\Service\NopApiClient;
use OCA\NopStationAnalytics\Service\WebhookService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class WebhookServiceTest extends TestCase {
	private NopApiClient $apiClient;
	private OrderMapper $orderMapper;
	private OrderItemMapper $orderItemMapper;
	private CustomerMapper $customerMapper;
	private ProductMapper $productMapper;
	private SyncLogMapper $syncLogMapper;
	private LoggerInterface $logger;
	private WebhookService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->apiClient = $this->createMock(NopApiClient::class);
		$this->orderMapper = $this->createMock(OrderMapper::class);
		$this->orderItemMapper = $this->createMock(OrderItemMapper::class);
		$this->customerMapper = $this->createMock(CustomerMapper::class);
		$this->productMapper = $this->createMock(ProductMapper::class);
		$this->syncLogMapper = $this->createMock(SyncLogMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->service = new WebhookService(
			$this->apiClient,
			$this->orderMapper,
			$this->orderItemMapper,
			$this->customerMapper,
			$this->productMapper,
			$this->syncLogMapper,
			$this->logger
		);
	}

	public function testVerifySignatureSuccess(): void {
		$secret = 'test-secret-key-12345';
		$this->apiClient->method('getWebhookSecret')->willReturn($secret);

		$body = json_encode(['event' => 'order.created', 'data' => ['Id' => 101]]);
		$timestamp = (string)time();
		$signature = base64_encode(hash_hmac('sha256', $body, $secret, true));

		$isValid = $this->service->verifySignature($signature, $timestamp, $body);
		$this->assertTrue($isValid);
	}

	public function testVerifySignatureRejectsInvalidSignature(): void {
		$secret = 'test-secret-key-12345';
		$this->apiClient->method('getWebhookSecret')->willReturn($secret);

		$body = json_encode(['event' => 'order.created', 'data' => ['Id' => 101]]);
		$timestamp = (string)time();
		$invalidSignature = base64_encode(hash_hmac('sha256', $body, 'wrong-secret', true));

		$isValid = $this->service->verifySignature($invalidSignature, $timestamp, $body);
		$this->assertFalse($isValid);
	}

	public function testVerifySignatureReplayProtection(): void {
		$secret = 'test-secret-key-12345';
		$this->apiClient->method('getWebhookSecret')->willReturn($secret);

		$body = json_encode(['event' => 'order.created', 'data' => ['Id' => 101]]);
		// 10 minutes ago (> 300 seconds)
		$expiredTimestamp = (string)(time() - 600);
		$signature = base64_encode(hash_hmac('sha256', $body, $secret, true));

		$isValid = $this->service->verifySignature($signature, $expiredTimestamp, $body);
		$this->assertFalse($isValid);
	}
}
