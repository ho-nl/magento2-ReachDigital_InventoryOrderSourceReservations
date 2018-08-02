<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\IOSReservationsPriorityApi\Model;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;

interface OrderSelectionInterface
{
    /**
     * @todo What are the $searchCriteria used for, isn't this the responsibility of this class to define its own criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return OrderSearchResultInterface
     */
    public function execute(?SearchCriteriaInterface $searchCriteria): OrderSearchResultInterface;
}
