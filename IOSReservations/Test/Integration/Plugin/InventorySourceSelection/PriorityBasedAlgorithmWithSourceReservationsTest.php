<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace IOSReservations\Test\Integration\Plugin\InventorySourceSelection;

use Magento\InventoryReservations\Model\ResourceModel\GetReservationsQuantity;
use Magento\InventoryReservationsApi\Model\GetReservationsQuantityInterface;
use Magento\InventorySourceSelection\Model\Algorithms\PriorityBasedAlgorithm;
use Magento\InventorySourceSelectionApi\Api\Data\InventoryRequestInterfaceFactory;
use Magento\InventorySourceSelectionApi\Api\Data\ItemRequestInterface;
use Magento\InventorySourceSelectionApi\Api\Data\ItemRequestInterfaceFactory;
use Magento\InventorySourceSelectionApi\Api\Data\SourceSelectionResultInterface;
use Magento\InventorySourceSelectionApi\Api\SourceSelectionServiceInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use ReachDigital\ISReservations\Model\AppendSourceReservations;
use ReachDigital\ISReservations\Model\SourceReservationBuilder;

class PriorityBasedAlgorithmWithSourceReservationsTest extends TestCase
{
    /** @var PriorityBasedAlgorithm */
    private $priorityBasedAlgorithm;

    /** @var SourceReservationBuilder */
    private $sourceReservationBuilder;

    /** @var AppendSourceReservations */
    private $appendReservations;

    /** @var ItemRequestInterfaceFactory */
    private $itemRequestFactory;

    /** @var InventoryRequestInterfaceFactory */
    private $inventoryRequestFactory;

    /** @var SourceSelectionServiceInterface */
    private $sourceSelectionService;

    public function setUp()
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->addSharedInstance(
            $objectManager->get(GetReservationsQuantity::class),
            GetReservationsQuantityInterface::class
        );

        $this->sourceReservationBuilder = $objectManager->get(SourceReservationBuilder::class);
        $this->appendReservations = $objectManager->get(AppendSourceReservations::class);
        $this->itemRequestFactory = $objectManager->get(ItemRequestInterfaceFactory::class);
        $this->inventoryRequestFactory = $objectManager->get(InventoryRequestInterfaceFactory::class);
        $this->sourceSelectionService = $objectManager->get(SourceSelectionServiceInterface::class);
    }

    /**
     * @test
     * @covers \ReachDigital\IOSReservations\Plugin\InventorySourceSelection\PriorityBasedAlgorithmWithSourceReservations
     *
     * @magentoDbIsolation disabled
     *
     * Rolling back previous database mess
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory_rollback.php
     * @-magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/product_simple_with_custom_options_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-sales-api/Test/_files/websites_with_stores_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stock_source_links_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stocks_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/sources_rollback.php
     * @-magentoDataFixture ../../../../vendor/reach-digital/magento2-inventory-source-reservations/ISReservations/Test/Integration/_files/clean_all_reservations.php
     *
     * Filling database
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/sources.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stocks.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stock_source_links.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-sales-api/Test/_files/websites_with_stores.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-sales-api/Test/_files/stock_website_sales_channels.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/product_simple_with_custom_options.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/source_items_for_simple_on_multi_source.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/create_quote_on_eu_website.php
     *
     * @throws
     */
    public function should_include_reservations_in_default_ssa(): void
    {
        /* Fixture:
         *
         * | *Sku*  | *Source Code* | *Qty* | *Info*        |
         * |--------|---------------|-------|---------------|
         * | simple | eu-1          | 2     |               |
         * | simple | eu-2          | 12    |               |
         * | simple | eu-3          | 12    | out of stock  |
         * | simple | eu-disabled   | 6     |               |
         * | simple | us-1          | 10    |               |
         */

        // 14 available, should not be shippable
        $selectionResult = $this->requestItems(10, 'simple', 16);
        self::assertEquals(false, $selectionResult->isShippable());

        // Add two by reservation and select for 16, should be shippable
        $this->appendReservation('eu-1', 'simple', 2, 'ssa_test_reservation');
        $selectionResult = $this->requestItems(10, 'simple', 16);
        self::assertEquals(true, $selectionResult->isShippable());

        // Reduce two by reservation and try to select for 16, should no longer be shippable
        $this->appendReservation('eu-1', 'simple', -2, 'ssa_test_reservation');
        $selectionResult = $this->requestItems(10, 'simple', 16);
        self::assertEquals(false, $selectionResult->isShippable());
    }

    /**
     * @test
     * @covers \ReachDigital\IOSReservations\Plugin\InventorySourceSelection\PriorityBasedAlgorithmWithSourceReservations
     *
     * @magentoDbIsolation disabled
     *
     * Rolling back previous database mess
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory_rollback.php
     * @-magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/product_simple_with_custom_options_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-sales-api/Test/_files/websites_with_stores_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stock_source_links_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stocks_rollback.php
     * @-magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/sources_rollback.php
     * @-magentoDataFixture ../../../../vendor/reach-digital/magento2-inventory-source-reservations/ISReservations/Test/Integration/_files/clean_all_reservations.php
     *
     * Filling database
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/sources.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stocks.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/stock_source_links.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-sales-api/Test/_files/websites_with_stores.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-sales-api/Test/_files/stock_website_sales_channels.php
     * @magentoDataFixture ../../../../vendor/reach-digital/magento2-order-source-reservations/IOSReservations/Test/Integration/_files/product_simple_with_custom_options.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/source_items_for_simple_on_multi_source.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/create_quote_on_eu_website.php
     *
     * @throws
     */
    public function should_only_return_one_source_reservation(): void
    {
        /* Fixture:
         *
         * | *Sku*  | *Source Code* | *Qty* | *Info*        |
         * |--------|---------------|-------|---------------|
         * | simple | eu-1          | 2     |               |
         * | simple | eu-2          | 12    |               |
         * | simple | eu-3          | 12    | out of stock  |
         * | simple | eu-disabled   | 6     |               |
         * | simple | us-1          | 10    |               |
         */

        $selectionResult = $this->requestItems(10, 'simple', 2);
        self::assertCount(1, $selectionResult->getSourceSelectionItems());
    }

    private function requestItems(int $stockId, string $sku, int $qty): SourceSelectionResultInterface
    {
        /** @var ItemRequestInterface[] $requestItems */
        $requestItems = [];

        $requestItems[] = $this->itemRequestFactory->create([
            'sku' => $sku,
            'qty' => $qty,
        ]);

        $inventoryRequest = $this->inventoryRequestFactory->create([
            'stockId' => $stockId,
            'items' => $requestItems,
        ]);

        return $this->sourceSelectionService->execute($inventoryRequest, 'priority');
    }

    /**
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Validation\ValidationException
     */
    private function appendReservation(string $sourceCode, string $sku, float $quantity, string $metaData): void
    {
        $this->sourceReservationBuilder->setSourceCode($sourceCode);
        $this->sourceReservationBuilder->setQuantity($quantity);
        $this->sourceReservationBuilder->setSku($sku);
        $this->sourceReservationBuilder->setMetadata($metaData);
        $reservation = $this->sourceReservationBuilder->build();
        $this->appendReservations->execute([$reservation]);
    }
}
