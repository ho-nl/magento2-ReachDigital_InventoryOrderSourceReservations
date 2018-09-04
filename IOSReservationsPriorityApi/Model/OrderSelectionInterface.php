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
     * @param int|null $limit
     * @return OrderSearchResultInterface
     */
    public function execute(?int $limit): OrderSearchResultInterface;
}
