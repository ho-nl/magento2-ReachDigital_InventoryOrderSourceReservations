<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace ReachDigital\IOSReservations\Test\Integration\Observer\MagentoInventorySales;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Inventory\Model\SourceItem\Command\GetSourceItemsBySku;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemRepositoryInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryReservations\Model\ResourceModel\GetReservationsQuantity;
use Magento\InventoryReservationsApi\Model\CleanupReservationsInterface;
use Magento\InventoryReservationsApi\Model\GetReservationsQuantityInterface;
use Magento\InventorySourceSelection\Model\GetDefaultSourceSelectionAlgorithmCode;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use ReachDigital\IOSReservationsApi\Api\MoveReservationsFromStockToSourceInterface;
use ReachDigital\ISReservations\Model\ResourceModel\GetReservationsQuantityList;
use ReachDigital\ISReservationsApi\Api\EncodeMetaDataInterface;
use ReachDigital\ISReservationsApi\Api\GetReservationsByMetadataInterface;

class CancelOrderItemObserverTest extends TestCase
{
    /** @var GetReservationsQuantityList */
    private $getReservationsQuantityList;

    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var MoveReservationsFromStockToSourceInterface */
    private $moveReservationsFromStockToSource;

    /** @var GetDefaultSourceSelectionAlgorithmCode */
    private $getDefaultSourceSelectionAlgorithmCode;

    /** @var GetReservationsQuantity */
    private $getStockReservationsQuantity;

    /** @var GetSourceItemsBySku */
    private $getSourceItemsBySku;

    /** @var CleanupReservationsInterface */
    private $cleanupReservations;

    /** @var SourceItemRepositoryInterface  */
    private $sourceItemRepository;

    /** @var SourceItemsSaveInterface */
    private $sourceItemsSave;
    /**
     * @var GetReservationsByMetadataInterface
     */
    private $getReservationsByMetadata;
    /**
     * @var EncodeMetaDataInterface
     */
    private $encodeMetaData;

    public function setUp()
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->addSharedInstance(
            $objectManager->get(GetReservationsQuantity::class),
            GetReservationsQuantityInterface::class
        );

        $this->getReservationsQuantityList = $objectManager->get(GetReservationsQuantityList::class);
        $this->orderRepository = $objectManager->get(OrderRepositoryInterface::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $this->moveReservationsFromStockToSource = $objectManager->get(
            MoveReservationsFromStockToSourceInterface::class
        );
        $this->getDefaultSourceSelectionAlgorithmCode = $objectManager->get(
            GetDefaultSourceSelectionAlgorithmCode::class
        );
        $this->getStockReservationsQuantity = $objectManager->get(GetReservationsQuantity::class);
        $this->getSourceItemsBySku = $objectManager->get(GetSourceItemsBySku::class);
        $this->cleanupReservations = $objectManager->get(CleanupReservationsInterface::class);
        $this->sourceItemRepository = $objectManager->get(SourceItemRepositoryInterface::class);
        $this->sourceItemsSave = $objectManager->get(SourceItemsSaveInterface::class);
        $this->getReservationsByMetadata = $objectManager->get(GetReservationsByMetadataInterface::class);
        $this->encodeMetaData = $objectManager->get(EncodeMetaDataInterface::class);
    }

    /**
     *
     * @test
     *
     * @covers \ReachDigital\IOSReservations\Observer\MagentoInventorySales\CancelOrderItemObserver
     *
     * @magentoDbIsolation disabled
     *
     * Rolling back previous database mess
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/order_simple_product_rollback.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/create_quote_on_eu_website_rollback.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory_rollback.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/source_items_for_simple_on_multi_source_rollback.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/simple_product_rollback.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/websites_with_stores_rollback.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stock_source_links_rollback.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stocks_rollback.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/sources_rollback.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-inventory-source-reservations/ISReservations/Test/Integration/_files/clean_all_reservations.php
     *
     * Filling database
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/sources.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stocks.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stock_source_links.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/websites_with_stores.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-sales-api/Test/_files/stock_website_sales_channels.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/simple_product.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/source_items_for_simple_on_multi_source.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/create_quote_on_eu_website.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/order_simple_product.php
     *
     * @throws
     */
    public function should_correctly_revert_uncaptured_processing_order_with_return_to_stock_at_cancel(): void
    {
        // Have order placed with qty:3 on simple.

        // Test the following scenario: order is placed, sourced-assigned and then cancelled before being shipped (and
        // thus items never left the source) and before being paid (authorised, but not captured).
        // Source reservations should be nullified and stock reservations should be refunded.

        self::assertEquals(-3, $this->getStockReservationsQuantity->execute('simple', 10));

        // Have an invoiced order
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('increment_id', 'created_order_for_test')->create();
        /** @var Order $order */
        $order = current($this->orderRepository->getList($searchCriteria)->getItems());

        // Set order to 'processing' without invoice (authorise without capture)
        $order->setStatus(Order::STATE_PROCESSING);
        $order->setState(Order::STATE_PROCESSING);
        $this->orderRepository->save($order);

        // Assign order to sources: stock reservation moved to source
        $this->moveReservationsFromStockToSource->execute(
            (int) $order->getEntityId(),
            $this->getDefaultSourceSelectionAlgorithmCode->execute()
        );

        self::assertEquals(0, $this->getStockReservationsQuantity->execute('simple', 10));
        self::assertEquals(-3, $this->getReservationsQuantityList->execute(['simple'])['simple']['quantity']);

        // Cancel order
        $order->cancel();
        $this->orderRepository->save($order);

        self::assertEquals(0, $this->getStockReservationsQuantity->execute('simple', 10));
        self::assertEquals(0, $this->getReservationsQuantityList->execute(['simple'])['simple']['quantity']);
    }

    /**
     *
     * @test
     *
     * @covers \ReachDigital\IOSReservations\Observer\MagentoInventorySales\CancelOrderItemObserver
     *
     * @magentoDbIsolation disabled
     *
     * Rolling back previous database mess
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/order_simple_product_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/create_quote_on_eu_website_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory_rollback.php
     * @-magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/source_items_for_simple_on_multi_source_rollback.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/simple_product_rollback.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/websites_with_stores_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stock_source_links_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stocks_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/sources_rollback.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-inventory-source-reservations/ISReservations/Test/Integration/_files/clean_all_reservations.php
     *
     * Filling database
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/sources.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stocks.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stock_source_links.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/websites_with_stores.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-sales-api/Test/_files/stock_website_sales_channels.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/simple_product.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/source_items_for_simple_on_multi_source.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/create_quote_on_eu_website.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/order_simple_product.php
     *
     * @throws
     */
    public function should_correctly_revert_uncaptured_processing_order_with_return_to_stock_at_cancel_with_cleanup(): void
    {
        // Have order placed with qty:3 on simple.

        // Test the following scenario: order is placed, sourced-assigned and then cancelled before being shipped (and
        // thus items never left the source) and before being paid (authorised, but not captured).
        // Source reservations should be nullified and stock reservations should be refunded.

        self::assertEquals(-3, $this->getStockReservationsQuantity->execute('simple', 10));

        // Have an invoiced order
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('increment_id', 'created_order_for_test')->create();
        /** @var Order $order */
        $order = current($this->orderRepository->getList($searchCriteria)->getItems());

        // Set order to 'processing' without invoice (authorise without capture)
        $order->setStatus(Order::STATE_PROCESSING);
        $order->setState(Order::STATE_PROCESSING);
        $this->orderRepository->save($order);

        // Assign order to sources: stock reservation moved to source
        $this->moveReservationsFromStockToSource->execute(
            (int) $order->getEntityId(),
            $this->getDefaultSourceSelectionAlgorithmCode->execute()
        );

        $this->cleanupReservations->execute();

        self::assertEquals(0, $this->getStockReservationsQuantity->execute('simple', 10));
        self::assertEquals(-3, $this->getReservationsQuantityList->execute(['simple'])['simple']['quantity']);

        // Cancel order
        $order->cancel();
        $this->orderRepository->save($order);

        self::assertEquals(0, $this->getStockReservationsQuantity->execute('simple', 10));
        self::assertEquals(0, $this->getReservationsQuantityList->execute(['simple'])['simple']['quantity']);
    }

    /**
     *
     * @test
     *
     * @covers \ReachDigital\IOSReservations\Observer\MagentoInventorySales\CancelOrderItemObserver
     *
     * @magentoDbIsolation disabled
     *
     * Rolling back previous database mess
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/order_simple_product_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/create_quote_on_eu_website_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory_rollback.php
     * @-magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/source_items_for_simple_on_multi_source_rollback.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/simple_product_rollback.php
     * @-magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/websites_with_stores_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stock_source_links_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stocks_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/sources_rollback.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-inventory-source-reservations/ISReservations/Test/Integration/_files/clean_all_reservations.php
     *
     * Filling database
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/sources.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stocks.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stock_source_links.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/websites_with_stores.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-sales-api/Test/_files/stock_website_sales_channels.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/simple_product.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/source_items_for_simple_on_multi_source.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/create_quote_on_eu_website.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/order_simple_product.php
     *
     * @throws
     */
    public function should_correctly_revert_unauthorized_order_with_return_to_stock_at_cancel(): void
    {
        // Test the following scenario: order is placed, sourced-assigned and then cancelled before being shipped (and
        // thus items never left the source) and before being paid (neither authorised nor captured).
        // Source reservations should be nullified and stock reservations should not be affected at all.

        // Have a pending order
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('increment_id', 'created_order_for_test')->create();
        /** @var Order $order */
        $order = current($this->orderRepository->getList($searchCriteria)->getItems());

        $initialSourceQty = $this->getSummedSourceQty('simple');

        // Cancel order
        $order->cancel();
        $this->orderRepository->save($order);

        // Assert that:
        // - source qty remains the same
        // - stock reservation qty remains the same
        $currentSourceQty = $this->getSummedSourceQty('simple');
        $currentStockReservationQty = $this->getStockReservationsQuantity->execute('simple', 10);

        self::assertEquals($initialSourceQty, $currentSourceQty, 'Source qty');
        self::assertEquals(0, $currentStockReservationQty, 'Stock reservation');
    }

    /**
     * @test
     *
     * @covers \ReachDigital\IOSReservations\Observer\MagentoInventorySales\CancelOrderItemObserver
     *
     * @magentoDbIsolation disabled
     *
     * Rolling back previous database mess
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/order_simple_product_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/create_quote_on_eu_website_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory_rollback.php
     * @-magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/source_items_for_simple_on_multi_source_rollback.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/simple_product_rollback.php
     * @-magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/websites_with_stores_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stock_source_links_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stocks_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/sources_rollback.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-inventory-source-reservations/ISReservations/Test/Integration/_files/clean_all_reservations.php
     *
     * Filling database
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/sources.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stocks.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stock_source_links.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/websites_with_stores.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-sales-api/Test/_files/stock_website_sales_channels.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/simple_product.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/source_items_for_simple_on_multi_source.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/create_quote_on_eu_website.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/order_simple_product.php
     */
    public function should_correctly_revert_partially_sourced_order_at_source_and_stock()
    {
        // Have order placed with simple product qty 3

        // Reservation has been made for the order
        self::assertEquals(-3, $this->getStockReservationsQuantity->execute('simple', 10));

        $searchCriteria = $this->searchCriteriaBuilder->addFilter('increment_id', 'created_order_for_test')->create();
        $order = current($this->orderRepository->getList($searchCriteria)->getItems());

        // Set order to 'processing' without invoice (authorise without capture)
        $order->setStatus(Order::STATE_PROCESSING);
        $order->setState(Order::STATE_PROCESSING);
        $this->orderRepository->save($order);

        // The actual source suddenly has less available
        $item = $this->getSourceItem('simple', 'eu-1');
        $item->setQuantity(1);
        $item2 = $this->getSourceItem('simple', 'eu-2');
        $item2->setQuantity(1);
        $this->sourceItemsSave->execute([$item, $item2]);

        // Run the SSA algorithm, with 2 results
        $this->moveReservationsFromStockToSource->execute(
            (int) $order->getEntityId(),
            $this->getDefaultSourceSelectionAlgorithmCode->execute()
        );

        self::assertEquals(-1, $this->getStockReservationsQuantity->execute('simple', 10));
        self::assertEquals(-2, $this->getReservationsQuantityList->execute(['simple'])['simple']['quantity']);

        // Cancel order
        $order->cancel();
        $this->orderRepository->save($order);

        self::assertEquals(0, $this->getStockReservationsQuantity->execute('simple', 10));
        self::assertEquals(0, $this->getReservationsQuantityList->execute(['simple'])['simple']['quantity']);
    }

    /**
     * @test
     *
     * @covers \ReachDigital\IOSReservations\Observer\MagentoInventorySales\CancelOrderItemObserver
     *
     * @magentoDbIsolation disabled
     *
     * Rolling back previous database mess
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/order_simple_product_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/create_quote_on_eu_website_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory_rollback.php
     * @-magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/source_items_for_simple_on_multi_source_rollback.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/simple_product_rollback.php
     * @-magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/websites_with_stores_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stock_source_links_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stocks_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/sources_rollback.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-inventory-source-reservations/ISReservations/Test/Integration/_files/clean_all_reservations.php
     *
     * Filling database
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/sources.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stocks.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stock_source_links.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/websites_with_stores.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-sales-api/Test/_files/stock_website_sales_channels.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/simple_product.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/source_items_for_simple_on_multi_source.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/create_quote_on_eu_website.php
     * @magentoDataFixture ../../../../vendor//reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/order_two_simple_products.php
     */
    public function should_revert_source_only_once_with_multiple_items()
    {
        // Reservation has been made for the order
        self::assertEquals(-3, $this->getStockReservationsQuantity->execute('simple', 10));
        self::assertEquals(-2, $this->getStockReservationsQuantity->execute('simple2', 10));

        $searchCriteria = $this->searchCriteriaBuilder->addFilter('increment_id', 'created_order_for_test')->create();
        $order = current($this->orderRepository->getList($searchCriteria)->getItems());

        // Set order to 'processing' without invoice (authorise without capture)
        $order->setStatus(Order::STATE_PROCESSING);
        $order->setState(Order::STATE_PROCESSING);
        $this->orderRepository->save($order);

        // The actual source suddenly has less available
        $item = $this->getSourceItem('simple', 'eu-1');
        $item->setQuantity(1);
        $item2 = $this->getSourceItem('simple', 'eu-2');
        $item2->setQuantity(1);
        $this->sourceItemsSave->execute([$item, $item2]);

        // Run the SSA algorithm, with 2 results
        $this->moveReservationsFromStockToSource->execute(
            (int) $order->getEntityId(),
            $this->getDefaultSourceSelectionAlgorithmCode->execute()
        );

        self::assertEquals(-1, $this->getStockReservationsQuantity->execute('simple', 10));
        self::assertEquals(-2, $this->getReservationsQuantityList->execute(['simple'])['simple']['quantity']);
        self::assertEquals(0, $this->getStockReservationsQuantity->execute('simple2', 10));
        self::assertEquals(-2, $this->getReservationsQuantityList->execute(['simple2'])['simple2']['quantity']);

        $order->cancel();
        $this->orderRepository->save($order);

        self::assertEquals(0, $this->getReservationsQuantityList->execute(['simple'])['simple']['quantity']);
        self::assertEquals(0, $this->getReservationsQuantityList->execute(['simple2'])['simple2']['quantity']);
        self::assertEquals(0, $this->getStockReservationsQuantity->execute('simple', 10));
        self::assertEquals(0, $this->getStockReservationsQuantity->execute('simple2', 10));

        // Make sure we only have 6 rows in the inventory_source_reservation table
        $sourceReservations = $this->getReservationsByMetadata->execute(
            $this->encodeMetaData->execute(['order' => $order->getEntityId()])
        );
        self::assertCount(6, $sourceReservations);
    }

    private function getSummedSourceQty(string $sku): float
    {
        $sourceQty = 0;
        /** @var SourceItemInterface[] $items */
        $items = $this->getSourceItemsBySku->execute($sku);
        foreach ($items as $item) {
            $sourceQty += $item->getQuantity();
        }
        return $sourceQty;
    }

    private function getSourceItem(string $sku, string $sourceCode): SourceItemInterface
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(SourceItemInterface::SKU, $sku)
            ->addFilter(SourceItemInterface::SOURCE_CODE, $sourceCode)
            ->create();

        $items = $this->sourceItemRepository->getList($searchCriteria)->getItems();
        return array_pop($items);
    }
}
