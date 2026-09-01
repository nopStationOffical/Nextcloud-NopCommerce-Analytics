<?php

declare(strict_types=1);

namespace OCA\NopStationAnalytics\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version001000Date2026083101 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// 1. Orders table
		if (!$schema->hasTable('nop_orders')) {
			$table = $schema->createTable('nop_orders');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('nop_order_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('order_guid', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('custom_order_number', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('store_id', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('store_name', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('customer_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('customer_email', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('customer_full_name', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('order_status_id', Types::INTEGER, [
				'notnull' => true,
				'default' => 10,
			]);
			$table->addColumn('payment_status_id', Types::INTEGER, [
				'notnull' => true,
				'default' => 10,
			]);
			$table->addColumn('shipping_status_id', Types::INTEGER, [
				'notnull' => true,
				'default' => 10,
			]);
			$table->addColumn('order_subtotal_incl_tax', Types::DECIMAL, [
				'notnull' => true,
				'precision' => 18,
				'scale' => 4,
				'default' => 0.0000,
			]);
			$table->addColumn('order_subtotal_excl_tax', Types::DECIMAL, [
				'notnull' => true,
				'precision' => 18,
				'scale' => 4,
				'default' => 0.0000,
			]);
			$table->addColumn('order_tax', Types::DECIMAL, [
				'notnull' => true,
				'precision' => 18,
				'scale' => 4,
				'default' => 0.0000,
			]);
			$table->addColumn('order_discount', Types::DECIMAL, [
				'notnull' => true,
				'precision' => 18,
				'scale' => 4,
				'default' => 0.0000,
			]);
			$table->addColumn('order_shipping', Types::DECIMAL, [
				'notnull' => true,
				'precision' => 18,
				'scale' => 4,
				'default' => 0.0000,
			]);
			$table->addColumn('order_total', Types::DECIMAL, [
				'notnull' => true,
				'precision' => 18,
				'scale' => 4,
				'default' => 0.0000,
			]);
			$table->addColumn('profit', Types::DECIMAL, [
				'notnull' => true,
				'precision' => 18,
				'scale' => 4,
				'default' => 0.0000,
			]);
			$table->addColumn('payment_method_system_name', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('shipping_method', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('created_on_utc', Types::DATETIME, [
				'notnull' => true,
			]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['nop_order_id'], 'nop_orders_nop_id_idx');
			$table->addIndex(['store_id'], 'nop_orders_store_id_idx');
			$table->addIndex(['customer_id'], 'nop_orders_customer_id_idx');
			$table->addIndex(['created_on_utc'], 'nop_orders_created_idx');
			$table->addIndex(['payment_status_id'], 'nop_orders_payment_idx');
		}

		// 2. Order Items table
		if (!$schema->hasTable('nop_order_items')) {
			$table = $schema->createTable('nop_order_items');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('nop_item_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('order_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('product_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('product_name', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('product_sku', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('quantity', Types::INTEGER, [
				'notnull' => true,
				'default' => 1,
			]);
			$table->addColumn('unit_price', Types::DECIMAL, [
				'notnull' => true,
				'precision' => 18,
				'scale' => 4,
				'default' => 0.0000,
			]);
			$table->addColumn('total_price', Types::DECIMAL, [
				'notnull' => true,
				'precision' => 18,
				'scale' => 4,
				'default' => 0.0000,
			]);
			$table->addColumn('attribute_xml', Types::TEXT, [
				'notnull' => false,
			]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['nop_item_id'], 'nop_items_nop_id_idx');
			$table->addIndex(['order_id'], 'nop_items_order_id_idx');
			$table->addIndex(['product_id'], 'nop_items_product_id_idx');
		}

		// 3. Customers table
		if (!$schema->hasTable('nop_customers')) {
			$table = $schema->createTable('nop_customers');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('nop_customer_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('customer_guid', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('email', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('username', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('full_name', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('active', Types::BOOLEAN, [
				'notnull' => false,
				'default' => true,
			]);
			$table->addColumn('created_on_utc', Types::DATETIME, [
				'notnull' => false,
			]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['nop_customer_id'], 'nop_customers_nop_id_idx');
			$table->addIndex(['email'], 'nop_customers_email_idx');
		}

		// 4. Products table
		if (!$schema->hasTable('nop_products')) {
			$table = $schema->createTable('nop_products');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('nop_product_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('name', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('sku', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('price', Types::DECIMAL, [
				'notnull' => true,
				'precision' => 18,
				'scale' => 4,
				'default' => 0.0000,
			]);
			$table->addColumn('cost', Types::DECIMAL, [
				'notnull' => true,
				'precision' => 18,
				'scale' => 4,
				'default' => 0.0000,
			]);
			$table->addColumn('stock_quantity', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('published', Types::BOOLEAN, [
				'notnull' => false,
				'default' => true,
			]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['nop_product_id'], 'nop_products_nop_id_idx');
			$table->addIndex(['sku'], 'nop_products_sku_idx');
		}

		// 5. Sync Logs table
		if (!$schema->hasTable('nop_sync_logs')) {
			$table = $schema->createTable('nop_sync_logs');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('sync_type', Types::STRING, [
				'notnull' => true,
				'length' => 64,
				'default' => 'full',
			]);
			$table->addColumn('entity_type', Types::STRING, [
				'notnull' => true,
				'length' => 64,
				'default' => 'orders',
			]);
			$table->addColumn('records_processed', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('status', Types::STRING, [
				'notnull' => true,
				'length' => 32,
				'default' => 'success',
			]);
			$table->addColumn('error_message', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['sync_type'], 'nop_sync_logs_type_idx');
			$table->addIndex(['created_at'], 'nop_sync_logs_created_idx');
		}

		return $schema;
	}
}
