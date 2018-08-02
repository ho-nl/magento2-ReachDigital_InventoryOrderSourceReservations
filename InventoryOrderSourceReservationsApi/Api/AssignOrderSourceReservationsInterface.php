<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\InventoryOrderSourceReservationsApi\Api;

use ReachDigital\InventoryOrderSourceReservationsApi\Api\Data\SourceReservationResultInterface;

interface AssignOrderSourceReservationsInterface
{

    /**
     * @param int    $orderId
     * @param string $algorithmCode
     *
     * @return SourceReservationResultInterface
     */
    public function execute(int $orderId, string $algorithmCode): SourceReservationResultInterface;
}
