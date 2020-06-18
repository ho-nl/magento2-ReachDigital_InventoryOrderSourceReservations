<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservationsPriorityApi\Api;

use Magento\Sales\Api\Data\OrderSearchResultInterface;

interface OrderSelectionServiceInterface
{
    /**
     * Get a list of orders that need to be assigned to a source. Sorted by priority
     *
     * @param int|null $limit
     * @param string   $algorithmCode
     *
     * @return mixed
     */
    public function execute(?int $limit, string $algorithmCode): OrderSearchResultInterface;
}
