<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Plugin\MagentoInventorySales;

use Magento\InventorySales\Model\ReturnProcessor\DeductSourceItemQuantityOnRefund;
use Magento\Sales\Api\Data\OrderInterface;

class PreventSourceItemQuantityDeductionOnRefund
{
    /**
     * Around plugin to prevent source item quantity deduction on refund of non-shipped order items
     *
     * @param DeductSourceItemQuantityOnRefund $subject
     * @param \Closure $proceed
     * @param OrderInterface $order
     * @param array $itemsToRefund
     * @param array $itemsToDeductFromSource
     */
    public function aroundExecute(
        DeductSourceItemQuantityOnRefund $subject,
        \Closure $proceed,
        OrderInterface $order,
        array $itemsToRefund,
        array $itemsToDeductFromSource
    ): void {
        return;
    }
}
