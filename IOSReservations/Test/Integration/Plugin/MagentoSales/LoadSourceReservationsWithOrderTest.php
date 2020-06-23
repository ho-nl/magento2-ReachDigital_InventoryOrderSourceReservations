<?php
declare(strict_types=1);

namespace ReachDigital\IOSReservations\Test\Integration\Plugin\MagentoSales\LoadSourceReservationsWithOrderTest;

use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\Validation\ValidationException;
use Magento\InventorySourceSelectionApi\Api\GetDefaultSourceSelectionAlgorithmCodeInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Sales\Api\InvoiceOrderInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipOrderInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSource;
use ReachDigital\IOSReservationsApi\Exception\CouldNotCreateSourceSelectionRequestFromOrder;
use ReachDigital\IOSReservationsApi\Exception\CouldNotFullySelectSourcesForOrder;

class LoadSourceReservationsWithOrderTest extends TestCase
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var InvoiceOrderInterface
     */
    private $invoiceOrder;

    /**
     * @var MoveReservationsFromStockToSource
     */
    private $moveReservationsFromStockToSource;

    /**
     * @var GetDefaultSourceSelectionAlgorithmCodeInterface
     */
    private $getDefaultSourceSelectionAlgorithmCode;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var AddressInterfaceFactory
     */
    private $addressFactory;

    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var OrderManagementInterface
     */
    private $orderManagement;
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;
    /**
     * @var OrderItemRepositoryInterface
     */
    private $orderItemRepository;

    protected function setUp()
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();

        $this->orderRepository = $objectManager->get(OrderRepositoryInterface::class);
        $this->orderItemRepository = $objectManager->get(OrderItemRepositoryInterface::class);

        $this->orderManagement = $objectManager->get(OrderManagementInterface::class);
        $this->invoiceOrder = $objectManager->get(InvoiceOrderInterface::class);
        $this->moveReservationsFromStockToSource = $objectManager->get(MoveReservationsFromStockToSource::class);
        $this->getDefaultSourceSelectionAlgorithmCode = $objectManager->get(
            GetDefaultSourceSelectionAlgorithmCodeInterface::class
        );

        $this->cartRepository = $objectManager->get(CartRepositoryInterface::class);
        $this->productRepository = $objectManager->get(ProductRepositoryInterface::class);
        $this->cartManagement = $objectManager->get(CartManagementInterface::class);

        $this->addressFactory = $objectManager->get(AddressInterfaceFactory::class);
        $this->storeRepository = $objectManager->get(StoreRepositoryInterface::class);
        $this->storeManager = $objectManager->get(StoreManagerInterface::class);

        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);

        $objectManager->get(ShipOrderInterface::class);
    }

    /**
     * @test
     *
     * @magentoDbIsolation disabled
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
     *
     * @throws Exception
     */
    public function should_use_single_query_to_load_order_item_reservations(): void
    {
        $order1 = $this->createOrder(5);
        $order2 = $this->createOrder(5);
        $order3 = $this->createOrder(4);

        $this->searchCriteriaBuilder->addFilter('entity_id', [$order1, $order2, $order3], 'in');
        $orderResult = $this->orderRepository->getList($this->searchCriteriaBuilder->create());
        self::assertCount(3, $orderResult->getItems());
        foreach ($orderResult->getItems() as $order) {
            self::assertCount(1, $order->getItems());
            foreach ($order->getItems() as $orderItem) {
                self::assertGreaterThan(0, $orderItem->getExtensionAttributes()->getSourceReservations());
            }
        }

        $order = $this->orderRepository->get($order1);
        $order->getItems();
        self::assertCount(1, $order->getItems());
        foreach ($order->getItems() as $orderItem) {
            self::assertCount(2, $orderItem->getExtensionAttributes()->getSourceReservations());
        }

        $order = $this->orderRepository->get($order2);
        $order->getItems();
        self::assertCount(1, $order->getItems());
        foreach ($order->getItems() as $orderItem) {
            self::assertCount(1, $orderItem->getExtensionAttributes()->getSourceReservations());
        }

        $this->searchCriteriaBuilder->addFilter('order_id', [$order1, $order2, $order3], 'in');
        $orderItems = $this->orderItemRepository->getList($this->searchCriteriaBuilder->create());
        self::assertCount(3, $orderItems);
        foreach ($orderItems as $orderItem) {
            self::assertGreaterThan(0, $orderItem->getExtensionAttributes()->getSourceReservations());
        }

        $this->deleteOrder($order1);
        $this->deleteOrder($order2);
        $this->deleteOrder($order3);
    }

    /**
     * Create a simple order
     *
     * @param int $qty
     * @return string
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws ValidationException
     * @throws CouldNotCreateSourceSelectionRequestFromOrder
     * @throws CouldNotFullySelectSourcesForOrder
     */
    private function createOrder(int $qty): string
    {
        $cartId = $this->cartManagement->createEmptyCart();
        $cart = $this->cartRepository->get($cartId);
        $cart->setCustomerEmail('admin@example.com');
        $cart->setCustomerIsGuest(true);
        $store = $this->storeRepository->get('store_for_eu_website');
        $cart->setStoreId($store->getId());
        $this->storeManager->setCurrentStore($store->getCode());

        /** @var AddressInterface $address */
        $address = $this->addressFactory->create([
            'data' => [
                AddressInterface::KEY_COUNTRY_ID => 'US',
                AddressInterface::KEY_REGION_ID => 15,
                AddressInterface::KEY_LASTNAME => 'Doe',
                AddressInterface::KEY_FIRSTNAME => 'John',
                AddressInterface::KEY_STREET => 'example street',
                AddressInterface::KEY_EMAIL => 'customer@example.com',
                AddressInterface::KEY_CITY => 'example city',
                AddressInterface::KEY_TELEPHONE => '000 0000',
                AddressInterface::KEY_POSTCODE => 12345,
            ],
        ]);
        $cart->setBillingAddress($address);
        $cart->setShippingAddress($address);
        $cart->getPayment()->setMethod('checkmo');
        $cart->getShippingAddress()->setShippingMethod('flatrate_flatrate');
        $cart->getShippingAddress()->setCollectShippingRates(true);
        $cart->getShippingAddress()->collectShippingRates();
        $this->cartRepository->save($cart);

        $product = $this->productRepository->get('simple');

        $cart->addProduct($product, new DataObject(['product' => $product->getId(), 'qty' => $qty]));
        $this->cartRepository->save($cart);
        $orderId = (string) $this->cartManagement->placeOrder($cart->getId());

        $this->invoiceOrder->execute($orderId);

        $this->moveReservationsFromStockToSource->execute(
            (int) $orderId,
            $this->getDefaultSourceSelectionAlgorithmCode->execute()
        );

        return $orderId;
    }

    private function deleteOrder($id)
    {
        $registry = Bootstrap::getObjectManager()->get(Registry::class);
        $registry->unregister('isSecureArea');
        $registry->register('isSecureArea', true);

        $this->orderManagement->cancel($id);
        $this->orderRepository->delete($this->orderRepository->get($id));

        $registry->unregister('isSecureArea');
        $registry->register('isSecureArea', false);
    }
}
