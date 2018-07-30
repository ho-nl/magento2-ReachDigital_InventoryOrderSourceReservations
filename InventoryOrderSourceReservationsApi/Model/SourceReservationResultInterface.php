<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace ReachDigital\InventoryOrderSourceReservationsApi\Model;

use ReachDigital\InventorySourceReservationsApi\Model\ReservationInterface;

/**
 * Result of how we will deduct product qty from different Sources
 *
 * @api
 */
interface SourceReservationResultInterface
{
    /**
     * @return ReservationInterface[]
     */
    public function getReservationItems(): array;

    /**
     * @return int
     */
    public function orderId() : int;
}
