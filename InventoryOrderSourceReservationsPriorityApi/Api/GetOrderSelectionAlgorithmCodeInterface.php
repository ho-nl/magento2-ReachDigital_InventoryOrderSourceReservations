<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\InventoryOrderSourceReservationsPriorityApi;

interface GetOrderSelectionAlgorithmCodeInterface
{
    /**
     * Get the configured Order Selection Algorithm Code
     *
     * @return string
     */
    public function execute(): string;
}
