<?php
declare(strict_types=1);
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace ReachDigital\IOSReservationsCancellationApi\Exception;

use Magento\Framework\Exception\LocalizedException;

class OrderItemNoQuantityToCancel extends LocalizedException
{
    public $quantityToCancel;

    public $orderItemId;

    public $quantityAvailable;

    public static function create(
        int $orderItemId,
        float $quantityToCancel,
        float $quantityAvailable
    ): OrderItemNoQuantityToCancel {
        $exception = new self(
            __(
                "Can not cancel quantity %1 for order item '%2' only '%3' available to cancel",
                $quantityToCancel,
                $orderItemId,
                $quantityAvailable
            )
        );
        $exception->quantityAvailable = $quantityAvailable;
        $exception->quantityToCancel = $quantityToCancel;
        $exception->orderItemId = $orderItemId;

        return $exception;
    }
}
