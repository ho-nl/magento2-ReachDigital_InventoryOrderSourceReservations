<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\InventoryOrderSourceReservationsPriorityAdminUI;

use ReachDigital\InventoryOrderSourceReservationsPriorityApi\GetOrderSelectionAlgorithmCodeInterface;

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
        // TODO: Implement execute() method.
    }
}
