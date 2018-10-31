<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservationsPriority\Test\Integration\Model\Algorithms;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\InventoryShipping\Model\GetSourceSelectionResultFromInvoice;
use Magento\InventoryShipping\Model\InventoryRequestFromOrderFactory;
use Magento\InventorySourceSelection\Model\GetDefaultSourceSelectionAlgorithmCode;
use Magento\InventorySourceSelection\Model\SourceSelectionService;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceOrderInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use ReachDigital\IOSReservations\Model\GetOrderSourceReservations;
use ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSource;
use ReachDigital\IOSReservationsPriorityApi\Api\OrderSelectionServiceInterface;

class ByDateCreatedAlgorithmTest extends \PHPUnit\Framework\TestCase
{

    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var InvoiceOrderInterface */
    private $invoiceOrder;

    /** @var OrderSelectionServiceInterface */
    private $orderSelectionService;

    /** @var SourceSelectionService */
    private $sourceSelectionService;

    /** @var MoveReservationsFromStockToSource */
    private $moveReservationsFromStockToSource;

    /** @var GetDefaultSourceSelectionAlgorithmCode */
    private $getDefaultSourceSelectionAlgorithmCode;

    /** @var GetOrderSourceReservations */
    private $getOrderSourceReservations;

    protected function setUp()
    {
        $this->searchCriteriaBuilder = Bootstrap::getObjectManager()->get(SearchCriteriaBuilder::class);
        $this->orderRepository = Bootstrap::getObjectManager()->get(OrderRepositoryInterface::class);
        $this->invoiceOrder = Bootstrap::getObjectManager()->get(InvoiceOrderInterface::class);
        $this->orderSelectionService = Bootstrap::getObjectManager()->get(OrderSelectionServiceInterface::class);
        $this->sourceSelectionService = Bootstrap::getObjectManager()->get(SourceSelectionService::class);
        $this->moveReservationsFromStockToSource = Bootstrap::getObjectManager()->get(MoveReservationsFromStockToSource::class);
        $this->getDefaultSourceSelectionAlgorithmCode = Bootstrap::getObjectManager()->get(GetDefaultSourceSelectionAlgorithmCode::class);
        $this->getOrderSourceReservations = Bootstrap::getObjectManager()->get(GetOrderSourceReservations::class);
    }

    /**
     * @test
     *
     * @magentoDbIsolation disabled
     *
     * @covers \ReachDigital\IOSReservationsPriorityApi\Model\OrderSelectionService
     * @covers \ReachDigital\IOSReservationsPriority\Model\Algorithms\ByDateCreatedAlgorithm
     *
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/products.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/source_items_for_bundle_children.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/products_bundle.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservationsPriority/Test/_files/order_bundle_products.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory.php
     */
    public function should_retrieve_unsourced_orders_by_date(): void
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', 'test_order_bundle_1')
            ->create();
        /** @var OrderInterface $order */
        $order = current($this->orderRepository->getList($searchCriteria)->getItems());
        $this->invoiceOrder->execute($order->getEntityId());

        $result = $this->orderSelectionService->execute(null, 'byDateCreated');
        $this->assertEquals(1, $result->getTotalCount());
    }

    /**
     * @test
     *
     * @magentoDbIsolation disabled
     *
     * @covers \ReachDigital\IOSReservationsPriorityApi\Model\OrderSelectionService
     * @covers \ReachDigital\IOSReservationsPriority\Model\Algorithms\ByDateCreatedAlgorithm
     *
     * Rolling back previous database mess
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/order_simple_product_rollback.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/create_quote_on_eu_website_rollback.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory_rollback.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/source_items_for_simple_on_multi_source_rollback.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/simple_product_rollback.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-sales-api/Test/_files/websites_with_stores_rollback.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stock_source_links_rollback.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stocks_rollback.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/sources_rollback.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-inventory-source-reservations/ISReservations/Test/Integration/_files/clean_all_reservations.php
     *
     * Filling database
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/sources.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stocks.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stock_source_links.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-sales-api/Test/_files/websites_with_stores.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-sales-api/Test/_files/stock_website_sales_channels.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/simple_product.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/source_items_for_simple_on_multi_source.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/create_quote_on_eu_website.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/order_simple_product.php
     *
     * @throws
     */
    public function should_skip_sourced_orders(): void
    {
        // Fixture: have an unsourced order

        // Invoice order so it reaches processing state
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', 'created_order_for_test')
            ->create();
        /** @var OrderInterface $order */
        $order = current($this->orderRepository->getList($searchCriteria)->getItems());
        $this->invoiceOrder->execute($order->getEntityId());

        // Run order selection algo and assert that it selected the order
        $result = $this->orderSelectionService->execute(null, 'byDateCreated');
        $this->assertEquals(1, $result->getTotalCount());

        // Run source selection and assert that orderitems were assigned to sources
        $this->moveReservationsFromStockToSource->execute(
            (int) $order->getEntityId(),
            $this->getDefaultSourceSelectionAlgorithmCode->execute()
        );
        $sourceReservations = $this->getOrderSourceReservations->execute((int)$order->getEntityId());
        $items = $sourceReservations->getReservationItems();
        self::assertCount(2, $items);

        // Run order selection and assert that it skipped the order
        $result = $this->orderSelectionService->execute(null, 'byDateCreated');
        $this->assertEquals(0, $result->getTotalCount());
    }
}
