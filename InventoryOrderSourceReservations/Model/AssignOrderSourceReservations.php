<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\InventoryOrderSourceReservations\Model;

use ReachDigital\InventoryOrderSourceReservationsApi\Api\AssignOrderSourceReservationsInterface;
use ReachDigital\InventoryOrderSourceReservationsApi\Api\Data\SourceReservationResultInterface;

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
        // TODO: Implement execute() method.
    }
}
