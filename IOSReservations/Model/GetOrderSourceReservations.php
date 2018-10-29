<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Model;

use ReachDigital\IOSReservationsApi\Api\GetOrderSourceReservationsInterface;
use ReachDigital\ISReservations\Model\MetaData\DecodeMetaData;
use ReachDigital\ISReservations\Model\ResourceModel\GetReservationsByMetadata;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultInterface;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultInterfaceFactory;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultItemInterface;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultItemInterfaceFactory;
use ReachDigital\ISReservationsApi\Model\ReservationInterface;

class GetOrderSourceReservations implements GetOrderSourceReservationsInterface
{

    /**
     * @var GetReservationsByMetadata
     */
    private $getReservationsByMetadata;

    /**
     * @var SourceReservationResultInterfaceFactory
     */
    private $sourceReservationResultFactory;

    /**
     * @var SourceReservationResultItemInterfaceFactory
     */
    private $sourceReservationResultItemFactory;

    /**
     * @var DecodeMetaData
     */
    private $decodeMetaData;

    public function __construct(
        GetReservationsByMetadata $getReservationsByMetadata,
        SourceReservationResultInterfaceFactory $sourceReservationResultFactory,
        SourceReservationResultItemInterfaceFactory $sourceReservationResultItemFactory,
        DecodeMetaData $decodeMetaData
    ) {
        $this->getReservationsByMetadata = $getReservationsByMetadata;
        $this->sourceReservationResultFactory = $sourceReservationResultFactory;
        $this->sourceReservationResultItemFactory = $sourceReservationResultItemFactory;
        $this->decodeMetaData = $decodeMetaData;
    }

    /**
     * @param int $orderId
     * @return SourceReservationResultInterface
     */
    public function execute(int $orderId): ? SourceReservationResultInterface
    {
        $reservations = $this->getReservationsByMetadata->execute("order:{$orderId}");

        $resultItems = array_map(function(ReservationInterface $reservation): SourceReservationResultItemInterface {
            $metaData = $this->decodeMetaData->execute($reservation->getMetadata());

            return $this->sourceReservationResultItemFactory->create([
                'reservation' => $reservation,
                'orderItemId' => (int) $metaData['order_item']
            ]);
        }, $reservations);

        return $this->sourceReservationResultFactory->create([
            'reservationItems' => $resultItems,
            'orderId' => $orderId
        ]);
    }
}