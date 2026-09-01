# Database Patterns & Migration Conventions

## Table Naming
All tables use Nextcloud's table prefix (`*prefix*`):
- `*prefix*_nop_orders` — Synced order records
- `*prefix*_nop_order_items` — Order line items (linked to orders)
- `*prefix*_nop_customers` — Synced customer records
- `*prefix*_nop_products` — Synced product catalog records
- `*prefix*_nop_sync_logs` — Audit trail for all sync operations

## Entity Pattern (Nextcloud `Entity`)
Each table maps to a PHP Entity class extending `OCP\AppFramework\Db\Entity`:
```php
namespace OCA\NopStationAnalytics\Db;

use OCP\AppFramework\Db\Entity;

class OrderEntity extends Entity {
    protected int $nopOrderId = 0;
    protected string $orderGuid = '';
    protected int $storeId = 0;
    protected int $customerId = 0;
    protected int $orderStatusId = 0;
    protected int $paymentStatusId = 0;
    protected int $shippingStatusId = 0;
    protected float $subtotalExclTax = 0.0;
    protected float $subtotalInclTax = 0.0;
    protected float $orderTax = 0.0;
    protected float $orderDiscount = 0.0;
    protected float $orderShipping = 0.0;
    protected float $orderTotal = 0.0;
    protected float $profit = 0.0;
    protected string $paymentMethod = '';
    protected string $shippingMethod = '';
    protected string $createdOnUtc = '';

    public function __construct() {
        $this->addType('nopOrderId', 'integer');
        $this->addType('storeId', 'integer');
        // ... all typed fields
    }
}
```

## Mapper Pattern (Nextcloud `QBMapper`)
Each Entity has a corresponding Mapper extending `OCP\AppFramework\Db\QBMapper`:
```php
class OrderMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'nop_orders', OrderEntity::class);
    }

    public function findByNopOrderId(int $nopOrderId): ?OrderEntity { ... }
    public function findByDateRange(string $startDate, string $endDate): array { ... }
}
```

## Migration Convention
- Migration class naming: `Version{MAJOR}{MINOR}{PATCH}Date{YYYYMMDD}{SEQ}.php`
- Example: `Version001000Date2026083101.php`
- Migrations extend `OCP\Migration\SimpleMigrationStep`.
- Use `$schema->createTable()` / `$schema->getTable()` for DDL.

## Critical Data Type Rules
- **Money fields**: Store as `DECIMAL(18,4)` or PHP `float`. NEVER store formatted strings like `"$700.00"`.
- **Dates**: Store as `DATETIME` in UTC (`createdOnUtc`).
- **Foreign keys**: `nop_order_id`, `customer_id`, `product_id` are nopCommerce IDs (integer), not Nextcloud IDs.
- **Sync dedup key**: Each entity table has a unique index on the nopCommerce ID column (e.g. `nop_order_id`).
