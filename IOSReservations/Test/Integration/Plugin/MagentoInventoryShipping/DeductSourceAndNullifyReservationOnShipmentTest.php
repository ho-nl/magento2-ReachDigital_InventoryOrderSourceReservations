<?php
declare(strict_types=1);
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\IOSReservations\Test\Integration\Plugin\MagentoInventoryShipping;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryReservations\Model\ResourceModel\GetReservationsQuantity;
use Magento\InventorySales\Model\GetProductSalableQty;
use Magento\InventorySourceDeductionApi\Model\GetSourceItemBySourceCodeAndSku;
use Magento\InventorySourceSelectionApi\Api\GetDefaultSourceSelectionAlgorithmCodeInterface;
use Magento\Sales\Api\Data\ShipmentCreationArgumentsInterface;
use Magento\Sales\Api\InvoiceOrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipOrderInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use ReachDigital\IOSReservations\Model\GetOrderSourceReservations;
use ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSource;
use ReachDigital\IOSReservations\Model\SourceReservationResult\SourceReservationResultItem;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultInterface;
use Magento\Sales\Api\Data\ShipmentCreationArgumentsExtensionInterfaceFactory;
use ReachDigital\ISReservationsApi\Model\GetSourceReservationsQuantityInterface;

class DeductSourceAndNullifyReservationOnShipmentTest extends TestCase
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

    /** @var \Magento\Sales\Model\Convert\Order */
    private $orderConverter;

    /** @var ShipmentCreationArgumentsInterface */
    private $shipmentCreationArguments;

    /** @var ShipmentCreationArgumentsExtensionInterfaceFactory */
    private $shipmentCreationArgumentsExtensionInterfaceFactory;

    /** @var GetSourceItemBySourceCodeAndSku */
    private $getSourceItemBySourceCodeAndSku;

    /** @var GetReservationsQuantity */
    private $getStockReservationsQuantity;

    /** @var GetSourceReservationsQuantityInterface */
    private $getSourceReservationsQuantity;

    protected function setUp()
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        //        $objectManager->addSharedInstance($objectManager->get(GetReservationsQuantity::class), 'wtf');
        //        $objectManager->addSharedInstance($this->get(GetStockItemData::class), GetStockItemDataCache::class);

        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $this->orderRepository = $objectManager->get(OrderRepositoryInterface::class);
        $this->invoiceOrder = $objectManager->get(InvoiceOrderInterface::class);
        $this->moveReservationsFromStockToSource = $objectManager->get(MoveReservationsFromStockToSource::class);
        $this->getDefaultSourceSelectionAlgorithmCode = $objectManager->get(
            GetDefaultSourceSelectionAlgorithmCodeInterface::class
        );
        $this->getOrderSourceReservations = $objectManager->get(GetOrderSourceReservations::class);
        $this->shipOrder = $objectManager->get(ShipOrderInterface::class);
        $this->getProductSalableQty = $objectManager->create(GetProductSalableQty::class);

        $this->orderConverter = $objectManager->get(\Magento\Sales\Model\Convert\Order::class);
        $this->shipmentCreationArguments = $objectManager->get(ShipmentCreationArgumentsInterface::class);
        $this->shipmentCreationArgumentsExtensionInterfaceFactory = $objectManager->get(
            ShipmentCreationArgumentsExtensionInterfaceFactory::class
        );
        $this->getSourceItemBySourceCodeAndSku = $objectManager->get(GetSourceItemBySourceCodeAndSku::class);
        $this->getStockReservationsQuantity = $objectManager->get(GetReservationsQuantity::class);
        $this->getSourceReservationsQuantity = $objectManager->get(GetSourceReservationsQuantityInterface::class);
    }

    /**
     * @test
     *
     * @covers \ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSource
     *
     * @magentoDbIsolation disabled
     *
     * Rolling back previous database mess
     *
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
    public function should_nullify_the_source_instead_of_the_stock(): void
    {
        $srq = $this->getSourceReservationsQuantity;
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('increment_id', 'created_order_for_test')->create();
        /** @var Order $order */
        $order = current($this->orderRepository->getList($searchCriteria)->getItems());

        // Create Invoice
        $this->invoiceOrder->execute($order->getEntityId());

        // Initial salableQty should be 11; initial qty of 14 (eu1 and eu2 sources, the other EU sources are either
        // disabled or marked out of stock) minus the 3 ordered
        self::assertEquals(11, $this->getProductSalableQty->execute('simple', 10));
        self::assertEquals(-3, $this->getStockReservationsQuantity->execute('simple', 10));
        self::assertEquals(0, $srq->execute('simple', 'eu-1') + $srq->execute('simple', 'eu-2'));

        // Move reservation to source, salable qty should remain the same. Actual source quantity should not be affected yet
        $initialStockReservationsQty = $this->getStockReservationsQuantity->execute('simple', 10);
        $sourceSelectionResult = $this->moveReservationsFromStockToSource->execute(
            (int) $order->getEntityId(),
            $this->getDefaultSourceSelectionAlgorithmCode->execute()
        );
        $initialSourcesQty = $this->getCombinedSourcesQty('simple', $sourceSelectionResult);

        self::assertEquals(11, $this->getProductSalableQty->execute('simple', 10));
        self::assertEquals(0, $this->getStockReservationsQuantity->execute('simple', 10));
        self::assertEquals(-3, $srq->execute('simple', 'eu-1') + $srq->execute('simple', 'eu-2'));

        // The qty should now be nullified from the stock reservations
        $currentStockReservationsQty = $this->getStockReservationsQuantity->execute('simple', 10);
        self::assertEquals($initialStockReservationsQty + 3, $currentStockReservationsQty);

        // Create shipments for the orders' assigned sources
        $sourceReservations = $this->getOrderSourceReservations->execute((int) $order->getEntityId());
        $initialSourceReservationsQty = [];
        foreach ($sourceReservations->getReservationItems() as $reservationItem) {
            $sourceCode = $reservationItem->getReservation()->getSourceCode();
            $initialSourceReservationsQty[$sourceCode] = $this->getSourceReservationsQuantity->execute(
                'simple',
                $sourceCode
            );
        }
        $initialSourceReservationsQty = array_sum($initialSourceReservationsQty);

        $reservationsPerSource = [];
        foreach ($sourceReservations->getReservationItems() as $reservationItem) {
            $sourceCode = $reservationItem->getReservation()->getSourceCode();
            isset($reservationsPerSource[$sourceCode])
                ? ($reservationsPerSource[$sourceCode][] = $reservationItem)
                : ($reservationsPerSource[$sourceCode] = [$reservationItem]);
        }

        foreach ($reservationsPerSource as $sourceCode => $items) {
            /** @var SourceReservationResultItem[] $items */
            /** @var SourceReservationResultItem $item */
            $shipmentItems = [];
            foreach ($items as $item) {
                /** @noinspection PhpParamsInspection */
                $shipmentItems[] = $this->orderConverter
                    ->itemToShipmentItem($order->getItemById($item->getOrderItemId()))
                    ->setQty($item->getReservation()->getQuantity() * -1);
            }

            if ($this->shipmentCreationArguments->getExtensionAttributes() === null) {
                $this->shipmentCreationArguments->setExtensionAttributes(
                    $this->shipmentCreationArgumentsExtensionInterfaceFactory->create()
                );
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

        // The stock reservation should not have been modified during shipment creation, as this is done when assigning
        // sources
        $currentStockReservationsQty = $this->getStockReservationsQuantity->execute('simple', 10);
        self::assertEquals($initialStockReservationsQty + 3, $currentStockReservationsQty);

        // The qty should now be reduced from the actual source qty
        $currentSourcesQty = $this->getCombinedSourcesQty('simple', $sourceSelectionResult);
        self::assertEquals($currentSourcesQty, $initialSourcesQty - 3);

        $currentSourceReservationsQty = [];
        foreach ($sourceReservations->getReservationItems() as $reservationItem) {
            $sourceCode = $reservationItem->getReservation()->getSourceCode();
            $currentSourceReservationsQty[$sourceCode] = $this->getSourceReservationsQuantity->execute(
                'simple',
                $sourceCode
            );
        }
        $currentSourceReservationsQty = array_sum($currentSourceReservationsQty);
        self::assertEquals($initialSourceReservationsQty + 3, $currentSourceReservationsQty);

        self::assertEquals(11, $this->getProductSalableQty->execute('simple', 10));
        self::assertEquals(0, $this->getStockReservationsQuantity->execute('simple', 10));
        self::assertEquals(0, $srq->execute('simple', 'eu-1') + $srq->execute('simple', 'eu-2'));
    }

    /**
     * Obtain actual qtys of sources present in the reservation result, for given SKU.
     *
     * @param string                           $sku
     * @param SourceReservationResultInterface $reservationResult
     *
     * @return float
     * @throws NoSuchEntityException
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
