<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\InventoryOrderSourceReservationsApi\Model;

interface GetOrderSourceReservationsInterface
{

    /**
     * @param int $orderId
     *
     * @return SourceReservationResultInterface
     */
    public function execute(int $orderId) :? SourceReservationResultInterface;
}
