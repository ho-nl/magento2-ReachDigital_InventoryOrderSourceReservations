<?php

namespace ReachDigital\IOSReservationsCancellationApi\Api;

use Magento\Framework\Exception\LocalizedException;
use ReachDigital\IOSReservationsCancellationApi\Api\Data\ItemToCancelInterface;

interface OrderCancelPartialInterface
{
    /**
     * Cancels a quantity on the order an order item.
     * - Check if amount can be cancelled
     *
     * - Revert stock and source reservations if available
     *
     * - Update the qtyCancelled on the order item
     * - Update the order totals when cancelled
     * - Cancel complete order when nothing left to ship or cancel
     * - Send email upon full cancellation
     *
     * @param $itemsToCancel ItemToCancelInterface[]
     */
    public function execute(int $orderId, array $itemsToCancel): void;
}
