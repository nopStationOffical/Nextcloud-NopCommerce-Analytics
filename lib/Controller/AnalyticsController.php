<?php

declare(strict_types=1);

namespace OCA\NopStationAnalytics\Controller;

use Exception;
use OCA\NopStationAnalytics\Service\AnalyticsCalculatorService;
use OCA\NopStationAnalytics\Service\ExportService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

class AnalyticsController extends Controller {
	private const APP_ID = 'nopstation_analytics';

	public function __construct(
		IRequest $request,
		private AnalyticsCalculatorService $analyticsService,
		private ExportService $exportService,
		private IUserSession $userSession,
	) {
		parent::__construct(self::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/v1/analytics/kpi')]
	public function getKpis(?string $startDate = null, ?string $endDate = null, int $storeId = 0): JSONResponse {
		try {
			$data = $this->analyticsService->getKpis($startDate, $endDate, $storeId);
			return new JSONResponse(['Data' => $data, 'Message' => null, 'ErrorList' => []]);
		} catch (Exception $e) {
			return new JSONResponse(
				['Data' => null, 'Message' => $e->getMessage(), 'ErrorList' => [$e->getMessage()]],
				Http::STATUS_INTERNAL_SERVER_ERROR
			);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/v1/analytics/trends')]
	public function getTrends(string $interval = 'day', ?string $startDate = null, ?string $endDate = null, int $storeId = 0): JSONResponse {
		try {
			$data = $this->analyticsService->getTrends($interval, $startDate, $endDate, $storeId);
			return new JSONResponse(['Data' => $data, 'Message' => null, 'ErrorList' => []]);
		} catch (Exception $e) {
			return new JSONResponse(
				['Data' => null, 'Message' => $e->getMessage(), 'ErrorList' => [$e->getMessage()]],
				Http::STATUS_INTERNAL_SERVER_ERROR
			);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/v1/analytics/bestsellers')]
	public function getBestsellers(int $limit = 10, ?string $startDate = null, ?string $endDate = null, int $storeId = 0): JSONResponse {
		try {
			$data = $this->analyticsService->getBestsellers($limit, $startDate, $endDate, $storeId);
			return new JSONResponse(['Data' => $data, 'Message' => null, 'ErrorList' => []]);
		} catch (Exception $e) {
			return new JSONResponse(
				['Data' => null, 'Message' => $e->getMessage(), 'ErrorList' => [$e->getMessage()]],
				Http::STATUS_INTERNAL_SERVER_ERROR
			);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/v1/analytics/customers')]
	public function getCustomers(?string $startDate = null, ?string $endDate = null): JSONResponse {
		try {
			$data = $this->analyticsService->getCustomerSegmentation($startDate, $endDate);
			return new JSONResponse(['Data' => $data, 'Message' => null, 'ErrorList' => []]);
		} catch (Exception $e) {
			return new JSONResponse(
				['Data' => null, 'Message' => $e->getMessage(), 'ErrorList' => [$e->getMessage()]],
				Http::STATUS_INTERNAL_SERVER_ERROR
			);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/v1/analytics/summary')]
	public function getSummary(?string $startDate = null, ?string $endDate = null, int $storeId = 0, string $groupBy = 'day'): JSONResponse {
		try {
			$data = $this->analyticsService->getSalesSummary($startDate, $endDate, $storeId, $groupBy);
			return new JSONResponse(['Data' => $data, 'Message' => null, 'ErrorList' => []]);
		} catch (Exception $e) {
			return new JSONResponse(
				['Data' => null, 'Message' => $e->getMessage(), 'ErrorList' => [$e->getMessage()]],
				Http::STATUS_INTERNAL_SERVER_ERROR
			);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/v1/analytics/lowstock')]
	public function getLowStock(int $threshold = 10): JSONResponse {
		try {
			$data = $this->analyticsService->getLowStockAlerts($threshold);
			return new JSONResponse(['Data' => $data, 'Message' => null, 'ErrorList' => []]);
		} catch (Exception $e) {
			return new JSONResponse(
				['Data' => null, 'Message' => $e->getMessage(), 'ErrorList' => [$e->getMessage()]],
				Http::STATUS_INTERNAL_SERVER_ERROR
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/v1/analytics/export')]
	public function exportSummary(
		?string $startDate = null,
		?string $endDate = null,
		int $storeId = 0,
		string $groupBy = 'day',
		string $reportType = 'summary',
	): JSONResponse {
		try {
			$user = $this->userSession->getUser();
			$userId = $user !== null ? $user->getUID() : 'admin';

			if ($reportType === 'bestsellers' || $groupBy === 'bestsellers') {
				$result = $this->exportService->exportBestsellersCsv($userId);
				return new JSONResponse([
					'Data' => $result,
					'Message' => "Bestsellers CSV report saved to Nextcloud Files: {$result['filePath']}",
					'ErrorList' => [],
				]);
			}

			$result = $this->exportService->exportSalesSummaryCsv($userId, $startDate, $endDate, $storeId, $groupBy);
			return new JSONResponse([
				'Data' => $result,
				'Message' => "CSV report saved to Nextcloud Files: {$result['filePath']}",
				'ErrorList' => [],
			]);
		} catch (Exception $e) {
			return new JSONResponse(
				['Data' => null, 'Message' => 'Export failed: ' . $e->getMessage(), 'ErrorList' => [$e->getMessage()]],
				Http::STATUS_INTERNAL_SERVER_ERROR
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/v1/analytics/export/bestsellers')]
	public function exportBestsellers(int $limit = 50): JSONResponse {
		try {
			$user = $this->userSession->getUser();
			$userId = $user !== null ? $user->getUID() : 'admin';

			$result = $this->exportService->exportBestsellersCsv($userId, $limit);
			return new JSONResponse([
				'Data' => $result,
				'Message' => "Bestsellers CSV report saved to Nextcloud Files: {$result['filePath']}",
				'ErrorList' => [],
			]);
		} catch (Exception $e) {
			return new JSONResponse(
				['Data' => null, 'Message' => 'Export failed: ' . $e->getMessage(), 'ErrorList' => [$e->getMessage()]],
				Http::STATUS_INTERNAL_SERVER_ERROR
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/v1/analytics/customers/{customerId}/orders')]
	public function getCustomerOrders(int $customerId, ?string $startDate = null, ?string $endDate = null): JSONResponse {
		try {
			$data = $this->analyticsService->getCustomerOrders($customerId, $startDate, $endDate);
			return new JSONResponse(['Data' => $data, 'Message' => null, 'ErrorList' => []]);
		} catch (Exception $e) {
			return new JSONResponse(
				['Data' => null, 'Message' => $e->getMessage(), 'ErrorList' => [$e->getMessage()]],
				Http::STATUS_INTERNAL_SERVER_ERROR
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/v1/analytics/summary/orders')]
	public function getPeriodOrders(string $period, string $groupBy = 'day', int $storeId = 0): JSONResponse {
		try {
			$data = $this->analyticsService->getOrdersByPeriod($period, $groupBy, $storeId);
			return new JSONResponse(['Data' => $data, 'Message' => null, 'ErrorList' => []]);
		} catch (Exception $e) {
			return new JSONResponse(
				['Data' => null, 'Message' => $e->getMessage(), 'ErrorList' => [$e->getMessage()]],
				Http::STATUS_INTERNAL_SERVER_ERROR
			);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/v1/analytics/shipments')]
	public function getShipments(?string $startDate = null, ?string $endDate = null, int $storeId = 0): JSONResponse {
		try {
			$data = $this->analyticsService->getShipmentOverview($startDate, $endDate, $storeId);
			return new JSONResponse(['Data' => $data, 'Message' => null, 'ErrorList' => []]);
		} catch (Exception $e) {
			return new JSONResponse(
				['Data' => null, 'Message' => $e->getMessage(), 'ErrorList' => [$e->getMessage()]],
				Http::STATUS_INTERNAL_SERVER_ERROR
			);
		}
	}
}
