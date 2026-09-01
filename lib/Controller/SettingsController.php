<?php

declare(strict_types=1);

namespace OCA\NopStationAnalytics\Controller;

use Exception;
use OCA\NopStationAnalytics\Service\NopApiClient;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;

class SettingsController extends Controller {
	private const APP_ID = 'nopstation_analytics';

	public function __construct(
		IRequest $request,
		private NopApiClient $apiClient,
		private IConfig $config,
	) {
		parent::__construct(self::APP_ID, $request);
	}

	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/v1/settings')]
	public function getSettings(): JSONResponse {
		return new JSONResponse([
			'Data' => [
				'apiUrl' => $this->apiClient->getBaseUrl(),
				'adminEmail' => $this->apiClient->getAdminEmail(),
				'webhookSecret' => $this->apiClient->getWebhookSecret(),
				'lastSyncTimestamp' => $this->config->getAppValue(self::APP_ID, 'last_sync_timestamp', ''),
				'hasToken' => !empty($this->config->getAppValue(self::APP_ID, 'nop_jwt_token', '')),
			],
			'Message' => null,
			'ErrorList' => [],
		]);
	}

	#[FrontpageRoute(verb: 'POST', url: '/api/v1/settings')]
	public function saveSettings(
		string $apiUrl,
		string $adminEmail,
		?string $adminPassword = null,
		?string $webhookSecret = null,
	): JSONResponse {
		try {
			if (trim($apiUrl) !== '') {
				$this->apiClient->setBaseUrl(trim($apiUrl));
			}
			if (trim($adminEmail) !== '') {
				$this->apiClient->setAdminEmail(trim($adminEmail));
			}
			if ($adminPassword !== null && trim($adminPassword) !== '') {
				$this->apiClient->setAdminPassword(trim($adminPassword));
			}
			if ($webhookSecret !== null && trim($webhookSecret) !== '') {
				$this->apiClient->setWebhookSecret(trim($webhookSecret));
			}

			// Clear cached token to force fresh authentication with new credentials
			$this->config->deleteAppValue(self::APP_ID, 'nop_jwt_token');

			return new JSONResponse([
				'Data' => ['success' => true],
				'Message' => 'Settings saved successfully',
				'ErrorList' => [],
			]);
		} catch (Exception $e) {
			return new JSONResponse([
				'Data' => null,
				'Message' => 'Failed to save settings: ' . $e->getMessage(),
				'ErrorList' => [$e->getMessage()],
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[FrontpageRoute(verb: 'POST', url: '/api/v1/settings/test')]
	public function testConnection(): JSONResponse {
		try {
			$result = $this->apiClient->testConnection();
			return new JSONResponse([
				'Data' => $result,
				'Message' => 'Connected to nopCommerce successfully',
				'ErrorList' => [],
			]);
		} catch (Exception $e) {
			return new JSONResponse([
				'Data' => ['connected' => false],
				'Message' => 'Connection failed: ' . $e->getMessage(),
				'ErrorList' => [$e->getMessage()],
			], Http::STATUS_BAD_REQUEST);
		}
	}
}
