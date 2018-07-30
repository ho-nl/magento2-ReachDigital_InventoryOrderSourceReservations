<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\InventoryOrderSourceReservationsApi\Model;

interface AssignOrderSourceReservationsInterface
{

    /**
     * @param int    $orderId
     * @param string $algorithmCode
     *
     * @return SourceReservationResultInterface
     */
    public function execute(int $orderId, string $algorithmCode): SourceReservationResultInterface;
}
