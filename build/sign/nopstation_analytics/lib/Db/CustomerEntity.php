<?php

declare(strict_types=1);

namespace OCA\NopStationAnalytics\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getNopCustomerId()
 * @method void setNopCustomerId(int $nopCustomerId)
 * @method string|null getCustomerGuid()
 * @method void setCustomerGuid(?string $customerGuid)
 * @method string|null getEmail()
 * @method void setEmail(?string $email)
 * @method string|null getUsername()
 * @method void setUsername(?string $username)
 * @method string|null getFullName()
 * @method void setFullName(?string $fullName)
 * @method bool getActive()
 * @method void setActive(bool $active)
 * @method string|null getCreatedOnUtc()
 * @method void setCreatedOnUtc(?string $createdOnUtc)
 */
class CustomerEntity extends Entity {
	protected ?int $nopCustomerId = null;
	protected ?string $customerGuid = null;
	protected ?string $email = null;
	protected ?string $username = null;
	protected ?string $fullName = null;
	protected ?bool $active = null;
	protected ?string $createdOnUtc = null;

	public function __construct() {
		$this->addType('nopCustomerId', 'integer');
		$this->addType('active', 'boolean');
	}
}
