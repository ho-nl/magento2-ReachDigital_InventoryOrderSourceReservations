<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservationsApi\Api;

use ReachDigital\IOSReservationsApi\Exception\SourceReservationForOrderInputException;

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
