<?php
declare(strict_types=1);
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\IOSReservationsPriorityApi\Api;

use ReachDigital\IOSReservationsPriorityApi\Api\Data\OrderSelectionAlgorithmInterface;

interface GetOrderSelectionSelectionAlgorithmListInterface
{
    /**
     * @return OrderSelectionAlgorithmInterface[]
     */
    public function execute(): array;
}
