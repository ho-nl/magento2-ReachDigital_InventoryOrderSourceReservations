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
use ReachDigital\IOSReservationsPriority\Model\GetUnSourcedOrderIds;
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
     * @var GetUnSourcedOrderIds
     */
    private $getUnSourcedOrderIds;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder,
        GetUnSourcedOrderIds $getUnSourcedOrderIds
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->getUnSourcedOrderIds = $getUnSourcedOrderIds;
    }

    /**
     * Get orders with one or more order items not yet assigned to source.
     *
     * @inheritdoc
     */
    public function execute(?int $limit): OrderSearchResultInterface
    {
        $this->searchCriteriaBuilder->addFilter(OrderInterface::STATE, Order::STATE_PROCESSING);
        $unsourcedIds = $this->getUnSourcedOrderIds->execute();
        $this->searchCriteriaBuilder->addFilter(OrderInterface::ENTITY_ID, $unsourcedIds, 'in');

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
