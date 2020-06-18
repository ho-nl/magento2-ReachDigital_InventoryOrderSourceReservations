<?php
declare(strict_types=1);
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
     * It is guaranteed that this method will be called at least every half hour, can be more. This method should
     * return a list of orders can be Source Selected.
     *
     * If for example you are implementing a time based system: Orders will be send at 17:00, then only at 17:00 should
     * this method return orders, and not earlier.
     *
     * @param int|null $limit
     * @return OrderSearchResultInterface
     */
    public function execute(?int $limit): OrderSearchResultInterface;
}
