<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\InventorySourceReservationsOrderApi\Model;

use ReachDigital\InventoryOrderSourceReservationsApi\Exception\SourceReservationForOrderInputException;

interface RevertOrderSourceReservationsInterface
{
    /**
     * @param int $orderId
     *
     * @throws SourceReservationForOrderInputException
     *
     * @return void
     */
    public function execute(int $orderId): void;
}
