<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\InventorySourceSelection\Model\GetDefaultSourceSelectionAlgorithmCode;
use Magento\Sales\Api\Data\CreditmemoCreationArgumentsExtensionFactory;
use Magento\Sales\Api\Data\CreditmemoCreationArgumentsInterface;
use Magento\Sales\Api\Data\CreditmemoItemCreationInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\RefundOrderInterface;
use Magento\Sales\Model\InvoiceOrder;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
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

    public function setUp()
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->getReservationsQuantityList = $objectManager->get(GetReservationsQuantityList::class);
        $this->invoiceOrder = $objectManager->get(InvoiceOrder::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $this->orderRepository = $objectManager->get(OrderRepositoryInterface::class);
        $this->moveReservationsFromStockToSource = $objectManager->get(MoveReservationsFromStockToSourceInterface::class);
        $this->getDefaultSourceSelectionAlgorithmCode = $objectManager->get(GetDefaultSourceSelectionAlgorithmCode::class);
        $this->objectManager = $objectManager;
    }

    /**
     *
     * @test
     *
     * @covers \ReachDigital\IOSReservations\Plugin\MagentoSales\RevertSourceReservationsOnCreditBeforeShipment
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
     * @covers \ReachDigital\IOSReservations\Plugin\MagentoSales\RevertSourceReservationsOnCreditBeforeShipment
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
     * @test
     */
    public function should_not_return_reservation_reverted_qty_to_source_or_stock_reservation()
    {
        // \Magento\InventorySales\Model\ReturnProcessor\ProcessRefundItems::execute() will either return qty to source,
        // or add back as reservation. Assert that it does not do that for the qtys we've already added back as source
        // reservations through \ReachDigital\IOSReservations\Plugin\MagentoSales\RevertSourceReservationsOnCreditBeforeShipment::aroundAfterSave

    }

    /**
     * @param Order $order
     */
    private function creditOrder(Order $order, ?float $overrideQty = null): void
    {
        $refundOrder = $this->objectManager->create(RefundOrderInterface::class);

        $items = [];
        $returnItems = [];
        foreach ($order->getAllItems() as $item) {
            $creditItem = $this->objectManager->create(CreditmemoItemCreationInterface::class);
            $creditItem->setOrderItemId($item->getItemId());
            if ($overrideQty === null) {
                $creditItem->setQty($item->getQtyOrdered());
            } else {
                $creditItem->setQty($overrideQty);
            }
            $items[] = $creditItem;
            $returnItems[] = $item->getItemId();
        }

        $arguments = $this->objectManager->create(CreditmemoCreationArgumentsInterface::class);
        $arguments->setExtensionAttributes(
            $this->objectManager->create(CreditmemoCreationArgumentsExtensionFactory::class)
                ->create());
        $arguments->getExtensionAttributes()->setReturnToStockItems($returnItems);

        $refundOrder->execute(
            $order->getEntityId(),
            $items,
            false,
            false,
            null,
            $arguments
        );
    }
}