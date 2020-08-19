<?php

namespace ReachDigital\IOSReservationsApi\Api;

use Magento\InventorySalesApi\Api\Data\ItemToSellInterface;

interface NullifyStockAndSourceReservationsInterface
{
    /**
     * Cancel items on order
     *
     * @param ItemToSellInterface[] $itemsToNullify
     */
    public function execute(int $orderId, array $itemsToNullify): void;
}
