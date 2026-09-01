<?php

declare(strict_types=1);

namespace OCA\NopStationAnalytics\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<SyncLogEntity>
 */
class SyncLogMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'nop_sync_logs', SyncLogEntity::class);
	}

	/**
	 * @return SyncLogEntity[]
	 */
	public function findRecent(int $limit = 20): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('created_at', 'DESC')
			->setMaxResults($limit);

		return $this->findEntities($qb);
	}

	public function log(string $syncType, string $entityType, int $recordsProcessed, string $status, ?string $errorMessage = null): SyncLogEntity {
		$log = new SyncLogEntity();
		$log->setSyncType($syncType);
		$log->setEntityType($entityType);
		$log->setRecordsProcessed($recordsProcessed);
		$log->setStatus($status);
		$log->setErrorMessage($errorMessage);
		$log->setCreatedAt(date('Y-m-d H:i:s'));

		return $this->insert($log);
	}
}
