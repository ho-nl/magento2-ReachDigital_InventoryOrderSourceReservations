<?php

namespace ReachDigital\IOSReservationsApi\Api;

use Magento\InventorySalesApi\Api\Data\ItemToSellInterface;

interface NullifyStockAndSourceReservationsInterface
{
    /**
     * Cancel items on an order.
     *
     * @param ItemToSellInterface[] $itemsToNullify
     * @return ItemToSellInterface[]
     */
    public function execute(int $orderId, array $itemsToNullify): array;
}
