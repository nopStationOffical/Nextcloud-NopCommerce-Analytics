<?php

declare(strict_types=1);

namespace OCA\NopStationAnalytics\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<CustomerEntity>
 */
class CustomerMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'nop_customers', CustomerEntity::class);
	}

	public function findByNopCustomerId(int $nopCustomerId): ?CustomerEntity {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('nop_customer_id', $qb->createNamedParameter($nopCustomerId)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return null;
		}
	}

	public function findByEmail(string $email): ?CustomerEntity {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('email', $qb->createNamedParameter($email)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return null;
		}
	}

	public function upsert(CustomerEntity $entity): CustomerEntity {
		$existing = $this->findByNopCustomerId($entity->getNopCustomerId());
		if ($existing !== null) {
			$entity->setId($existing->getId());
			return $this->update($entity);
		}
		return $this->insert($entity);
	}
}
