<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\InventoryOrderSourceReservationsRunnerApi;

use Magento\Sales\Api\Data\OrderSearchResultInterface;

interface GetOrdersBySourceReservationPriorityInterface
{
    /**
     * Get a list of orders that need to be assigned to a source. Sorted by priority
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     *
     * @return mixed
     */
    public function execute(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria): OrderSearchResultInterface;
}
