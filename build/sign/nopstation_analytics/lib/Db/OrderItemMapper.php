<?php

declare(strict_types=1);

namespace OCA\NopStationAnalytics\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<OrderItemEntity>
 */
class OrderItemMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'nop_order_items', OrderItemEntity::class);
	}

	public function findByNopItemId(int $nopItemId): ?OrderItemEntity {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('nop_item_id', $qb->createNamedParameter($nopItemId)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return null;
		}
	}

	/**
	 * @return OrderItemEntity[]
	 */
	public function findByOrderId(int $orderId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('order_id', $qb->createNamedParameter($orderId)));

		return $this->findEntities($qb);
	}

	public function upsert(OrderItemEntity $entity): OrderItemEntity {
		$existing = $this->findByNopItemId($entity->getNopItemId());
		if ($existing !== null) {
			$entity->setId($existing->getId());
			return $this->update($entity);
		}
		return $this->insert($entity);
	}
}
