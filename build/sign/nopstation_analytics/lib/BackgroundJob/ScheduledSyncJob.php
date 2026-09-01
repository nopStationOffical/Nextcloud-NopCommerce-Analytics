<?php

declare(strict_types=1);

namespace OCA\NopStationAnalytics\BackgroundJob;

use Exception;
use OCA\NopStationAnalytics\Service\SyncService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class ScheduledSyncJob extends TimedJob {
	private const APP_ID = 'nopstation_analytics';

	public function __construct(
		ITimeFactory $time,
		private SyncService $syncService,
		private LoggerInterface $logger
	) {
		parent::__construct($time);
		$this->setInterval(900); // 15 minutes
	}

	protected function run(mixed $argument): void {
		try {
			$this->logger->info('Running scheduled nopCommerce incremental sync...', ['app' => self::APP_ID]);
			$this->syncService->syncAll('incremental');
		} catch (Exception $e) {
			$this->logger->error('Scheduled sync failed: ' . $e->getMessage(), ['app' => self::APP_ID]);
		}
	}
}
