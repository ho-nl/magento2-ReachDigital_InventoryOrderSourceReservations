<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservationsPriorityApi;

interface GetOrderSelectionAlgorithmCodeInterface
{
    /**
     * Get the configured Order Selection Algorithm Code
     *
     * @return string
     */
    public function execute(): string;
}
