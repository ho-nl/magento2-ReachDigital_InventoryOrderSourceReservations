<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservationsPriority\Test\Integration\Model\Algorithms;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceOrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use ReachDigital\IOSReservationsPriorityApi\Api\OrderSelectionServiceInterface;

class ByDateCreatedAlgorithmTest extends \PHPUnit\Framework\TestCase
{

    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var InvoiceOrderInterface */
    private $invoiceOrder;

    /** @var OrderSelectionServiceInterface */
    private $orderSelectionService;

    protected function setUp()
    {
        $this->searchCriteriaBuilder = Bootstrap::getObjectManager()->get(SearchCriteriaBuilder::class);
        $this->orderRepository = Bootstrap::getObjectManager()->get(OrderRepositoryInterface::class);
        $this->invoiceOrder = Bootstrap::getObjectManager()->get(InvoiceOrderInterface::class);
        $this->orderSelectionService = Bootstrap::getObjectManager()->get(OrderSelectionServiceInterface::class);
    }

    /**
     * @test
     *
     * @covers \ReachDigital\IOSReservationsPriorityApi\Model\OrderSelectionService, \ReachDigital\IOSReservationsPriority\Model\Algorithms\AssignOrderSourceReservations
     *
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-api/Test/_files/products.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/source_items_for_bundle_children.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/products_bundle.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-shipping/Test/_files/order_bundle_products.php
     * @magentoDataFixture ../../../../vendor/magento/module-inventory-indexer/Test/_files/reindex_inventory.php
     */
    public function should_retrieve_unsourced_orders_by_date(): void
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', 'test_order_bundle_1')
            ->create();
        /** @var OrderInterface $order */
        $order = current($this->orderRepository->getList($searchCriteria)->getItems());
        $this->invoiceOrder->execute($order->getEntityId());

        $result = $this->orderSelectionService->execute(null, 'byDateCreated');
        $this->assertEquals(1, $result->getTotalCount());
    }

    /**
     * @test
     */
    public function should_skip_sourced_orders(): void
    {

    }
}
