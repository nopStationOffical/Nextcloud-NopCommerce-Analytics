---
name: analytics-calculations
description: >-
  Skill for implementing KPI calculations, time-series aggregations,
  product bestseller rankings, customer segmentation, and report generation
  from locally-stored nopCommerce entity data.
---

# Analytics Calculations Skill

## When to Use
Activate when building or modifying `AnalyticsCalculatorService` or `ExportService`.

## KPI Calculations

### Revenue & Order Metrics
All calculations query local Nextcloud DB tables (`*prefix*_nop_orders`).

```php
class AnalyticsCalculatorService {
    
    public function getKpiSummary(?string $startDate, ?string $endDate, int $storeId = 0): array {
        $qb = $this->db->getQueryBuilder();
        
        $qb->select(
            $qb->createFunction('COUNT(*) as total_orders'),
            $qb->createFunction('SUM(order_total) as total_revenue'),
            $qb->createFunction('AVG(order_total) as avg_order_value'),
            $qb->createFunction('SUM(profit) as total_profit'),
            $qb->createFunction('SUM(order_shipping) as total_shipping'),
            $qb->createFunction('SUM(order_tax) as total_tax'),
            $qb->createFunction('COUNT(DISTINCT customer_id) as unique_customers')
        )
        ->from('nop_orders')
        ->where($qb->expr()->in('payment_status_id', [30, 35])); // Paid or PartiallyRefunded
        
        if ($startDate) {
            $qb->andWhere($qb->expr()->gte('created_on_utc', $qb->createNamedParameter($startDate)));
        }
        if ($endDate) {
            $qb->andWhere($qb->expr()->lte('created_on_utc', $qb->createNamedParameter($endDate)));
        }
        if ($storeId > 0) {
            $qb->andWhere($qb->expr()->eq('store_id', $qb->createNamedParameter($storeId)));
        }
        
        $result = $qb->executeQuery()->fetch();
        
        return [
            'totalOrders' => (int)$result['total_orders'],
            'totalRevenue' => round((float)$result['total_revenue'], 2),
            'avgOrderValue' => round((float)$result['avg_order_value'], 2),
            'totalProfit' => round((float)$result['total_profit'], 2),
            'totalShipping' => round((float)$result['total_shipping'], 2),
            'totalTax' => round((float)$result['total_tax'], 2),
            'uniqueCustomers' => (int)$result['unique_customers'],
        ];
    }
}
```

### Time-Series Trends
Group by day, week, or month:
```php
public function getTrends(string $groupBy, ?string $startDate, ?string $endDate): array {
    $dateExpr = match ($groupBy) {
        'day'   => "DATE(created_on_utc)",
        'week'  => "DATE(created_on_utc - INTERVAL (DAYOFWEEK(created_on_utc) - 1) DAY)",
        'month' => "DATE_FORMAT(created_on_utc, '%Y-%m-01')",
    };
    
    // Query with GROUP BY date bucket
    // Return array of { period, orders, revenue, profit }
}
```

### Product Bestsellers
Join `nop_order_items` with `nop_products`:
```php
public function getBestsellers(int $limit = 10, string $sortBy = 'quantity'): array {
    // GROUP BY product_id
    // SUM(quantity) as total_quantity
    // SUM(total_price) as total_revenue
    // ORDER BY total_quantity DESC or total_revenue DESC
    // LIMIT $limit
}
```

### Customer Segmentation
```php
public function getCustomerSegmentation(): array {
    // New vs Returning:
    //   New = customers with exactly 1 order
    //   Returning = customers with > 1 order
    
    // Top Customers by spend:
    //   GROUP BY customer_id, SUM(order_total), ORDER BY SUM DESC
    
    // Geographic breakdown:
    //   Would need billing address data from order details
}
```

## Export Service

### CSV Export
```php
public function exportCsv(string $reportType, array $data, string $userId): string {
    $folder = $this->rootFolder->getUserFolder($userId);
    $targetDir = '/NopCommerce_Analytics/Reports/';
    
    // Ensure directory exists
    // Write CSV with headers + data rows
    // Return file path
}
```

### Scheduled Reports
Background job checks `*prefix*_nop_report_schedules` table for active schedules.
If `last_run` + frequency interval has elapsed, generate report and save to configured folder.
