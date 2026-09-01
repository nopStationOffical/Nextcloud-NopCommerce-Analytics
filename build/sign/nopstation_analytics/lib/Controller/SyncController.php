<?php

declare(strict_types=1);

namespace OCA\NopStationAnalytics\Controller;

use Exception;
use OCA\NopStationAnalytics\Db\SyncLogMapper;
use OCA\NopStationAnalytics\Service\SyncService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class SyncController extends Controller {
	private const APP_ID = 'nopstation_analytics';

	public function __construct(
		IRequest $request,
		private SyncService $syncService,
		private SyncLogMapper $syncLogMapper
	) {
		parent::__construct(self::APP_ID, $request);
	}

	#[FrontpageRoute(verb: 'POST', url: '/api/v1/sync/run')]
	public function runSync(string $syncType = 'full'): JSONResponse {
		try {
			$type = in_array($syncType, ['full', 'incremental'], true) ? $syncType : 'full';
			$result = $this->syncService->syncAll($type);

			return new JSONResponse([
				'Data' => $result,
				'Message' => "Sync completed: {$result['orders']} orders, {$result['customers']} customers, {$result['products']} products",
				'ErrorList' => $result['errors'],
			]);
		} catch (Exception $e) {
			return new JSONResponse([
				'Data' => null,
				'Message' => 'Sync failed: ' . $e->getMessage(),
				'ErrorList' => [$e->getMessage()],
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/v1/sync/status')]
	public function getStatus(): JSONResponse {
		$lastSync = $this->syncService->getLastSyncTime();
		$logs = $this->syncLogMapper->findRecent(15);

		$formattedLogs = array_map(fn($log) => [
			'id' => $log->getId(),
			'syncType' => $log->getSyncType(),
			'entityType' => $log->getEntityType(),
			'recordsProcessed' => $log->getRecordsProcessed(),
			'status' => $log->getStatus(),
			'errorMessage' => $log->getErrorMessage(),
			'createdAt' => $log->getCreatedAt(),
		], $logs);

		return new JSONResponse([
			'Data' => [
				'lastSync' => $lastSync,
				'logs' => $formattedLogs,
			],
			'Message' => null,
			'ErrorList' => [],
		]);
	}
}
