<?php

declare(strict_types=1);

namespace OCA\NopStationAnalytics\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getSyncType()
 * @method void setSyncType(string $syncType)
 * @method string getEntityType()
 * @method void setEntityType(string $entityType)
 * @method int getRecordsProcessed()
 * @method void setRecordsProcessed(int $recordsProcessed)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string|null getErrorMessage()
 * @method void setErrorMessage(?string $errorMessage)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 */
class SyncLogEntity extends Entity {
	protected ?string $syncType = null;
	protected ?string $entityType = null;
	protected ?int $recordsProcessed = null;
	protected ?string $status = null;
	protected ?string $errorMessage = null;
	protected ?string $createdAt = null;

	public function __construct() {
		$this->addType('recordsProcessed', 'integer');
	}
}
