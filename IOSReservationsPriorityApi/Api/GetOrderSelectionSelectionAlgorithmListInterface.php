<?php
declare(strict_types=1);
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\IOSReservationsPriorityApi\Api;

interface GetOrderSelectionSelectionAlgorithmListInterface
{
    /**
     * @return \ReachDigital\IOSReservationsPriorityApi\Api\Data\OrderSelectionAlgorithmInterface[]
     */
    public function execute(): array;
}
