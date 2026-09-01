<?php

declare(strict_types=1);

namespace OCA\NopStationAnalytics\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<OrderEntity>
 */
class OrderMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'nop_orders', OrderEntity::class);
	}

	public function findByNopOrderId(int $nopOrderId): ?OrderEntity {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('nop_order_id', $qb->createNamedParameter($nopOrderId)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return null;
		}
	}

	/**
	 * @return OrderEntity[]
	 */
	public function findFiltered(?string $startDate = null, ?string $endDate = null, int $storeId = 0, int $limit = 100, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName());

		if ($startDate !== null && $startDate !== '') {
			$qb->andWhere($qb->expr()->gte('created_on_utc', $qb->createNamedParameter($startDate)));
		}
		if ($endDate !== null && $endDate !== '') {
			$qb->andWhere($qb->expr()->lte('created_on_utc', $qb->createNamedParameter($endDate)));
		}
		if ($storeId > 0) {
			$qb->andWhere($qb->expr()->eq('store_id', $qb->createNamedParameter($storeId)));
		}

		$qb->orderBy('created_on_utc', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);

		return $this->findEntities($qb);
	}

	public function upsert(OrderEntity $entity): OrderEntity {
		$existing = $this->findByNopOrderId($entity->getNopOrderId());
		if ($existing !== null) {
			$entity->setId($existing->getId());
			return $this->update($entity);
		}
		return $this->insert($entity);
	}
}
