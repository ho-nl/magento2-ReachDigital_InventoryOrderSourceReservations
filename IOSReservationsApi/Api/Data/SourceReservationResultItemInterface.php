<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservationsApi\Api\Data;

use ReachDigital\ISReservationsApi\Model\ReservationInterface;

interface SourceReservationResultItemInterface
{
    public function getReservation(): ReservationInterface;

    public function getOrderItemId() : int;
}
