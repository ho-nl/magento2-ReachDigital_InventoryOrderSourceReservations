<?php
declare(strict_types=1);
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace ReachDigital\IOSReservationsCancellationApi\Exception;

use Magento\Framework\Exception\LocalizedException;

class OrderItemNotExists extends LocalizedException
{
    public $orderId;

    public $orderItemId;

    public static function create(int $orderId, int $orderItemId): OrderItemNotExists
    {
        $e = new self(__("Item with id '%1' does not exist on order '%2'", $orderItemId, $orderId));
        $e->orderId = $orderId;
        $e->orderItemId = $orderItemId;
        return $e;
    }
}
