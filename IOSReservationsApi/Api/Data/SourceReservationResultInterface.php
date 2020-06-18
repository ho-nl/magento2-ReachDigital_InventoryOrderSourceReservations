<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservationsApi\Api\Data;

/**
 * Result of how we will deduct product qty from different Sources
 *
 * @api
 */
interface SourceReservationResultInterface
{
    /**
     * @return \ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultItemInterface[]
     */
    public function getReservationItems(): array;

    public function getOrderId(): int;
}
