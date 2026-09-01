<?php

declare(strict_types=1);

namespace OCA\NopStationAnalytics\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getNopItemId()
 * @method void setNopItemId(int $nopItemId)
 * @method int getOrderId()
 * @method void setOrderId(int $orderId)
 * @method int getProductId()
 * @method void setProductId(int $productId)
 * @method string getProductName()
 * @method void setProductName(string $productName)
 * @method string|null getProductSku()
 * @method void setProductSku(?string $productSku)
 * @method int getQuantity()
 * @method void setQuantity(int $quantity)
 * @method float getUnitPrice()
 * @method void setUnitPrice(float $unitPrice)
 * @method float getTotalPrice()
 * @method void setTotalPrice(float $totalPrice)
 * @method string|null getAttributeXml()
 * @method void setAttributeXml(?string $attributeXml)
 */
class OrderItemEntity extends Entity {
	protected ?int $nopItemId = null;
	protected ?int $orderId = null;
	protected ?int $productId = null;
	protected ?string $productName = null;
	protected ?string $productSku = null;
	protected ?int $quantity = null;
	protected ?float $unitPrice = null;
	protected ?float $totalPrice = null;
	protected ?string $attributeXml = null;

	public function __construct() {
		$this->addType('nopItemId', 'integer');
		$this->addType('orderId', 'integer');
		$this->addType('productId', 'integer');
		$this->addType('quantity', 'integer');
		$this->addType('unitPrice', 'float');
		$this->addType('totalPrice', 'float');
	}
}
