<?php

declare(strict_types=1);

namespace OCA\NopStationAnalytics\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getNopOrderId()
 * @method void setNopOrderId(int $nopOrderId)
 * @method string|null getOrderGuid()
 * @method void setOrderGuid(?string $orderGuid)
 * @method string|null getCustomOrderNumber()
 * @method void setCustomOrderNumber(?string $customOrderNumber)
 * @method int getStoreId()
 * @method void setStoreId(int $storeId)
 * @method string|null getStoreName()
 * @method void setStoreName(?string $storeName)
 * @method int getCustomerId()
 * @method void setCustomerId(int $customerId)
 * @method string|null getCustomerEmail()
 * @method void setCustomerEmail(?string $customerEmail)
 * @method string|null getCustomerFullName()
 * @method void setCustomerFullName(?string $customerFullName)
 * @method int getOrderStatusId()
 * @method void setOrderStatusId(int $orderStatusId)
 * @method int getPaymentStatusId()
 * @method void setPaymentStatusId(int $paymentStatusId)
 * @method int getShippingStatusId()
 * @method void setShippingStatusId(int $shippingStatusId)
 * @method float getOrderSubtotalInclTax()
 * @method void setOrderSubtotalInclTax(float $orderSubtotalInclTax)
 * @method float getOrderSubtotalExclTax()
 * @method void setOrderSubtotalExclTax(float $orderSubtotalExclTax)
 * @method float getOrderTax()
 * @method void setOrderTax(float $orderTax)
 * @method float getOrderDiscount()
 * @method void setOrderDiscount(float $orderDiscount)
 * @method float getOrderShipping()
 * @method void setOrderShipping(float $orderShipping)
 * @method float getOrderTotal()
 * @method void setOrderTotal(float $orderTotal)
 * @method float getProfit()
 * @method void setProfit(float $profit)
 * @method string|null getPaymentMethodSystemName()
 * @method void setPaymentMethodSystemName(?string $paymentMethodSystemName)
 * @method string|null getShippingMethod()
 * @method void setShippingMethod(?string $shippingMethod)
 * @method string getCreatedOnUtc()
 * @method void setCreatedOnUtc(string $createdOnUtc)
 */
class OrderEntity extends Entity {
	protected ?int $nopOrderId = null;
	protected ?string $orderGuid = null;
	protected ?string $customOrderNumber = null;
	protected ?int $storeId = null;
	protected ?string $storeName = null;
	protected ?int $customerId = null;
	protected ?string $customerEmail = null;
	protected ?string $customerFullName = null;
	protected ?int $orderStatusId = null;
	protected ?int $paymentStatusId = null;
	protected ?int $shippingStatusId = null;
	protected ?float $orderSubtotalInclTax = null;
	protected ?float $orderSubtotalExclTax = null;
	protected ?float $orderTax = null;
	protected ?float $orderDiscount = null;
	protected ?float $orderShipping = null;
	protected ?float $orderTotal = null;
	protected ?float $profit = null;
	protected ?string $paymentMethodSystemName = null;
	protected ?string $shippingMethod = null;
	protected ?string $createdOnUtc = null;

	public function __construct() {
		$this->addType('nopOrderId', 'integer');
		$this->addType('storeId', 'integer');
		$this->addType('customerId', 'integer');
		$this->addType('orderStatusId', 'integer');
		$this->addType('paymentStatusId', 'integer');
		$this->addType('shippingStatusId', 'integer');
		$this->addType('orderSubtotalInclTax', 'float');
		$this->addType('orderSubtotalExclTax', 'float');
		$this->addType('orderTax', 'float');
		$this->addType('orderDiscount', 'float');
		$this->addType('orderShipping', 'float');
		$this->addType('orderTotal', 'float');
		$this->addType('profit', 'float');
	}
}
