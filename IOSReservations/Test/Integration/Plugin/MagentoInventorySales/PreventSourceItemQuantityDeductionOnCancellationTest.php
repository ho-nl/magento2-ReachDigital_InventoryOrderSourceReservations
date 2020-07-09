<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\ObjectManagerInterface;
use Magento\Inventory\Model\SourceItem\Command\GetSourceItemsBySku;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryReservations\Model\ResourceModel\GetReservationsQuantity;
use Magento\InventoryReservationsApi\Model\GetReservationsQuantityInterface;
use Magento\InventorySourceSelection\Model\GetDefaultSourceSelectionAlgorithmCode;
use Magento\Sales\Api\Data\CreditmemoCreationArgumentsExtensionFactory;
use Magento\Sales\Api\Data\CreditmemoCreationArgumentsInterface;
use Magento\Sales\Api\Data\CreditmemoItemCreationInterface;
use Magento\Sales\Api\Data\ShipmentCreationArgumentsExtensionInterfaceFactory;
use Magento\Sales\Api\Data\ShipmentCreationArgumentsInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\RefundOrderInterface;
use Magento\Sales\Model\InvoiceOrder;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use ReachDigital\IOSReservationsApi\Api\MoveReservationsFromStockToSourceInterface;
use ReachDigital\ISReservations\Model\ResourceModel\GetReservationsQuantityList;

class PreventSourceItemQuantityDeductionOnCancellationTest extends TestCase
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

    /** @var ObjectManagerInterface */
    private $objectManager;

    /** @var GetReservationsQuantity */
    private $getStockReservationsQuantity;

    /** @var GetSourceItemsBySku */
    private $getSourceItemsBySku;

    public function setUp()
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->addSharedInstance(
            $objectManager->get(GetReservationsQuantity::class),
            GetReservationsQuantityInterface::class
        );

        $this->getReservationsQuantityList = $objectManager->get(GetReservationsQuantityList::class);
        $this->invoiceOrder = $objectManager->get(InvoiceOrder::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $this->orderRepository = $objectManager->get(OrderRepositoryInterface::class);
        $this->moveReservationsFromStockToSource = $objectManager->get(
            MoveReservationsFromStockToSourceInterface::class
        );
        $this->getDefaultSourceSelectionAlgorithmCode = $objectManager->get(
            GetDefaultSourceSelectionAlgorithmCode::class
        );
        $this->getStockReservationsQuantity = $objectManager->get(GetReservationsQuantity::class);
        $this->getSourceItemsBySku = $objectManager->get(GetSourceItemsBySku::class);
        $this->objectManager = $objectManager;
    }

    /**
     *
     * @test
     *
     * @covers \ReachDigital\IOSReservations\Plugin\MagentoInventorySales\PreventSourceItemQuantityDeductionOnCancellation
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
    public function should_correctly_revert_unshipped_order_without_return_to_stock_at_credit(): void
    {
        // Test the following scenario: order is placed, sourced-assigned and then credited before being shipped (and
        // thus items never left the source)
        // Source reservations should be nullified and stock reservations should not be affected (this should only
        // change during source-assignment).

        // Have an invoiced order
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('increment_id', 'created_order_for_test')->create();
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

        // Fully credit order
        $this->creditOrder($order, false);

        // Assert that:
        // - source qty remains the same
        // - stock reservation qty  remains the same (should not have changed after source-assignment)
        // - source reservation increased by 3
        $currentSourceQty = $this->getSummedSourceQty('simple');
        $currentStockReservationQty = $this->getStockReservationsQuantity->execute('simple', 10);
        $currentSourceReservationQty = $this->getReservationsQuantityList->execute(['simple'])['simple']['quantity'];

        self::assertEquals($initialSourceQty, $currentSourceQty);
        self::assertEquals($initialStockReservationQty, $currentStockReservationQty);
        self::assertEquals($initialSourceReservationQty + 3, $currentSourceReservationQty);
    }

    /**
     *
     * @test
     *
     * @covers \ReachDigital\IOSReservations\Plugin\MagentoInventorySales\PreventSourceItemQuantityDeductionOnCancellation
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
     * @covers \ReachDigital\IOSReservations\Plugin\MagentoInventorySales\PreventSourceItemQuantityDeductionOnCancellation
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

        self::assertEquals($initialSourceQty, $currentSourceQty);
        self::assertEquals(0, $currentStockReservationQty);
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

    /**
     * @param Order $order
     */
    private function creditOrder(Order $order, bool $returnToStock = true, ?float $overrideQty = null): void
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
            if ($returnToStock) {
                $returnItems[] = $orderItem->getItemId();
            }
        }

        $arguments = $this->objectManager->create(CreditmemoCreationArgumentsInterface::class);
        $arguments->setExtensionAttributes(
            $this->objectManager->create(CreditmemoCreationArgumentsExtensionFactory::class)->create()
        );
        if ($returnToStock) {
            $arguments->getExtensionAttributes()->setReturnToStockItems($returnItems);
        }

        $refundOrder->execute($order->getEntityId(), $orderItems, false, false, null, $arguments);
    }
}
