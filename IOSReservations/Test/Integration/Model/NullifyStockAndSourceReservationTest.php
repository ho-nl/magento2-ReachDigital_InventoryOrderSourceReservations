<?php

namespace ReachDigital\IOSReservations\Test\Integration\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Inventory\Model\SourceItem\Command\GetSourceItemsBySku;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemRepositoryInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryReservations\Model\ResourceModel\GetReservationsQuantity;
use Magento\InventoryReservationsApi\Model\GetReservationsQuantityInterface;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterfaceFactory;
use Magento\InventorySourceSelection\Model\GetDefaultSourceSelectionAlgorithmCode;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use ReachDigital\IOSReservationsApi\Api\MoveReservationsFromStockToSourceInterface;
use ReachDigital\IOSReservationsApi\Api\NullifyStockAndSourceReservationsInterface;
use ReachDigital\ISReservations\Model\ResourceModel\GetReservationsQuantityList;

class NullifyStockAndSourceReservationTest extends TestCase
{
    /** @var GetReservationsQuantityList */
    private $getSourceReservationsQuantityList;

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

    /** @var SourceItemRepositoryInterface  */
    private $sourceItemRepository;

    /** @var SourceItemsSaveInterface */
    private $sourceItemsSave;

    /**
     * @var NullifyStockAndSourceReservationsInterface
     */
    private $nullifyStockAndSourceReservations;
    /**
     * @var ItemToSellInterfaceFactory
     */
    private $itemToCancelFactory;

    public function setUp()
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->addSharedInstance(
            $objectManager->get(GetReservationsQuantity::class),
            GetReservationsQuantityInterface::class
        );

        $this->getSourceReservationsQuantityList = $objectManager->get(GetReservationsQuantityList::class);
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
        $this->sourceItemRepository = $objectManager->get(SourceItemRepositoryInterface::class);
        $this->sourceItemsSave = $objectManager->get(SourceItemsSaveInterface::class);
        $this->nullifyStockAndSourceReservations = $objectManager->get(
            NullifyStockAndSourceReservationsInterface::class
        );
        $this->itemToCancelFactory = $objectManager->get(ItemToSellInterfaceFactory::class);
    }

    /**
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
     * @magentoDataFixture ../../../../vendor//reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/order_two_simple_products.php
     */
    public function should_partially_cancel_order_item()
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
        self::assertEquals(-2, $this->getSourceReservationsQuantityList->execute(['simple'])['simple']['quantity']);
        self::assertEquals(0, $this->getStockReservationsQuantity->execute('simple2', 10));
        self::assertEquals(-2, $this->getSourceReservationsQuantityList->execute(['simple2'])['simple2']['quantity']);

        $result = $this->nullifyStockAndSourceReservations->execute((int) $order->getEntityId(), [
            $this->itemToCancelFactory->create(['sku' => 'simple', 'qty' => 1]),
            $this->itemToCancelFactory->create(['sku' => 'simple2', 'qty' => 1]),
        ]);
        self::assertEmpty($result);
        foreach ($order->getItems() as $item) {
            $item->setQtyCanceled(1);
        }
        $this->orderRepository->save($order);

        // If order item gets cancelled and still has stock reservations it should cancel the stock reservation first
        self::assertEquals(0, $this->getStockReservationsQuantity->execute('simple', 10));
        self::assertEquals(-2, $this->getSourceReservationsQuantityList->execute(['simple'])['simple']['quantity']);
        self::assertEquals(0, $this->getStockReservationsQuantity->execute('simple2', 10));
        self::assertEquals(-1, $this->getSourceReservationsQuantityList->execute(['simple2'])['simple2']['quantity']);

        // Completely cancel the order.
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('increment_id', 'created_order_for_test')->create();
        $order = current($this->orderRepository->getList($searchCriteria)->getItems());
        $order->cancel();
        $this->orderRepository->save($order);

        self::assertEquals(0, $this->getStockReservationsQuantity->execute('simple', 10));
        self::assertEquals(0, $this->getSourceReservationsQuantityList->execute(['simple'])['simple']['quantity']);
        self::assertEquals(0, $this->getStockReservationsQuantity->execute('simple2', 10));
        self::assertEquals(0, $this->getSourceReservationsQuantityList->execute(['simple2'])['simple2']['quantity']);
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
