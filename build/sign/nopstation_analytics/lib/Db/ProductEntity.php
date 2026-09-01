<?php

declare(strict_types=1);

namespace OCA\NopStationAnalytics\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getNopProductId()
 * @method void setNopProductId(int $nopProductId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string|null getSku()
 * @method void setSku(?string $sku)
 * @method float getPrice()
 * @method void setPrice(float $price)
 * @method float getCost()
 * @method void setCost(float $cost)
 * @method int getStockQuantity()
 * @method void setStockQuantity(int $stockQuantity)
 * @method bool getPublished()
 * @method void setPublished(bool $published)
 */
class ProductEntity extends Entity {
	protected ?int $nopProductId = null;
	protected ?string $name = null;
	protected ?string $sku = null;
	protected ?float $price = null;
	protected ?float $cost = null;
	protected ?int $stockQuantity = null;
	protected ?bool $published = null;

	public function __construct() {
		$this->addType('nopProductId', 'integer');
		$this->addType('price', 'float');
		$this->addType('cost', 'float');
		$this->addType('stockQuantity', 'integer');
		$this->addType('published', 'boolean');
	}
}
