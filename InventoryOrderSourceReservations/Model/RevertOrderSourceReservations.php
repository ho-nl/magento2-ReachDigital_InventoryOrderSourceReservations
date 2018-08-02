<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\InventoryOrderSourceReservations\Model;

use ReachDigital\InventorySourceReservationsOrderApi\Api\RevertOrderSourceReservationsInterface;

class RevertOrderSourceReservations implements RevertOrderSourceReservationsInterface
{
    /**
     * @inheritdoc
     */
    public function execute(int $orderId): void
    {
        // TODO: Implement execute() method.
    }
}
