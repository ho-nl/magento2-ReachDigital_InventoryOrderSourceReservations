<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservationsApi\Api;

interface MoveReservationsFromStockToSourceRunnerInterface
{
    /**
     * Assign the unassigned orders to their correct sources.
     */
    public function execute(): void;
}
