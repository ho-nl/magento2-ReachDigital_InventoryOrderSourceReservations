<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Model;

use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultInterface;
use ReachDigital\IOSReservationsApi\Api\GetOrderSourceReservationsInterface;

class GetOrderSourceReservations implements GetOrderSourceReservationsInterface
{

    /**
     * @param int $orderId
     *
     * @return SourceReservationResultInterface
     */
    public function execute(int $orderId): ? SourceReservationResultInterface
    {
        // TODO: Implement execute() method.
    }
}
