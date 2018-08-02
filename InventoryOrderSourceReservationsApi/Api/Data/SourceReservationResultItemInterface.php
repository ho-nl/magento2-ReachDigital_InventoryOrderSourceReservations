<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\InventoryOrderSourceReservationsApi\Api\Data;

use ReachDigital\InventorySourceReservationsApi\Model\ReservationInterface;

interface SourceReservationResultItemInterface
{
    public function getReservation(): ReservationInterface;

    public function getOrderItemId() : int;
}
