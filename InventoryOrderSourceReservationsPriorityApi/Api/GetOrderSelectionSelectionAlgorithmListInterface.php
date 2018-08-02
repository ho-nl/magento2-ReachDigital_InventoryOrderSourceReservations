<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\InventoryOrderSourceReservationsPriorityApi\Api;

interface GetOrderSelectionSelectionAlgorithmListInterface
{
    /**
     * @return \ReachDigital\InventoryOrderSourceReservationsPriorityApi\Api\Data\OrderSelectionAlgorithmInterface[]
     */
    public function execute(): array;
}
