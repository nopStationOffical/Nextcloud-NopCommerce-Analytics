<?php

declare(strict_types=1);

namespace OCA\NopStationAnalytics\Service;

use Exception;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class NopApiClient {
	private const APP_ID = 'nopstation_analytics';
	private const TOKEN_LIFETIME_SECONDS = 2419200; // 28 days (safe margin before 30-day expiry)

	/**
	 * Static NST token required by nopStation Admin API endpoints.
	 * Fixed HS512 JWT with payload {"NST_KEY":"nopStationToken"}.
	 */
	private const ADMIN_NST = 'eyJhbGciOiJIUzUxMiJ9.eyJOU1RfS0VZIjoiYm05d1UzUmhkR2x2YmxSdmEyVnUifQ.adqiIzFjqZdpJw5uHOHjE5qw2UvCDH2FwMmwlYvr5ljKyPG65ZQe_4wb8NYEQFXmyZZyVu-77xd5Njn310cjMw';

	/**
	 * Standard Device ID for nopStation API requests.
	 */
	private const DEVICE_ID = '44b4d8cd-7a2d-4a5f-a0e2-798021f1e294';

	public function __construct(
		private IConfig $config,
		private IClientService $clientService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Returns base URL without any /api or /api/a suffixes.
	 * E.g. "https://5b66-118-67-219-51.ngrok-free.app"
	 */
	public function getBaseUrl(): string {
		$url = $this->config->getAppValue(self::APP_ID, 'nop_api_url', 'https://30e7-118-67-219-51.ngrok-free.app');
		$url = rtrim(trim($url), '/');
		if (str_ends_with($url, '/api/a')) {
			$url = substr($url, 0, -6);
		} elseif (str_ends_with($url, '/api')) {
			$url = substr($url, 0, -4);
		}
		return rtrim($url, '/');
	}

	public function setBaseUrl(string $url): void {
		$url = rtrim(trim($url), '/');
		if (str_ends_with($url, '/api/a')) {
			$url = substr($url, 0, -6);
		} elseif (str_ends_with($url, '/api')) {
			$url = substr($url, 0, -4);
		}
		$this->config->setAppValue(self::APP_ID, 'nop_api_url', rtrim($url, '/'));
	}

	public function getAdminEmail(): string {
		return $this->config->getAppValue(self::APP_ID, 'nop_admin_email', 'admin@yourstore.com');
	}

	public function setAdminEmail(string $email): void {
		$this->config->setAppValue(self::APP_ID, 'nop_admin_email', trim($email));
	}

	public function setAdminPassword(string $password): void {
		$this->config->setAppValue(self::APP_ID, 'nop_admin_password', $password);
	}

	protected function getAdminPassword(): string {
		return $this->config->getAppValue(self::APP_ID, 'nop_admin_password', 'admin');
	}

	public function getWebhookSecret(): string {
		$secret = $this->config->getAppValue(self::APP_ID, 'webhook_secret', '');
		if ($secret === '') {
			$secret = bin2hex(random_bytes(24));
			$this->config->setAppValue(self::APP_ID, 'webhook_secret', $secret);
		}
		return $secret;
	}

	public function setWebhookSecret(string $secret): void {
		$this->config->setAppValue(self::APP_ID, 'webhook_secret', $secret);
	}

	/**
	 * Login uses POST /api/admincustomer/login (NO /a/ prefix).
	 * Header requires Admin-NST.
	 * The JWT token is returned in the response body as Data.Token.
	 */
	public function login(): string {
		$baseUrl = $this->getBaseUrl();
		$email = $this->getAdminEmail();
		$password = $this->getAdminPassword();

		$client = $this->clientService->newClient();
		$endpoint = $baseUrl . '/api/admincustomer/login';

		$payload = [
			'Data' => [
				'Email' => $email,
				'Password' => $password,
				'CheckoutAsGuest' => false,
				'UsernamesEnabled' => false,
				'RegistrationType' => 1,
				'Username' => '',
				'RememberMe' => false,
				'DisplayCaptcha' => false,
			],
		];

		try {
			$response = $client->post($endpoint, [
				'version' => 1.1,
				'headers' => [
					'Content-Type' => 'application/json',
					'Accept' => 'application/json',
					'User-Agent' => 'com.bs.ecommerce/1.0',
					'Admin-NST' => self::ADMIN_NST,
					'DeviceId' => self::DEVICE_ID,
					'ngrok-skip-browser-warning' => '69420',
				],
				'body' => json_encode($payload),
				'timeout' => 15,
			]);

			$body = json_decode($response->getBody(), true);
			$token = $body['Data']['Token'] ?? null;

			if (!$token) {
				throw new Exception('Login succeeded but no token was returned in response: ' . substr($response->getBody(), 0, 300));
			}

			$this->config->setAppValue(self::APP_ID, 'nop_jwt_token', $token);
			$this->config->setAppValue(self::APP_ID, 'nop_token_issued_at', (string)time());

			return $token;
		} catch (Exception $e) {
			$this->logger->error('nopCommerce login failed: ' . $e->getMessage(), ['app' => self::APP_ID]);
			throw $e;
		}
	}

	public function getToken(bool $forceRefresh = false): string {
		if ($forceRefresh) {
			return $this->login();
		}

		$token = $this->config->getAppValue(self::APP_ID, 'nop_jwt_token', '');
		$issuedAt = (int)$this->config->getAppValue(self::APP_ID, 'nop_token_issued_at', '0');

		if ($token === '' || (time() - $issuedAt) > self::TOKEN_LIFETIME_SECONDS) {
			return $this->login();
		}

		return $token;
	}

	/**
	 * Make an authenticated API request to nopCommerce Admin API.
	 * All authenticated endpoints reside under /api/a/ prefix.
	 * Required headers:
	 *  - Admin-Token: JWT returned from login
	 *  - Admin-NST: static NST signature token
	 *  - DeviceId: device identifier
	 *  - User-Agent: com.bs.ecommerce/1.0
	 */
	public function makeRequest(string $method, string $path, array $data = []): array {
		$baseUrl = $this->getBaseUrl();
		$token = $this->getToken();
		$client = $this->clientService->newClient();

		// Normalize path to always start with /api/a/
		$cleanPath = '/' . ltrim($path, '/');
		if (!str_starts_with($cleanPath, '/api/a/')) {
			if (str_starts_with($cleanPath, '/api/')) {
				$cleanPath = '/api/a/' . substr($cleanPath, 5);
			} elseif (str_starts_with($cleanPath, '/a/')) {
				$cleanPath = '/api/a/' . substr($cleanPath, 3);
			} else {
				$cleanPath = '/api/a' . $cleanPath;
			}
		}

		$url = $baseUrl . $cleanPath;

		$options = [
			'version' => 1.1,
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
				'User-Agent' => 'com.bs.ecommerce/1.0',
				'Admin-Token' => $token,
				'Admin-NST' => self::ADMIN_NST,
				'DeviceId' => self::DEVICE_ID,
				'ngrok-skip-browser-warning' => '69420',
			],
			'timeout' => 30,
		];

		if (strtoupper($method) === 'POST') {
			$options['body'] = json_encode($data);
		}

		try {
			$response = match (strtoupper($method)) {
				'POST' => $client->post($url, $options),
				default => $client->get($url, $options),
			};

			$body = json_decode($response->getBody(), true);
			return is_array($body) ? $body : [];
		} catch (Exception $e) {
			// If 401 Unauthorized or Token expired, re-login once and retry
			if (str_contains($e->getMessage(), '401') || str_contains($e->getMessage(), 'Unauthorized')) {
				$token = $this->login();
				$options['headers']['Admin-Token'] = $token;
				$response = match (strtoupper($method)) {
					'POST' => $client->post($url, $options),
					default => $client->get($url, $options),
				};
				$body = json_decode($response->getBody(), true);
				return is_array($body) ? $body : [];
			}

			$this->logger->error("nopCommerce API request failed [{$method} {$url}]: " . $e->getMessage(), ['app' => self::APP_ID]);
			throw $e;
		}
	}

	/**
	 * Parse string money values like "$1,200.00", "70,379.30৳", or numeric floats.
	 */
	public function parseMoney(mixed $value): float {
		if (is_int($value) || is_float($value)) {
			return (float)$value;
		}
		if (!is_string($value) || trim($value) === '') {
			return 0.0;
		}

		$str = trim($value);
		$isNegative = str_contains($str, '(') || str_starts_with($str, '-');
		$cleaned = preg_replace('/[^0-9.]/', '', $str);

		if ($cleaned === '' || !is_numeric($cleaned)) {
			return 0.0;
		}

		$num = (float)$cleaned;
		return $isNegative ? -$num : $num;
	}

	/**
	 * Fetch orders using POST /api/a/order/List.
	 */
	public function fetchOrders(?string $startDate = null, ?string $endDate = null, int $page = 1, int $pageSize = 100): array {
		$payload = [
			'Data' => [
				'StartDate' => $startDate,
				'EndDate' => $endDate,
				'OrderStatusIds' => null,
				'PaymentStatusIds' => null,
				'ShippingStatusIds' => null,
				'StoreId' => 0,
				'Page' => $page,
				'PageSize' => $pageSize,
				'Start' => ($page - 1) * $pageSize,
				'Length' => $pageSize,
			],
		];

		$response = $this->makeRequest('POST', '/order/List', $payload);
		return $response['Data']['Data'] ?? [];
	}

	/**
	 * Fetch order details/items using GET /api/a/order/Edit/{orderId}.
	 */
	public function fetchOrderDetails(int $orderId): array {
		$response = $this->makeRequest('GET', "/order/Edit/{$orderId}");
		return $response['Data'] ?? [];
	}

	/**
	 * Fetch customer list using POST /api/a/customer/CustomerList.
	 * Does not restrict roles to fetch all registered and guest customers.
	 */
	public function fetchCustomers(int $page = 1, int $pageSize = 100): array {
		$payload = [
			'Data' => [
				'SearchEmail' => '',
				'SearchFirstName' => '',
				'SearchLastName' => '',
				'SelectedCustomerRoleIds' => [],
				'Page' => $page,
				'PageSize' => $pageSize,
				'Start' => ($page - 1) * $pageSize,
				'Length' => $pageSize,
			],
		];

		$response = $this->makeRequest('POST', '/customer/CustomerList', $payload);
		return $response['Data']['Data'] ?? [];
	}

	/**
	 * Fetch product list using POST /api/a/product/ProductList.
	 */
	public function fetchProducts(int $page = 1, int $pageSize = 100): array {
		$payload = [
			'Data' => [
				'SearchProductName' => null,
				'SearchCategoryId' => 0,
				'SearchIncludeSubCategories' => false,
				'SearchManufacturerId' => 0,
				'SearchStoreId' => 0,
				'SearchVendorId' => 0,
				'SearchProductTypeId' => 0,
				'Page' => $page,
				'PageSize' => $pageSize,
				'Start' => ($page - 1) * $pageSize,
				'Length' => $pageSize,
			],
		];

		$response = $this->makeRequest('POST', '/product/ProductList', $payload);
		return $response['Data']['Data'] ?? [];
	}

	/**
	 * Fetch store list using POST /api/a/store/StoreList.
	 */
	public function fetchStores(): array {
		$response = $this->makeRequest('POST', '/store/StoreList', ['Data' => []]);
		return $response['Data']['Data'] ?? [];
	}

	/**
	 * Fetch order aggregator totals (profit, shipping, tax, total).
	 * POST /api/a/order/ReportAggregates
	 */
	public function fetchReportAggregates(): array {
		$response = $this->makeRequest('POST', '/order/ReportAggregates', ['Data' => []]);
		return $response['Data'] ?? [];
	}

	/**
	 * Fetch bestseller brief report by quantity or amount.
	 * POST /api/a/order/BestsellersBriefReportByQuantityList or ByAmountList
	 */
	public function fetchBestsellers(string $orderBy = 'OrderByQuantity', int $start = 0, int $length = 10): array {
		$payload = [
			'Data' => [
				'Start' => $start,
				'Length' => $length,
			],
		];

		$endpoint = ($orderBy === 'OrderByTotalAmount' || $orderBy === 'OrderByAmount')
			? '/order/BestsellersBriefReportByAmountList'
			: '/order/BestsellersBriefReportByQuantityList';

		$response = $this->makeRequest('POST', $endpoint, $payload);
		return $response['Data']['Data'] ?? [];
	}

	/**
	 * Fetch order average report.
	 * POST /api/a/order/OrderAverageReportList
	 */
	public function fetchOrderAverageReport(): array {
		$response = $this->makeRequest('POST', '/order/OrderAverageReportList', ['Data' => []]);
		return $response['Data']['Data'] ?? [];
	}

	/**
	 * Fetch order incomplete report.
	 * POST /api/a/order/OrderIncompleteReportList
	 */
	public function fetchOrderIncompleteReport(): array {
		$response = $this->makeRequest('POST', '/order/OrderIncompleteReportList', ['Data' => []]);
		return $response['Data']['Data'] ?? [];
	}

	public function testConnection(): array {
		$token = $this->login();
		return [
			'connected' => !empty($token),
			'baseUrl' => $this->getBaseUrl(),
			'adminEmail' => $this->getAdminEmail(),
			'tokenPreview' => substr($token, 0, 15) . '...',
		];
	}
}
