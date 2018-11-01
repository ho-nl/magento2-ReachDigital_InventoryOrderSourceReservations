<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservationsPriority\Model\Algorithms;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use ReachDigital\IOSReservationsPriorityApi\Model\OrderSelectionInterface;

class ByDateCreatedAlgorithm implements OrderSelectionInterface
{

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var SortOrderBuilder
     */
    private $sortOrderBuilder;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder,
        ResourceConnection $resourceConnection
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Get orders with one or more order items not yet assigned to source.
     *
     * @inheritdoc
     */
    public function execute(?int $limit): OrderSearchResultInterface
    {
        $conn = $this->resourceConnection->getConnection();
        $resTable = $this->resourceConnection->getTableName('inventory_source_reservation');
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $orderItemTable = $this->resourceConnection->getTableName('sales_order_item');

        // Fetch IDs of all orders being processed, which have at least one order_item without a source reservation
        $select = $conn->select()
            ->distinct(true)
            ->from(     [ 'o'  => $orderTable     ], OrderInterface::ENTITY_ID)
            ->joinInner([ 'oi' => $orderItemTable ], 'oi.order_id = o.entity_id', [])
            ->joinLeft( [ 'r'  => $resTable       ], "r.metadata like concat('%order_item:', oi.item_id, '%')", [])
            ->where(sprintf("r.reservation_id is null AND o.%s = '%s'", OrderInterface::STATE, Order::STATE_PROCESSING));

        $orderIds = $conn->fetchCol($select);

        $this->searchCriteriaBuilder->addFilter(OrderInterface::STATE, Order::STATE_PROCESSING);
        $this->searchCriteriaBuilder->addFilter(OrderInterface::ENTITY_ID, $orderIds, 'in');

        $sort = $this->sortOrderBuilder
            ->setField(OrderInterface::CREATED_AT)
            ->setAscendingDirection()
            ->create();
        $this->searchCriteriaBuilder->addSortOrder($sort);

        if ($limit) {
            $this->searchCriteriaBuilder->setPageSize($limit);
        }

        return $this->orderRepository->getList($this->searchCriteriaBuilder->create());
    }
}
