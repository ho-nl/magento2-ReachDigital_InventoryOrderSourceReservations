<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservationsPriority\Test\Integration\Model\Algorithms;

use Magento\Framework\App\ObjectManager;
use ReachDigital\IOSReservationsPriorityApi\Api\OrderSelectionServiceInterface;

class ByDateCreatedAlgorithmTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @test
     * @magentoDataFixture ../../../../app/code/Magento/InventoryApi/Test/_files/products.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryShipping/Test/_files/source_items_for_bundle_children.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryShipping/Test/_files/products_bundle.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryShipping/Test/_files/order_bundle_products.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryIndexer/Test/_files/reindex_inventory.php
     */
    public function should_retrieve_unsourced_orders_by_date(): void
    {
        /** @var OrderSelectionServiceInterface $orderSelectionService */
        $orderSelectionService = ObjectManager::getInstance()->get(OrderSelectionServiceInterface::class);
        $result = $orderSelectionService->execute(null, 'byDateCreated');
        $this->assertEquals(1, $result->getTotalCount());
    }

    public function should_skip_sourced_orders(): void
    {

    }
}
