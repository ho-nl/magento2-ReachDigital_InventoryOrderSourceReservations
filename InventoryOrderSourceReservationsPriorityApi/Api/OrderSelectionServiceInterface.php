<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\InventoryOrderSourceReservationsPriorityApi\Api;

use Magento\Sales\Api\Data\OrderSearchResultInterface;

interface OrderSelectionServiceInterface
{

    /**
     * Get a list of orders that need to be assigned to a source. Sorted by priority
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @param string                                         $algorithmCode
     *
     * @return mixed
     */
    public function execute(
        ?\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria,
        string $algorithmCode
    ): OrderSearchResultInterface;
}
