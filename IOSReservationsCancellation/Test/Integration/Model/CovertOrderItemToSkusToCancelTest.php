<?php

namespace ReachDigital\IOSReservationsCancellation\Test\Integration\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use ReachDigital\IOSReservationsCancellation\Model\ConvertOrderItemToSkusToCancel;
use ReachDigital\IOSReservationsCancellationApi\Exception\OrderItemNoQuantityToCancel;

class CovertOrderItemToSkusToCancelTest extends TestCase
{
    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;

    /** @var ConvertOrderItemToSkusToCancel */
    private $convertOrderItemToSkusToCancel;

    protected function setUp()
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->orderRepository = $objectManager->get(OrderRepositoryInterface::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $this->convertOrderItemToSkusToCancel = $objectManager->get(ConvertOrderItemToSkusToCancel::class);
    }

    /**
     * @test
     *
     * @covers \ReachDigital\IOSReservationsCancellation\Model\ConvertOrderItemToSkusToCancel
     *
     * @magentoDbIsolation disabled
     *
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-sales-api/Test/_files/websites_with_stores.php
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
    public function test()
    {
        $order = current(
            $this->orderRepository
                ->getList($this->searchCriteriaBuilder->addFilter('increment_id', 'created_order_for_test')->create())
                ->getItems()
        );

        $items = $order->getItems();

        /** @var OrderItemInterface $simple */
        $configurable = current($items);

        /** @var OrderItemInterface $configurable */
        $simple = next($items);

        self::assertEquals('simple', $simple->getProductType());
        self::assertEquals('configurable', $configurable->getProductType());

        // Not possible to cancel simple of configurable.
        $qtyToCancel = 1000;
        try {
            $this->convertOrderItemToSkusToCancel->execute($simple, 3);
        } catch (OrderItemNoQuantityToCancel $e) {
            $qtyToCancel = $e->quantityAvailable;
        }
        self::assertEquals(0, $qtyToCancel);

        // We can cancel less than available
        $result = $this->convertOrderItemToSkusToCancel->execute($configurable, 2);
        self::assertEquals('simple_10', $result[0]->getSku());
        self::assertEquals(2, $result[0]->getQuantity());

        // We can cancel the max amount
        $result = $this->convertOrderItemToSkusToCancel->execute($configurable, 3);
        self::assertEquals('simple_10', $result[0]->getSku());
        self::assertEquals(3, $result[0]->getQuantity());

        // Not possible to cancel more than available to cancel.
        $qtyToCancel = 0;
        try {
            $this->convertOrderItemToSkusToCancel->execute($configurable, 10);
        } catch (OrderItemNoQuantityToCancel $e) {
            $qtyToCancel = $e->quantityAvailable;
        }
        self::assertEquals(3, $qtyToCancel);

        // When requesting zero to cancel, we don't get any items
        $result = $this->convertOrderItemToSkusToCancel->execute($configurable, 0);
        self::assertEmpty($result);

        // When requesting less than 0 to cancel, we don't get any items
        $result = $this->convertOrderItemToSkusToCancel->execute($configurable, -1);
        self::assertEmpty($result);
    }
}
