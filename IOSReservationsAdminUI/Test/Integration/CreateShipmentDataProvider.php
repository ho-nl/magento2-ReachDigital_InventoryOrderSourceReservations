<?php
declare(strict_types=1);

namespace ReachDigital\IOSReservationsAdminUI\Test\Integration;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Request\Http;
use Magento\Framework\DataObject;
use Magento\Framework\Registry;
use Magento\InventoryReservations\Model\ResourceModel\GetReservationsQuantity;
use Magento\InventorySales\Model\GetProductSalableQty;
use Magento\InventoryShippingAdminUi\Ui\DataProvider\SourceSelectionDataProviderFactory;
use Magento\InventorySourceDeductionApi\Model\GetSourceItemBySourceCodeAndSku;
use Magento\InventorySourceSelectionApi\Api\GetDefaultSourceSelectionAlgorithmCodeInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Sales\Api\Data\ShipmentCreationArgumentsInterface;
use Magento\Sales\Api\InvoiceOrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipOrderInterface;
use Magento\Sales\Model\Convert\Order;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use ReachDigital\IOSReservations\Model\GetOrderSourceReservations;
use ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSource;
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

    /** @var Order */
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
     * @var SourceSelectionDataProviderFactory
     */
    private $sourceSelectionDataProviderFactory;

    protected function setUp()
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();

        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $this->orderRepository = $objectManager->get(OrderRepositoryInterface::class);
        $this->orderManagement = $objectManager->get(OrderManagementInterface::class);
        $this->invoiceOrder = $objectManager->get(InvoiceOrderInterface::class);
        $this->moveReservationsFromStockToSource = $objectManager->get(MoveReservationsFromStockToSource::class);
        $this->getDefaultSourceSelectionAlgorithmCode = $objectManager->get(
            GetDefaultSourceSelectionAlgorithmCodeInterface::class
        );
        $this->getOrderSourceReservations = $objectManager->get(GetOrderSourceReservations::class);
        $this->shipOrder = $objectManager->get(ShipOrderInterface::class);
        $this->getProductSalableQty = $objectManager->create(GetProductSalableQty::class);

        $this->orderConverter = $objectManager->get(Order::class);
        $this->shipmentCreationArguments = $objectManager->get(ShipmentCreationArgumentsInterface::class);
        $this->shipmentCreationArgumentsExtensionInterfaceFactory = $objectManager->get(
            ShipmentCreationArgumentsExtensionInterfaceFactory::class
        );
        $this->getSourceItemBySourceCodeAndSku = $objectManager->get(GetSourceItemBySourceCodeAndSku::class);
        $this->getStockReservationsQuantity = $objectManager->get(GetReservationsQuantity::class);
        $this->getSourceReservationsQuantity = $objectManager->get(GetSourceReservationsQuantityInterface::class);

        $this->cartRepository = $objectManager->get(CartRepositoryInterface::class);
        $this->productRepository = $objectManager->get(ProductRepositoryInterface::class);
        $this->cartManagement = $objectManager->get(CartManagementInterface::class);

        $this->addressFactory = $objectManager->get(AddressInterfaceFactory::class);
        $this->storeRepository = $objectManager->get(StoreRepositoryInterface::class);
        $this->storeManager = $objectManager->get(StoreManagerInterface::class);

        $this->sourceSelectionDataProviderFactory = $objectManager->get(SourceSelectionDataProviderFactory::class);

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
     */
    public function should_be_able_to_ship_although_product_is_not_salable_anymore(): void
    {
        $order1 = $this->createOrder(5);
        $order2 = $this->createOrder(5);
        $order3 = $this->createOrder(4);

        $prophecy = $this->prophesize(Http::class);
        $prophecy->getParam('order_id')->willReturn($order1);

        $dataProvider = $this->sourceSelectionDataProviderFactory->create([
            'name' => 'inventory_shipping_source_selection_form_data_source',
            'requestFieldName' => 'order_id',
            'primaryFieldName' => 'order_id',
            'request' => $prophecy->reveal(),
            'getSourcesByStockIdSkuAndQty' => null,
        ]);

        $const = $dataProvider->getData()[$order1]['items'][0]['sources'];
        self::assertGreaterThan(0, count($const));

        $this->deleteOrder($order1);
        $this->deleteOrder($order2);
        $this->deleteOrder($order3);
    }

    private function createOrder($qty): string
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
