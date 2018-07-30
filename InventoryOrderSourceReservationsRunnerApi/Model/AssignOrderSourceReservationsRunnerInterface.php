<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\InventoryOrderSourceReservationsRunnerApi;

interface AssignOrderSourceReservationsRunnerInterface
{

    /**
     * Assign the unassigned orders to their correct sources.
     */
    public function execute(): void;
}
