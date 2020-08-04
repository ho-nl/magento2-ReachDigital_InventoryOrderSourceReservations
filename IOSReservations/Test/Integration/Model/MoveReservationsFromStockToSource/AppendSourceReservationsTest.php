<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservationsPriority\Test\Integration\Model\MoveReservationsFromStockToSource;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\Validation\ValidationException;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemRepositoryInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryReservations\Model\ResourceModel\GetReservationsQuantity;
use Magento\InventoryReservationsApi\Model\GetReservationsQuantityInterface;
use Magento\InventorySourceSelectionApi\Api\GetDefaultSourceSelectionAlgorithmCodeInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Sales\Api\InvoiceOrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSource;
use ReachDigital\IOSReservationsApi\Exception\CouldNotCreateSourceSelectionRequestFromOrder;
use ReachDigital\IOSReservationsApi\Exception\CouldNotFullySelectSourcesForOrder;
use ReachDigital\ISReservations\Model\MetaData\DecodeMetaData;

class AppendSourceReservationsTest extends TestCase
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

    /**
     * @var SourceItemRepositoryInterface
     */
    private $sourceItemRepository;
    /**
     * @var SourceItemsSaveInterface
     */
    private $sourceItemSave;
    /**
     * @var CartRepositoryInterface|mixed
     */
    private $cartRepository;
    /**
     * @var ProductRepositoryInterface|mixed
     */
    private $productRepository;
    /**
     * @var CartManagementInterface|mixed
     */
    private $cartManagement;
    /**
     * @var AddressInterfaceFactory|mixed
     */
    private $addressFactory;
    /**
     * @var StoreRepositoryInterface|mixed
     */
    private $storeRepository;
    /**
     * @var StoreManagerInterface|mixed
     */
    private $storeManager;
    /**
     * @var OrderManagementInterface|mixed
     */
    private $orderManagement;
    /**
     * @var DecodeMetaData
     */
    private $decodeMetaData;

    protected function setUp()
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->addSharedInstance(
            $objectManager->get(GetReservationsQuantity::class),
            GetReservationsQuantityInterface::class
        );

        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);

        $this->orderRepository = $objectManager->get(OrderRepositoryInterface::class);
        $this->invoiceOrder = $objectManager->get(InvoiceOrderInterface::class);
        $this->moveReservationsFromStockToSource = $objectManager->get(MoveReservationsFromStockToSource::class);
        $this->getDefaultSourceSelectionAlgorithmCode = $objectManager->get(
            GetDefaultSourceSelectionAlgorithmCodeInterface::class
        );
        $this->sourceItemRepository = $objectManager->get(SourceItemRepositoryInterface::class);
        $this->sourceItemSave = $objectManager->get(SourceItemsSaveInterface::class);

        $this->cartRepository = $objectManager->get(CartRepositoryInterface::class);
        $this->productRepository = $objectManager->get(ProductRepositoryInterface::class);
        $this->cartManagement = $objectManager->get(CartManagementInterface::class);

        $this->addressFactory = $objectManager->get(AddressInterfaceFactory::class);
        $this->storeRepository = $objectManager->get(StoreRepositoryInterface::class);
        $this->storeManager = $objectManager->get(StoreManagerInterface::class);
        $this->orderManagement = $objectManager->get(OrderManagementInterface::class);
        $this->decodeMetaData = $objectManager->get(DecodeMetaData::class);
    }

    /**
     * @test
     *
     * @covers \ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSource
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
     *
     * @throws LocalizedException
     */
    public function should_assign_reservations_to_correct_order_items()
    {
        $order1 = $this->createOrder(5);

        // The actual source suddenly has less available
        $item = $this->getSourceItem('simple', 'eu-1');
        $item->setQuantity(0);
        $item2 = $this->getSourceItem('simple', 'eu-2');
        $item2->setQuantity(0);
        $this->sourceItemSave->execute([$item, $item2]);

        $this->invoiceOrder->execute($order1);

        $this->moveReservationsFromStockToSource->execute(
            (int) $order1,
            $this->getDefaultSourceSelectionAlgorithmCode->execute()
        );

        $orderOne = $this->orderRepository->get($order1);

        $item1 = $orderOne->getItems()[0];
        $item2 = $orderOne->getItems()[1];
        self::assertNull($item1->getExtensionAttributes()->getSourceReservations());

        foreach ($item2->getExtensionAttributes()->getSourceReservations() as $reservation) {
            $metaData = $this->decodeMetaData->execute($reservation->getMetadata());
            self::assertEquals($item2->getItemId(), $metaData['order_item']);
            self::assertEquals($item2->getSku(), $reservation->getSku());
        }

        $this->deleteOrder($order1);
    }

    /**
     * Create a simple order
     *
     * @param int $qty
     * @return string
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
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
        $product2 = $this->productRepository->get('simple2');

        $cart->addProduct($product, new DataObject(['product' => $product->getId(), 'qty' => $qty]));
        $cart->addProduct($product2, new DataObject(['product' => $product2->getId(), 'qty' => $qty]));

        $this->cartRepository->save($cart);
        return (string) $this->cartManagement->placeOrder($cart->getId());
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

    private function getSourceItem(string $sku, string $sourceCode): SourceItemInterface
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(SourceItemInterface::SKU, $sku)
            ->addFilter(SourceItemInterface::SOURCE_CODE, $sourceCode)
            ->create();

        $items = $this->sourceItemRepository->getList($searchCriteria)->getItems();
        return array_pop($items);
    }
}
