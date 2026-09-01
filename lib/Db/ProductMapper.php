<?php

declare(strict_types=1);

namespace OCA\NopStationAnalytics\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<ProductEntity>
 */
class ProductMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'nop_products', ProductEntity::class);
	}

	public function findByNopProductId(int $nopProductId): ?ProductEntity {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('nop_product_id', $qb->createNamedParameter($nopProductId)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return null;
		}
	}

	/**
	 * @return ProductEntity[]
	 */
	public function findLowStock(int $threshold = 5): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->lte('stock_quantity', $qb->createNamedParameter($threshold)))
			->andWhere($qb->expr()->eq('published', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)))
			->orderBy('stock_quantity', 'ASC');

		return $this->findEntities($qb);
	}

	public function upsert(ProductEntity $entity): ProductEntity {
		$existing = $this->findByNopProductId($entity->getNopProductId());
		if ($existing !== null) {
			$entity->setId($existing->getId());
			return $this->update($entity);
		}
		return $this->insert($entity);
	}
}
