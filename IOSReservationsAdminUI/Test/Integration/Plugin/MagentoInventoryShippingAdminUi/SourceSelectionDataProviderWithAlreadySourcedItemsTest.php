<?php
declare(strict_types=1);

namespace ReachDigital\IOSReservationsAdminUI\Test\Integration\Plugin\MagentoInventoryShippingAdminUi;

use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\Validation\ValidationException;
use Magento\InventoryShippingAdminUi\Ui\DataProvider\SourceSelectionDataProviderFactory;
use Magento\InventorySourceSelectionApi\Api\GetDefaultSourceSelectionAlgorithmCodeInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Sales\Api\InvoiceOrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipOrderInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use ReachDigital\IOSReservationsApi\Api\MoveReservationsFromStockToSourceInterface;
use ReachDigital\IOSReservationsApi\Exception\CouldNotCreateSourceSelectionRequestFromOrder;
use ReachDigital\IOSReservationsApi\Exception\CouldNotFullySelectSourcesForOrder;

class SourceSelectionDataProviderWithAlreadySourcedItemsTest extends TestCase
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
     * @var MoveReservationsFromStockToSourceInterface
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
     * @var SourceSelectionDataProviderFactory
     */
    private $sourceSelectionDataProviderFactory;

    protected function setUp()
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();

        $this->orderRepository = $objectManager->get(OrderRepositoryInterface::class);
        $this->orderManagement = $objectManager->get(OrderManagementInterface::class);
        $this->invoiceOrder = $objectManager->get(InvoiceOrderInterface::class);
        $this->moveReservationsFromStockToSource = $objectManager->get(
            MoveReservationsFromStockToSourceInterface::class
        );
        $this->getDefaultSourceSelectionAlgorithmCode = $objectManager->get(
            GetDefaultSourceSelectionAlgorithmCodeInterface::class
        );

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
     *
     * @throws Exception
     */
    public function admin_ui_create_shipment_should_use_source_reservations(): void
    {
        $order1 = $this->createOrder(2);
        $order2 = $this->createOrder(2);
        $order3 = $this->createOrder(2);

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
