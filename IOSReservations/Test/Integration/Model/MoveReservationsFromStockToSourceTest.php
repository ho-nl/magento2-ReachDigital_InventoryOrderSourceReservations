<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservationsPriority\Test\Integration\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventorySales\Model\GetProductSalableQty;
use Magento\InventorySourceSelectionApi\Api\GetDefaultSourceSelectionAlgorithmCodeInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceOrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSource;

class MoveReservationsFromStockToSourceTest extends \PHPUnit\Framework\TestCase
{

    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var InvoiceOrderInterface */
    private $invoiceOrder;

    /** @var MoveReservationsFromStockToSource */
    private $moveReservationsFromStockToSource;

    /** @var GetDefaultSourceSelectionAlgorithmCodeInterface */
    private $getDefaultSourceSelectionAlgorithmCode;

    /** @var GetProductSalableQty */
    private $getProductSalableQty;

    protected function setUp()
    {
        $this->searchCriteriaBuilder = Bootstrap::getObjectManager()->get(SearchCriteriaBuilder::class);
        $this->orderRepository = Bootstrap::getObjectManager()->get(OrderRepositoryInterface::class);
        $this->invoiceOrder = Bootstrap::getObjectManager()->get(InvoiceOrderInterface::class);
        $this->moveReservationsFromStockToSource = Bootstrap::getObjectManager()->get(MoveReservationsFromStockToSource::class);
        $this->getDefaultSourceSelectionAlgorithmCode = Bootstrap::getObjectManager()->get(GetDefaultSourceSelectionAlgorithmCodeInterface::class);
        $this->getProductSalableQty = Bootstrap::getObjectManager()->get(GetProductSalableQty::class);
    }

    /**
     * @test
     *
     * @covers \ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSource
     *
     * @magentoDbIsolation disabled
     *
     * Rolling back previous database mess
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/order_simple_product_with_custom_options_rollback.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory_rollback.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/product_simple_with_custom_options_rollback.php
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
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/product_simple_with_custom_options.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/source_items_for_simple_on_multi_source.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/create_quote_on_eu_website.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/order_simple_product_with_custom_options.php
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function should_move_reservation_from_stock_to_source() : void
    {
        //There are 14 actually available in the source, order is placed with three.
        $salableQty = $this->getProductSalableQty->execute('simple', 10);
        self::assertEquals(11, $salableQty);

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', 'created_order_for_test')
            ->create();
        /** @var OrderInterface $order */
        $order = current($this->orderRepository->getList($searchCriteria)->getItems());
        $this->invoiceOrder->execute($order->getEntityId());

        $salableQty = $this->getProductSalableQty->execute('simple', 10);
        self::assertEquals(11, $salableQty);

        $this->moveReservationsFromStockToSource->execute(
            (int) $order->getEntityId(),
            $this->getDefaultSourceSelectionAlgorithmCode->execute()
        );

        $salableQty = $this->getProductSalableQty->execute('simple', 10);
        self::assertEquals(11, $salableQty);
    }

    /**
     * @test
     *
     * @covers \ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSource
     *
     *
     * @magentoDbIsolation disabled
     *
     * Rolling back previous database mess
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/order_simple_product_with_custom_options_rollback.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory_rollback.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/product_simple_with_custom_options_rollback.php
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
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/product_simple_with_custom_options.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/source_items_for_simple_on_multi_source.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/create_quote_on_eu_website.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/order_simple_product_with_custom_options.php
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function should_not_move_reservations_if_already_moved() : void
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', 'created_order_for_test')
            ->create();
        /** @var OrderInterface $order */
        $order = current($this->orderRepository->getList($searchCriteria)->getItems());
        $this->invoiceOrder->execute($order->getEntityId());

        $salableQty = $this->getProductSalableQty->execute('simple', 10);
        $this->assertEquals(11, $salableQty);

        $this->moveReservationsFromStockToSource->execute(
            (int) $order->getEntityId(),
            $this->getDefaultSourceSelectionAlgorithmCode->execute()
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageRegExp('/Can not assign sources, source already selected for order/');
        $this->moveReservationsFromStockToSource->execute(
            (int) $order->getEntityId(),
            $this->getDefaultSourceSelectionAlgorithmCode->execute()
        );
    }

    /**
     * @todo Source Backorder Support
     * @test
     * Sometimes qty's need to be used by the source selection service, but other times it shouldn't? When does S&H
     * need to know about the orders? Does S&H only need to know how much inventory is incomming (communicating a
     * purchase order) or do they need to receive all orders directly?
     *
     * In OppoSuits' case, for most purchase orders there is a flow where the warehouse will need to receive orders
     * that will need to be shipped at a certain point.
     *       1. The system wil withhold all orders from the warehouse until the last moment.
     *       2. Like a day before the actual container arrives at the warehouse all the orders are sent to the
     *       warehouse. Question is, is this required, or can we skip this, because it is already covered by the
     *       purchaseOrder document?
     *
     * There seems to be a state difference if a reservation should be used for calculation of the sourceSelection
     * algorithm.
     */
    public function should_not_move_qtys_when_backorder_support_is_disabled_for_source() : void
    {

    }
}
