<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Model\SourceReservationResult;

use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultInterface;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultItemInterface;

class SourceReservationResult implements SourceReservationResultInterface
{
    /** @var array */
    private $reservationItems;

    /** @var int */
    private $orderId;

    /**
     * SourceReservationResult constructor.
     *
     * @param array $reservationItems
     * @param int   $orderId
     *
     * @throws \LogicException
     */
    public function __construct(
        array $reservationItems,
        int $orderId
    ) {
        $this->orderId = $orderId;
        foreach ($reservationItems as $reservationItem) {
            if (! $reservationItem instanceof SourceReservationResultItemInterface) {
                throw new \LogicException(__('Item must be instance of SourceReservationResultItemInterface'));
            }
            $this->reservationItems[] = $reservationItem;
        }
    }

    /**
     * @inheritdoc
     */
    public function getReservationItems(): array
    {
        return $this->reservationItems;
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }
}
