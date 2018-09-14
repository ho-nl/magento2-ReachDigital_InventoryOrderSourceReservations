<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservationsPriority\Model\Algorithms;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
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

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->sortOrderBuilder = $sortOrderBuilder;
    }

    /**
     * @todo should this be able to debounce orders? If an order cant be Sourced, should this always return something
     * @todo it is the responsibility of this method to return orders based on the time of day / strategy.
     * @todo how to we filter on 'already sourced items'?
     * @inheritdoc
     */
    public function execute(?int $limit): OrderSearchResultInterface
    {
        $this->searchCriteriaBuilder->addFilter(OrderInterface::STATE, Order::STATE_PROCESSING);

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
