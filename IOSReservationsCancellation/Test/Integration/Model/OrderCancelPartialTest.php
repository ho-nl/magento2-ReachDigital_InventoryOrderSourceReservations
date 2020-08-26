<?php

namespace ReachDigital\IOSReservationsCancellation\Test\Integration\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Request\Http;
use Magento\InventoryReservations\Model\ResourceModel\GetReservationsQuantity;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySourceSelection\Model\GetDefaultSourceSelectionAlgorithmCode;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Item\Collection;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use ReachDigital\IOSReservationsApi\Api\MoveReservationsFromStockToSourceInterface;
use ReachDigital\IOSReservationsCancellation\Model\Data\ItemToCancelFactory;
use ReachDigital\IOSReservationsCancellationApi\Api\OrderCancelPartialInterface;
use ReachDigital\ISReservationsApi\Model\GetSourceReservationsQuantityInterface;
use Magento\InventoryShippingAdminUi\Ui\DataProvider\SourceSelectionDataProviderFactory;

class OrderCancelPartialTest extends TestCase
{
    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;

    /** @var OrderCancelPartialInterface */
    private $orderCancelPartial;

    /** @var ItemToCancelFactory */
    private $itemToCancelFactory;
    /**
     * @var GetProductSalableQtyInterface
     */
    private $getProductSalableQty;

    /** @var GetReservationsQuantity */
    private $getStockReservationsQuantity;

    /** @var GetSourceReservationsQuantityInterface */
    private $getSourceReservationsQuantity;

    /** @var MoveReservationsFromStockToSourceInterface */
    private $moveReservationsFromStockToSource;
    /**
     * @var GetDefaultSourceSelectionAlgorithmCode
     */
    private $getDefaultSourceSelectionAlgorithmCode;
    /**
     * @var SourceSelectionDataProviderFactory
     */
    private $sourceSelectionDataProviderFactory;

    protected function setUp()
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->orderRepository = $objectManager->get(OrderRepositoryInterface::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $this->orderCancelPartial = $objectManager->get(OrderCancelPartialInterface::class);
        $this->itemToCancelFactory = $objectManager->get(ItemToCancelFactory::class);

        $this->getProductSalableQty = $objectManager->get(GetProductSalableQtyInterface::class);
        $this->getStockReservationsQuantity = $objectManager->get(GetReservationsQuantity::class);
        $this->getSourceReservationsQuantity = $objectManager->get(GetSourceReservationsQuantityInterface::class);
        $this->moveReservationsFromStockToSource = $objectManager->get(
            MoveReservationsFromStockToSourceInterface::class
        );
        $this->getDefaultSourceSelectionAlgorithmCode = $objectManager->get(
            GetDefaultSourceSelectionAlgorithmCode::class
        );
        $this->sourceSelectionDataProviderFactory = $objectManager->get(SourceSelectionDataProviderFactory::class);
    }

    /**
     * @test
     *
     * @covers \ReachDigital\IOSReservationsCancellation\Model\OrderCancelPartial
     *
     * @magentoDbIsolation disabled
     *
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-inventory-source-reservations/ISReservations/Test/Integration/_files/clean_all_reservations.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/websites_with_stores.php
     * @magentoDataFixture Magento/ConfigurableProduct/_files/configurable_attribute.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-configurable-product/Test/_files/product_configurable.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/sources.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stocks.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stock_source_links.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-sales-api/Test/_files/stock_website_sales_channels.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-configurable-product/Test/_files/source_items_configurable.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/create_quote_on_us_website.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/order_configurable_product.php
     */
    public function should_partially_cancel_order_item_and_revert_reservations()
    {
        self::assertEquals(97, $this->getProductSalableQty->execute('simple_10', 20));
        self::assertEquals(-3, $this->getStockReservationsQuantity->execute('simple_10', 20));
        self::assertEquals(0, $this->getSourceReservationsQuantity->execute('simple_10', 'us-1'));

        // Retrieve order
        $order = current(
            $this->orderRepository
                ->getList($this->searchCriteriaBuilder->addFilter('increment_id', 'created_order_for_test')->create())
                ->getItems()
        );
        $orderId = (int) $order->getEntityId();
        /** @var OrderItemInterface $simple */
        $configurable = current($order->getItems());
        $order = $this->orderRepository->get($orderId);

        // Cancel single item before assigned to source.
        $this->orderCancelPartial->execute($orderId, [
            $this->itemToCancelFactory->create((int) $configurable->getItemId(), 1),
        ]);

        self::assertEquals(98, $this->getProductSalableQty->execute('simple_10', 20));
        self::assertEquals(-2, $this->getStockReservationsQuantity->execute('simple_10', 20));
        self::assertEquals(0, $this->getSourceReservationsQuantity->execute('simple_10', 'us-1'));

        $order->setStatus(Order::STATE_PROCESSING);
        $order->setState(Order::STATE_PROCESSING);

        // Assign to source
        $this->moveReservationsFromStockToSource->execute(
            (int) $order->getEntityId(),
            $this->getDefaultSourceSelectionAlgorithmCode->execute()
        );

        self::assertEquals(98, $this->getProductSalableQty->execute('simple_10', 20));
        self::assertEquals(0, $this->getStockReservationsQuantity->execute('simple_10', 20));
        self::assertEquals(-2, $this->getSourceReservationsQuantity->execute('simple_10', 'us-1'));
        self::assertEquals(Order::STATE_PROCESSING, $order->getState());

        $this->orderCancelPartial->execute($order->getEntityId(), [
            $this->itemToCancelFactory->create((int) $configurable->getItemId(), 1),
        ]);

        self::assertEquals(99, $this->getProductSalableQty->execute('simple_10', 20));
        self::assertEquals(0, $this->getStockReservationsQuantity->execute('simple_10', 20));
        self::assertEquals(-1, $this->getSourceReservationsQuantity->execute('simple_10', 'us-1'));
        self::assertEquals(Order::STATE_PROCESSING, $order->getState());

        $this->orderCancelPartial->execute($order->getEntityId(), [
            $this->itemToCancelFactory->create((int) $configurable->getItemId(), 1),
        ]);

        self::assertEquals(100, $this->getProductSalableQty->execute('simple_10', 20));
        self::assertEquals(0, $this->getStockReservationsQuantity->execute('simple_10', 20));
        self::assertEquals(0, $this->getSourceReservationsQuantity->execute('simple_10', 'us-1'));
        self::assertEquals(Order::STATE_CANCELED, $order->getState());
    }

    /**
     * @test
     *
     * @covers \ReachDigital\IOSReservationsCancellation\Model\OrderCancelPartial
     *
     * @magentoDbIsolation disabled
     *
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-inventory-source-reservations/ISReservations/Test/Integration/_files/clean_all_reservations.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/websites_with_stores.php
     * @magentoDataFixture Magento/ConfigurableProduct/_files/configurable_attribute.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-configurable-product/Test/_files/product_configurable.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/sources.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stocks.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stock_source_links.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-sales-api/Test/_files/stock_website_sales_channels.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-configurable-product/Test/_files/source_items_configurable.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/create_quote_on_us_website.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/order_configurable_product.php
     */
    public function should_only_ship_and_invoice_available_when_partially_cancelled()
    {
        self::assertEquals(97, $this->getProductSalableQty->execute('simple_10', 20));
        self::assertEquals(-3, $this->getStockReservationsQuantity->execute('simple_10', 20));
        self::assertEquals(0, $this->getSourceReservationsQuantity->execute('simple_10', 'us-1'));

        // Retrieve order
        $order = current(
            $this->orderRepository
                ->getList($this->searchCriteriaBuilder->addFilter('increment_id', 'created_order_for_test')->create())
                ->getItems()
        );
        $orderId = (int) $order->getEntityId();
        /** @var OrderItemInterface $simple */
        $configurable = current($order->getItems());

        $order = $this->orderRepository->get($orderId);

        // Assign to source
        $this->moveReservationsFromStockToSource->execute(
            (int) $order->getEntityId(),
            $this->getDefaultSourceSelectionAlgorithmCode->execute()
        );

        // Cancel single item
        $this->orderCancelPartial->execute($orderId, [
            $this->itemToCancelFactory->create((int) $configurable->getItemId(), 1),
        ]);
        $order->setStatus(Order::STATE_PROCESSING);
        $order->setState(Order::STATE_PROCESSING);

        // Shipment: It should only  Check that we only have two items to ship
        $prophecy = $this->prophesize(Http::class);
        $prophecy->getParam('order_id')->willReturn($orderId);

        $dataProvider = $this->sourceSelectionDataProviderFactory->create([
            'name' => 'inventory_shipping_source_selection_form_data_source',
            'requestFieldName' => 'order_id',
            'primaryFieldName' => 'order_id',
            'request' => $prophecy->reveal(),
            'getSourcesByStockIdSkuAndQty' => null,
        ]);

        $result = $dataProvider->getData()[$orderId]['items'][0]['sources'][0];
        self::assertEquals('us-1', $result['sourceCode']);
        self::assertEquals(2, $result['qtyAvailable']);
        self::assertEquals(2, $result['qtyToDeduct']);

        // Invoice: It should only invoice qty 2 for the simple and configurable.
        /** @var Order $order */
        $order = $this->orderRepository->get($orderId);
        $invoice = $order->prepareInvoice();

        /** @var Collection $invoiceItems */
        $invoiceItems = $invoice->getItems();
        self::assertEquals(2, $invoiceItems->count());
        foreach ($invoiceItems as $item) {
            self::assertEquals(2, $item->getQty());
        }
    }
}
