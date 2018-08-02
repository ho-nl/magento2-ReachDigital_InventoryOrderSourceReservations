<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservationsPriorityAdminUI;

use ReachDigital\IOSReservationsPriorityApi\GetOrderSelectionAlgorithmCodeInterface;

class GetOrderSelectionAlgorithmCode implements GetOrderSelectionAlgorithmCodeInterface
{
    /**
     * Get the configured Order Selection Algorithm Code
     *
     * @return string
     */
    public function execute(): string
    {
        return 'byDatePlaced';
    }
}
