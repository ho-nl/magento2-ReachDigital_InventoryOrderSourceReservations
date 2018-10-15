<?php
declare(strict_types=1);
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\IOSReservations\Test\Integration\Plugin\InventorySales;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\InventoryIndexer\Indexer\SourceItem\SourceItemIndexer;
use Magento\InventoryReservations\Model\ResourceModel\GetReservationsQuantity;
use Magento\InventorySales\Model\GetProductSalableQty;
use Magento\InventorySourceDeductionApi\Model\GetSourceItemBySourceCodeAndSku;
use Magento\InventorySourceSelectionApi\Api\GetDefaultSourceSelectionAlgorithmCodeInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\ShipmentCreationArgumentsInterface;
use Magento\Sales\Api\InvoiceOrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipOrderInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use ReachDigital\IOSReservations\Model\GetOrderSourceReservations;
use ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSource;
use ReachDigital\IOSReservations\Model\SourceReservationResult\SourceReservationResultItem;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultInterface;
use ReachDigital\ISReservations\Model\MetaData\DecodeMetaData;
use \Magento\Sales\Api\Data\ShipmentCreationArgumentsExtensionInterfaceFactory;
use ReachDigital\ISReservationsApi\Model\GetSourceReservationsQuantityInterface;

class MoveShipmentStockNullificationToSourceTest extends TestCase
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

    /** @var GetOrderSourceReservations */
    private $getOrderSourceReservations;

    /** @var ShipOrderInterface */
    private $shipOrder;

    /** @var GetProductSalableQty */
    private $getProductSalableQty;

    /** @var DecodeMetaData */
    private $decodeMetaData;

    /** @var \Magento\Sales\Model\Convert\Order */
    private $orderConverter;

    /** @var ShipmentCreationArgumentsInterface */
    private $shipmentCreationArguments;

    /** @var ShipmentCreationArgumentsExtensionInterfaceFactory */
    private $shipmentCreationArgumentsExtensionInterfaceFactory;

    /** @var GetSourceItemBySourceCodeAndSku */
    private $getSourceItemBySourceCodeAndSku;

    /** @var GetReservationsQuantity */
    private $getReservationsQuantity;

    /** @var GetSourceReservationsQuantityInterface */
    private $getSourceReservationsQuantity;

    protected function setUp()
    {
        $this->searchCriteriaBuilder = Bootstrap::getObjectManager()->get(SearchCriteriaBuilder::class);
        $this->orderRepository = Bootstrap::getObjectManager()->get(OrderRepositoryInterface::class);
        $this->invoiceOrder = Bootstrap::getObjectManager()->get(InvoiceOrderInterface::class);
        $this->moveReservationsFromStockToSource = Bootstrap::getObjectManager()->get(MoveReservationsFromStockToSource::class);
        $this->getDefaultSourceSelectionAlgorithmCode = Bootstrap::getObjectManager()->get(GetDefaultSourceSelectionAlgorithmCodeInterface::class);
        $this->getOrderSourceReservations = Bootstrap::getObjectManager()->get(GetOrderSourceReservations::class);
        $this->shipOrder = Bootstrap::getObjectManager()->get(ShipOrderInterface::class);
        $this->getProductSalableQty = Bootstrap::getObjectManager()->get(GetProductSalableQty::class);
        $this->decodeMetaData = Bootstrap::getObjectManager()->get(DecodeMetaData::class);
        $this->orderConverter = Bootstrap::getObjectManager()->get(\Magento\Sales\Model\Convert\Order::class);
        $this->shipmentCreationArguments = Bootstrap::getObjectManager()->get(ShipmentCreationArgumentsInterface::class);
        $this->shipmentCreationArgumentsExtensionInterfaceFactory = Bootstrap::getObjectManager()->get(ShipmentCreationArgumentsExtensionInterfaceFactory::class);
        $this->getSourceItemBySourceCodeAndSku = Bootstrap::getObjectManager()->get(GetSourceItemBySourceCodeAndSku::class);
        $this->getReservationsQuantity = Bootstrap::getObjectManager()->get(GetReservationsQuantity::class);
        $this->getSourceReservationsQuantity = Bootstrap::getObjectManager()->get(GetSourceReservationsQuantityInterface::class);
    }

    /**
     * @test
     *
     * @covers \ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSource
     *
     * @magentoDbIsolation disabled
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
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function should_nullify_the_source_instead_of_the_stock() : void
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', 'created_order_for_test')
            ->create();
        /** @var Order $order */
        $order = current($this->orderRepository->getList($searchCriteria)->getItems());

        //Create Invoice
        $this->invoiceOrder->execute($order->getEntityId());


        // Trigger initial reindex (else no stock index tables exist)
        /** @var SourceItemIndexer $indexer */
        $indexer = Bootstrap::getObjectManager()->get(SourceItemIndexer::class);
        $indexer->executeFull();

        $salableQty = $this->getProductSalableQty->execute('simple', 10);
        self::assertEquals(11, $salableQty);

        // Move reservation to source, salable qty should remain the same. Actual source quantity should not be affected yet
        $initialStockReservationsQty = $this->getReservationsQuantity->execute('simple', 10);
        $sourceSelectionResult = $this->moveReservationsFromStockToSource->execute(
            (int) $order->getEntityId(),
            $this->getDefaultSourceSelectionAlgorithmCode->execute()
        );
        $initialSourcesQty = $this->getCombinedSourcesQty('simple', $sourceSelectionResult);

        $salableQty = $this->getProductSalableQty->execute('simple', 10);
        self::assertEquals(11, $salableQty);

        // The qty should now be nullified from the stock reservations
        $currentStockReservationsQty = $this->getReservationsQuantity->execute('simple', 10);
        self::assertEquals($initialStockReservationsQty + 3, $currentStockReservationsQty);

        // Create shipments for the orders' assigned sources
        $sourceReservations = $this->getOrderSourceReservations->execute((int) $order->getEntityId());
        $initialSourceReservationsQty = [];
        foreach ($sourceReservations->getReservationItems() as $reservationItem) {
            $sourceCode = $reservationItem->getReservation()->getSourceCode();
            $initialSourceReservationsQty[$sourceCode] = $this->getSourceReservationsQuantity->execute('simple', $sourceCode);
        }
        $initialSourceReservationsQty = array_sum($initialSourceReservationsQty);

        $reservationsPerSource = [];
        foreach ($sourceReservations->getReservationItems() as $reservationItem) {
            $sourceCode = $reservationItem->getReservation()->getSourceCode();
            isset($reservationsPerSource[$sourceCode]) ?
                $reservationsPerSource[$sourceCode][] = $reservationItem:
                $reservationsPerSource[$sourceCode] = [$reservationItem];
        }

        foreach ($reservationsPerSource as $sourceCode => $items) {
            /** @var SourceReservationResultItem[] $items */
            /** @var SourceReservationResultItem $item */
            $shipmentItems = [];
            foreach ($items as $item) {
                $shipmentItems[] = $this->orderConverter
                    ->itemToShipmentItem($order->getItemById($item->getOrderItemId()))
                    ->setQty($item->getReservation()->getQuantity() * -1);
            }

            if ($this->shipmentCreationArguments->getExtensionAttributes() === null) {
                $this->shipmentCreationArguments->setExtensionAttributes($this->shipmentCreationArgumentsExtensionInterfaceFactory->create());
            }

            $this->shipmentCreationArguments->getExtensionAttributes()->setSourceCode($sourceCode);
            $this->shipOrder->execute(
                $sourceReservations->getOrderId(),
                $shipmentItems,
                false,
                false,
                null,
                [],
                [],
                $this->shipmentCreationArguments
            );
        }

        // The qty should now be reduced from the actual source qty
        $currentSourcesQty = $this->getCombinedSourcesQty('simple', $sourceSelectionResult);
        self::assertEquals($currentSourcesQty, $initialSourcesQty - 3);

        // The qty should be nullified in the source reservations
        $currentSourceReservationsQty = [];
        foreach ($sourceReservations->getReservationItems() as $reservationItem) {
            $sourceCode = $reservationItem->getReservation()->getSourceCode();
            $currentSourceReservationsQty[$sourceCode] = $this->getSourceReservationsQuantity->execute('simple', $sourceCode);
        }
        $currentSourceReservationsQty = array_sum($currentSourceReservationsQty);
        self::assertEquals($initialSourceReservationsQty + 3, $currentSourceReservationsQty);

        $salableQty = $this->getProductSalableQty->execute('simple', 10);
        self::assertEquals(11, $salableQty);
    }

    /**
     * Obtain actual qtys of sources present in the reservation result, for given SKU.
     *
     * @param string                           $sku
     * @param SourceReservationResultInterface $reservationResult
     *
     * @return float
     */
    public function getCombinedSourcesQty(string $sku, SourceReservationResultInterface $reservationResult): float
    {
        $qty = 0;

        foreach ($reservationResult->getReservationItems() as $item) {
            $sourceCode = $item->getReservation()->getSourceCode();
            $sourceItem = $this->getSourceItemBySourceCodeAndSku->execute($sourceCode, $sku);
            $qty += $sourceItem->getQuantity();
        }

        return $qty;
    }
}
