<?php

namespace ReachDigital\IOSReservations\Model;

use ReachDigital\IOSReservationsApi\Api\GetOrderSourceReservationConfigInterface;

class GetOrderSourceReservationConfig implements GetOrderSourceReservationConfigInterface
{
    /**
     * todo implement an Admin UI configuration for this
     */
    public function allowPartialShipping(): bool
    {
        return true;
    }
}
