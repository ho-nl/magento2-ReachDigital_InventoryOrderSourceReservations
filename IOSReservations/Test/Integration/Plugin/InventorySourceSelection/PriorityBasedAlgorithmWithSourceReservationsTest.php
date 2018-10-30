<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace IOSReservations\Test\Integration\Plugin\InventorySourceSelection;

use IOSReservations\Plugin\InventorySourceSelection\PriorityBasedAlgorithmWithSourceReservations;
use Magento\InventorySourceSelection\Model\Algorithms\PriorityBasedAlgorithm;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class PriorityBasedAlgorithmWithSourceReservationsTest extends TestCase
{
    /** @var PriorityBasedAlgorithm */
    private $priorityBasedAlgorithm;

    public function setUp()
    {
        $this->priorityBasedAlgorithm = Bootstrap::getObjectManager()->get(PriorityBasedAlgorithm::class);
    }

    /**
     * @test
     * @covers \IOSReservations\Plugin\InventorySourceSelection\PriorityBasedAlgorithmWithSourceReservations
     *
     * @magentoDbIsolation disabled
     *
     * Rolling back previous database mess
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory_rollback.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/product_simple_with_custom_options_rollback.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-sales-api/Test/_files/websites_with_stores_rollback.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stock_source_links_rollback.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stocks_rollback.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/sources_rollback.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/clean_all_reservations.php
     *
     * Filling database
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/sources.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stocks.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stock_source_links.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-sales-api/Test/_files/websites_with_stores.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-sales-api/Test/_files/stock_website_sales_channels.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/product_simple_with_custom_options.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/source_items_for_simple_on_multi_source.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/create_quote_on_eu_website.php
     */
    public function should_include_reservations_in_default_ssa(): void
    {
        // Fixture: eu-1 has qty of 2
        // Add reservation of 2
        // Try to select source for qty 4

    }
}
