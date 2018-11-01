<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Plugin\MagentoInventorySales;

use Magento\InventorySales\Model\ReturnProcessor\ProcessRefundItems;
use Magento\Sales\Api\Data\OrderInterface;

class RevertSourceReservationsOnCreditBeforeShipment
{
    public function aroundExecute(
        ProcessRefundItems $subject,
        \Closure $proceed,
        OrderInterface $order,
        array $itemsToRefund,
        array $returnToStockItems
    ): void
    {
        $proceed($order, $itemsToRefund, $returnToStockItems);
    }
}