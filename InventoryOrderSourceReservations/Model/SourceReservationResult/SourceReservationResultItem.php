<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace ReachDigital\InventoryOrderSourceReservations\Model\SourceReservationResult;

use ReachDigital\InventoryOrderSourceReservationsApi\Api\Data\SourceReservationResultItemInterface;
use ReachDigital\InventorySourceReservationsApi\Model\ReservationInterface;

class SourceReservationResultItem implements SourceReservationResultItemInterface
{

    /**
     * @var ReservationInterface
     */
    private $reservation;

    /**
     * @var int
     */
    private $orderItemId;

    public function __construct(
        ReservationInterface $reservation,
        int $orderItemId
    ) {
        $this->reservation = $reservation;
        $this->orderItemId = $orderItemId;
    }

    public function getReservation(): ReservationInterface
    {
        return $this->reservation;
    }

    public function getOrderItemId(): int
    {
        return $this->orderItemId;
    }
}
