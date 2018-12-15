<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Inventory\Model\SourceItem\Command\GetSourceItemsBySku;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryReservations\Model\ResourceModel\GetReservationsQuantity;
use Magento\InventorySourceSelection\Model\GetDefaultSourceSelectionAlgorithmCode;
use Magento\Sales\Api\Data\CreditmemoCreationArgumentsExtensionFactory;
use Magento\Sales\Api\Data\CreditmemoCreationArgumentsInterface;
use Magento\Sales\Api\Data\CreditmemoItemCreationInterface;
use Magento\Sales\Api\Data\ShipmentCreationArgumentsExtensionInterfaceFactory;
use Magento\Sales\Api\Data\ShipmentCreationArgumentsInterface;
use Magento\Sales\Api\Data\ShipmentItemCreationInterfaceFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\RefundOrderInterface;
use Magento\Sales\Api\ShipOrderInterface;
use Magento\Sales\Model\InvoiceOrder;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use ReachDigital\IOSReservations\Model\GetOrderSourceReservations;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultItemInterface;
use ReachDigital\IOSReservationsApi\Api\MoveReservationsFromStockToSourceInterface;
use ReachDigital\ISReservations\Model\ResourceModel\GetReservationsQuantityList;

class RevertSourceReservationsOnCreditBeforeShipmentTest extends \PHPUnit\Framework\TestCase
{
    /** @var GetReservationsQuantityList */
    private $getReservationsQuantityList;

    /** @var InvoiceOrder */
    private $invoiceOrder;

    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var MoveReservationsFromStockToSourceInterface */
    private $moveReservationsFromStockToSource;

    /** @var GetDefaultSourceSelectionAlgorithmCode */
    private $getDefaultSourceSelectionAlgorithmCode;

    /** @var \Magento\Framework\ObjectManagerInterface */
    private $objectManager;

    /** @var GetReservationsQuantity */
    private $getStockReservationsQuantity;

    /** @var GetSourceItemsBySku */
    private $getSourceItemsBySku;

    /** @var ShipmentCreationArgumentsInterface */
    private $shipmentCreationArguments;

    /** @var ShipmentCreationArgumentsExtensionInterfaceFactory */
    private $shipmentCreationArgumentsExtensionFactory;

    public function setUp()
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->getReservationsQuantityList = $objectManager->get(GetReservationsQuantityList::class);
        $this->invoiceOrder = $objectManager->get(InvoiceOrder::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $this->orderRepository = $objectManager->get(OrderRepositoryInterface::class);
        $this->moveReservationsFromStockToSource = $objectManager->get(MoveReservationsFromStockToSourceInterface::class);
        $this->getDefaultSourceSelectionAlgorithmCode = $objectManager->get(GetDefaultSourceSelectionAlgorithmCode::class);
        $this->getStockReservationsQuantity = $objectManager->get(GetReservationsQuantity::class);
        $this->getSourceItemsBySku = $objectManager->get(GetSourceItemsBySku::class);
        $this->shipmentCreationArguments = $objectManager->get(ShipmentCreationArgumentsInterface::class);
        $this->shipmentCreationArgumentsExtensionFactory = $objectManager->get(ShipmentCreationArgumentsExtensionInterfaceFactory::class);
        $this->objectManager = $objectManager;
    }

    /**
     *
     * @test
     *
     * @covers \ReachDigital\IOSReservations\Plugin\MagentoInventorySales\RevertSourceReservationsOnCreditBeforeShipment
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
     *
     * @throws
     */
    public function should_revert_source_reservations_on_credit_before_shipping_if_available(): void
    {
        // Have an invoiced order
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', 'created_order_for_test')
            ->create();
        /** @var Order $order */
        $order = current($this->orderRepository->getList($searchCriteria)->getItems());

        // Create Invoice
        $this->invoiceOrder->execute($order->getEntityId());

        // Assert no reservations
        $result = $this->getReservationsQuantityList->execute(['simple']);
        self::assertCount(0, $result);

        // Assign order to sources
        $this->moveReservationsFromStockToSource->execute(
            (int) $order->getEntityId(),
            $this->getDefaultSourceSelectionAlgorithmCode->execute()
        );

        // Assert reservation qty
        $result = $this->getReservationsQuantityList->execute(['simple']);
        self::assertEquals(-3, $result['simple']['quantity']);

        // Credit order
        $this->creditOrder($order);

        // Assert that source reservations have been reverted by refunded qty
        $result = $this->getReservationsQuantityList->execute(['simple']);
        self::assertEquals(0, $result['simple']['quantity']);
    }


    /**
     *
     * @test
     *
     * @covers \ReachDigital\IOSReservations\Plugin\MagentoInventorySales\RevertSourceReservationsOnCreditBeforeShipment
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
     *
     * @throws
     */
    public function should_revert_source_reservations_on_partial_credit_before_shipping_if_available(): void
    {
        // Have an invoiced order
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', 'created_order_for_test')
            ->create();
        /** @var Order $order */
        $order = current($this->orderRepository->getList($searchCriteria)->getItems());

        // Create Invoice
        $this->invoiceOrder->execute($order->getEntityId());

        // Assert no reservations
        $result = $this->getReservationsQuantityList->execute(['simple']);
        self::assertCount(0, $result);

        // Assign order to sources
        $this->moveReservationsFromStockToSource->execute(
            (int) $order->getEntityId(),
            $this->getDefaultSourceSelectionAlgorithmCode->execute()
        );

        // Assert reservation qty
        $result = $this->getReservationsQuantityList->execute(['simple']);
        self::assertEquals(-3, $result['simple']['quantity']);

        // Partially credit order
        $this->creditOrder($order, 1.0);

        // Assert that source reservations have been reverted by refunded qty
        $result = $this->getReservationsQuantityList->execute(['simple']);
        self::assertEquals(-2, $result['simple']['quantity']);
    }

    /**
     *
     * @test
     *
     * @covers \ReachDigital\IOSReservations\Plugin\MagentoInventorySales\RevertSourceReservationsOnCreditBeforeShipment
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
     *
     * @throws
     */
    public function should_not_return_reservation_reverted_qty_to_source_or_stock_reservation() : void
    {
        // Test that fully crediting an unshipped order does not affect source qtys or stock reservations

        // Have an invoiced order
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', 'created_order_for_test')
            ->create();
        /** @var Order $order */
        $order = current($this->orderRepository->getList($searchCriteria)->getItems());

        // Create Invoice
        $this->invoiceOrder->execute($order->getEntityId());

        // Assign order to sources
        $this->moveReservationsFromStockToSource->execute(
            (int) $order->getEntityId(),
            $this->getDefaultSourceSelectionAlgorithmCode->execute()
        );

        // Obtain initial source qty and stock reservation qty
        $initialStockReservationQty = $this->getStockReservationsQuantity->execute('simple', 10);
        $initialSourceQty = $this->getSummedSourceQty('simple');

        // Fully credit order
        $this->creditOrder($order);

        // Compare current with initial source qty and stock reservation qty
        $currentStockReservationQty = $this->getStockReservationsQuantity->execute('simple', 10);
        $currentSourceQty = $this->getSummedSourceQty('simple');

        self::assertEquals($initialSourceQty, $currentSourceQty);
        self::assertEquals($initialStockReservationQty, $currentStockReservationQty);
    }

    /**
     *
     * @test
     *
     * @covers \ReachDigital\IOSReservations\Plugin\MagentoInventorySales\RevertSourceReservationsOnCreditBeforeShipment
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
     *
     * @throws
     */
    public function should_correctly_revert_partially_shipped_order() : void
    {
        // Test the following scenario: creditmemo created for full qty, but is partially shipped (and should be
        // returned to source) and one wasn't (should be reverted from reservation). Stock reservations should not be
        // affected (this should only change during source-assignment).

        // Have an invoiced order
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', 'created_order_for_test')
            ->create();
        /** @var Order $order */
        $order = current($this->orderRepository->getList($searchCriteria)->getItems());

        // Create Invoice
        $this->invoiceOrder->execute($order->getEntityId());

        // Assign order to sources: stock reservation moved to source
        $this->moveReservationsFromStockToSource->execute(
            (int) $order->getEntityId(),
            $this->getDefaultSourceSelectionAlgorithmCode->execute()
        );

        $initialSourceQty = $this->getSummedSourceQty('simple');
        $initialStockReservationQty = $this->getStockReservationsQuantity->execute('simple', 10);
        $initialSourceReservationQty = $this->getReservationsQuantityList->execute(['simple'])['simple']['quantity'];

        // @todo write test for correct handling of returns without 'back to source' box checked.
        // Partially ship order: deduct shipped qty from source (-1), nullify source reservation (+1)
        $this->shipOrder($order, 'eu-1', 1.0);

        $shippedSourceQty = $this->getSummedSourceQty('simple');
        self::assertEquals($initialSourceQty - 1, $shippedSourceQty);

        // Fully credit order: shipped qty back to source (+1), reserved source qty reverted (+2)
        $this->creditOrder($order);

        // Assert that:
        // - source qty remains the same
        // - source reservation increased by 3
        // - stock reservation qty (should not have changed after source-assignment)
        $currentSourceQty = $this->getSummedSourceQty('simple');
        $currentStockReservationQty = $this->getStockReservationsQuantity->execute('simple', 10);
        $currentSourceReservationQty = $this->getReservationsQuantityList->execute(['simple'])['simple']['quantity'];

        self::assertEquals($initialSourceQty, $currentSourceQty);
        self::assertEquals($initialStockReservationQty, $currentStockReservationQty);
        self::assertEquals($initialSourceReservationQty + 3, $currentSourceReservationQty);
    }

    private function getSummedSourceQty(string $sku) : float
    {
        $sourceQty = 0;
        /** @var SourceItemInterface[] $items */
        $items = $this->getSourceItemsBySku->execute($sku);
        foreach ($items as $item) {
            $sourceQty += $item->getQuantity();
        }
        return $sourceQty;
    }

    /**
     * @param Order $order
     */
    private function creditOrder(Order $order, ?float $overrideQty = null): void
    {
        $refundOrder = $this->objectManager->create(RefundOrderInterface::class);

        $orderItems = [];
        $returnItems = [];
        foreach ($order->getAllItems() as $orderItem) {
            $creditItem = $this->objectManager->create(CreditmemoItemCreationInterface::class);
            $creditItem->setOrderItemId($orderItem->getItemId());
            if ($overrideQty === null) {
                $creditItem->setQty($orderItem->getQtyOrdered());
            } else {
                $creditItem->setQty($overrideQty);
            }
            $orderItems[] = $creditItem;
            $returnItems[] = $orderItem->getItemId();
        }

        $arguments = $this->objectManager->create(CreditmemoCreationArgumentsInterface::class);
        $arguments->setExtensionAttributes(
            $this->objectManager->create(CreditmemoCreationArgumentsExtensionFactory::class)
                ->create());
        $arguments->getExtensionAttributes()->setReturnToStockItems($returnItems);

        $refundOrder->execute(
            $order->getEntityId(),
            $orderItems,
            false,
            false,
            null,
            $arguments
        );
    }

    private function shipOrder(Order $order, string $sourceCode, ?float $overrideQty = null) : void
    {
        $sourceReservations = $this->objectManager->get(GetOrderSourceReservations::class)->execute((int)$order->getEntityId());

        /** @var SourceReservationResultItemInterface[][] $reservationsPerSource */
        $reservationsPerSource = [];
        foreach ($sourceReservations->getReservationItems() as $reservationItem) {
            $resSourceCode = $reservationItem->getReservation()->getSourceCode();
            isset($reservationsPerSource[$resSourceCode]) ?
                $reservationsPerSource[$resSourceCode][] = $reservationItem:
                $reservationsPerSource[$resSourceCode] = [$reservationItem];
        }

        $reservations = $reservationsPerSource[$sourceCode];

        $shipItems = [];
        foreach ($reservations as $reservation) {
            $itemFactory = $this->objectManager->create(ShipmentItemCreationInterfaceFactory::class);
            $shipItem = $itemFactory->create();
            $shipItem->setOrderItemId($reservation->getOrderItemId());
            if ($overrideQty === null) {
                $shipItem->setQty(-$reservation->getReservation()->getQuantity());
            } else {
                $shipItem->setQty($overrideQty);
            }
            $shipItems[] = $shipItem;
        }

        $shipmentCreationArguments = $this->objectManager->get(ShipmentCreationArgumentsInterface::class);
        $shipmentCreationArgumentsExtensionFactory = $this->objectManager->get(ShipmentCreationArgumentsExtensionInterfaceFactory::class);
        if ($shipmentCreationArguments->getExtensionAttributes() === null) {
            $shipmentCreationArguments->setExtensionAttributes($shipmentCreationArgumentsExtensionFactory->create());
        }

        $shipmentCreationArguments->getExtensionAttributes()->setSourceCode($sourceCode);

        $shipOrder = $this->objectManager->create(ShipOrderInterface::class);
        $shipOrder->execute(
            $order->getEntityId(),
            $shipItems,
            false,
            false,
            null,
            [],
            [],
            $shipmentCreationArguments
        );
    }
}