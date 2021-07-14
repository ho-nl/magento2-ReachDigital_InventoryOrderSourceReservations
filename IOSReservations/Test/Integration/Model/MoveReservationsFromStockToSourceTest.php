<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Test\Integration\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemRepositoryInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryReservations\Model\ResourceModel\GetReservationsQuantity;
use Magento\InventoryReservationsApi\Model\GetReservationsQuantityInterface;
use Magento\InventorySales\Model\GetProductSalableQty;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySourceSelectionApi\Api\GetDefaultSourceSelectionAlgorithmCodeInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceOrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSource;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultInterface;
use ReachDigital\IOSReservationsApi\Exception\CouldNotCreateSourceSelectionRequestFromOrder;
use ReachDigital\ISReservationsApi\Model\GetSourceReservationsQuantityInterface;

class MoveReservationsFromStockToSourceTest extends TestCase
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

    /** @var GetReservationsQuantity */
    private $getStockReservationsQuantity;

    /** @var GetSourceReservationsQuantityInterface */
    private $getSourceReservationsQuantity;
    /**
     * @var SourceItemRepositoryInterface
     */
    private $sourceItemRepository;
    /**
     * @var SourceItemsSaveInterface
     */
    private $sourceItemSave;

    protected function setUp()
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->addSharedInstance(
            $objectManager->get(GetReservationsQuantity::class),
            GetReservationsQuantityInterface::class
        );

        $this->searchCriteriaBuilder = Bootstrap::getObjectManager()->get(SearchCriteriaBuilder::class);

        $this->orderRepository = $objectManager->get(OrderRepositoryInterface::class);
        $this->invoiceOrder = $objectManager->get(InvoiceOrderInterface::class);
        $this->moveReservationsFromStockToSource = $objectManager->get(MoveReservationsFromStockToSource::class);
        $this->getDefaultSourceSelectionAlgorithmCode = $objectManager->get(
            GetDefaultSourceSelectionAlgorithmCodeInterface::class
        );
        $this->getProductSalableQty = $objectManager->get(GetProductSalableQtyInterface::class);
        $this->getStockReservationsQuantity = $objectManager->get(GetReservationsQuantity::class);
        $this->getSourceReservationsQuantity = $objectManager->get(GetSourceReservationsQuantityInterface::class);
        $this->sourceItemRepository = $objectManager->get(SourceItemRepositoryInterface::class);
        $this->sourceItemSave = $objectManager->get(SourceItemsSaveInterface::class);
    }

    /**
     * @test
     *
     * @covers \ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSource
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
     * @throws LocalizedException
     */
    public function should_move_reservation_from_stock_to_source(): void
    {
        $srq = $this->getSourceReservationsQuantity;

        //There are 14 actually available in the source, order is placed with three.
        self::assertEquals(11, $this->getProductSalableQty->execute('simple', 10));
        self::assertEquals(-3, $this->getStockReservationsQuantity->execute('simple', 10));
        self::assertEquals(0, $srq->execute('simple', 'eu-1') + $srq->execute('simple', 'eu-2'));

        $searchCriteria = $this->searchCriteriaBuilder->addFilter('increment_id', 'created_order_for_test')->create();
        /** @var OrderInterface $order */
        $order = current($this->orderRepository->getList($searchCriteria)->getItems());
        $this->invoiceOrder->execute($order->getEntityId());

        self::assertEquals(11, $this->getProductSalableQty->execute('simple', 10));
        self::assertEquals(-3, $this->getStockReservationsQuantity->execute('simple', 10));
        self::assertEquals(0, $srq->execute('simple', 'eu-1') + $srq->execute('simple', 'eu-2'));

        $this->moveReservationsFromStockToSource->execute(
            (int) $order->getEntityId(),
            $this->getDefaultSourceSelectionAlgorithmCode->execute()
        );

        self::assertEquals(11, $this->getProductSalableQty->execute('simple', 10));
        self::assertEquals(0, $this->getStockReservationsQuantity->execute('simple', 10));
        self::assertEquals(-3, $srq->execute('simple', 'eu-1') + $srq->execute('simple', 'eu-2'));
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
     * @throws LocalizedException
     */
    public function should_not_move_reservations_if_already_moved(): void
    {
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('increment_id', 'created_order_for_test')->create();
        /** @var OrderInterface $order */
        $order = current($this->orderRepository->getList($searchCriteria)->getItems());
        $this->invoiceOrder->execute($order->getEntityId());

        $this->moveReservationsFromStockToSource->execute(
            (int) $order->getEntityId(),
            $this->getDefaultSourceSelectionAlgorithmCode->execute()
        );

        $this->expectException(CouldNotCreateSourceSelectionRequestFromOrder::class);
        $this->moveReservationsFromStockToSource->execute(
            (int) $order->getEntityId(),
            $this->getDefaultSourceSelectionAlgorithmCode->execute()
        );
    }

    /**
     * @test
     *
     * @covers \ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSource
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
     * @throws LocalizedException
     */
    public function should_partially_reserve_items_when_item_is_oversold()
    {
        // Have order placed with simple product qty 3
        // Reservation has been made for the order
        self::assertEquals(-3, $this->getStockReservationsQuantity->execute('simple', 10));
        self::assertEquals(11, $this->getProductSalableQty->execute('simple', 10));

        // Invoicing doesn't make a difference
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('increment_id', 'created_order_for_test')->create();
        /** @var OrderInterface $order */
        $order = current($this->orderRepository->getList($searchCriteria)->getItems());
        $this->invoiceOrder->execute($order->getEntityId());

        self::assertEquals(-3, $this->getStockReservationsQuantity->execute('simple', 10));

        // The actual source suddenly has less available
        $item = $this->getSourceItem('simple', 'eu-1');
        $item->setQuantity(1);
        $item2 = $this->getSourceItem('simple', 'eu-2');
        $item2->setQuantity(1);
        $this->sourceItemSave->execute([$item, $item2]);

        // Run the SSA algorithm, with 2 results
        $result = $this->moveReservationsFromStockToSource->execute(
            (int) $order->getEntityId(),
            $this->getDefaultSourceSelectionAlgorithmCode->execute()
        );
        self::assertEquals(-2, $this->getReservationResultCount($result));
        self::assertEquals(-1, $this->getStockReservationsQuantity->execute('simple', 10));
        self::assertEquals(-1, $this->getSourceReservationsQuantity->execute('simple', 'eu-1'));
        self::assertEquals(-1, $this->getSourceReservationsQuantity->execute('simple', 'eu-2'));

        // Run the SSA a second time, with no result
        $result = $this->moveReservationsFromStockToSource->execute(
            (int) $order->getEntityId(),
            $this->getDefaultSourceSelectionAlgorithmCode->execute()
        );

        self::assertEquals(0, $this->getReservationResultCount($result));
        self::assertEquals(-1, $this->getStockReservationsQuantity->execute('simple', 10));
        self::assertEquals(-1, $this->getSourceReservationsQuantity->execute('simple', 'eu-1'));
        self::assertEquals(-1, $this->getSourceReservationsQuantity->execute('simple', 'eu-2'));

        // Inventory becomes available
        $item = $this->getSourceItem('simple', 'eu-2');
        $item->setQuantity(10);
        $this->sourceItemSave->execute([$item]);

        // Run the SSA a third time, with 1 result
        $result = $this->moveReservationsFromStockToSource->execute(
            (int) $order->getEntityId(),
            $this->getDefaultSourceSelectionAlgorithmCode->execute()
        );
        self::assertEquals(-1, $this->getReservationResultCount($result));
        self::assertEquals(0, $this->getStockReservationsQuantity->execute('simple', 10));
        self::assertEquals(-1, $this->getSourceReservationsQuantity->execute('simple', 'eu-1'));
        self::assertEquals(-2, $this->getSourceReservationsQuantity->execute('simple', 'eu-2'));
    }

    private function getReservationResultCount(SourceReservationResultInterface $result): float
    {
        $totalQty = 0;
        foreach ($result->getReservationItems() as $item) {
            $totalQty += $item->getReservation()->getQuantity();
        }
        return $totalQty;
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
