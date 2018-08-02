<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Model;

use ReachDigital\IOSReservationsApi\Api\AssignOrderSourceReservationsInterface;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultInterface;

class AssignOrderSourceReservations implements AssignOrderSourceReservationsInterface
{

    /**
     * @param int    $orderId
     * @param string $algorithmCode
     *
     * @return SourceReservationResultInterface
     */
    public function execute(int $orderId, string $algorithmCode): SourceReservationResultInterface
    {
        //Revert Stock Reservations
        //Append Source Reservations

        //Stict mode handling? Exception handling? What if an order can't be assigned to sources?
        //This method calls the actual SourceSelectionAlgorithm, but this algorithm should use the right data, so the
        //source data of that algorithm is different based on the strict mode setting.

        // TODO: Implement execute() method.
    }
}
