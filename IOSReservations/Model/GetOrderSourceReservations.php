<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Model;

use ReachDigital\IOSReservationsApi\Api\GetOrderSourceReservationsInterface;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultInterface;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultInterfaceFactory;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultItemInterface;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultItemInterfaceFactory;
use ReachDigital\ISReservationsApi\Api\Data\SourceReservationInterface;
use ReachDigital\ISReservationsApi\Api\DecodeMetaDataInterface;
use ReachDigital\ISReservationsApi\Api\EncodeMetaDataInterface;
use ReachDigital\ISReservationsApi\Api\GetReservationsByMetadataInterface;

class GetOrderSourceReservations implements GetOrderSourceReservationsInterface
{
    /**
     * @var GetReservationsByMetadataInterface
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
     * @var DecodeMetaDataInterface
     */
    private $decodeMetaData;

    /**
     * @var EncodeMetaDataInterface
     */
    private $encodeMetaData;

    public function __construct(
        GetReservationsByMetadataInterface $getReservationsByMetadata,
        SourceReservationResultInterfaceFactory $sourceReservationResultFactory,
        SourceReservationResultItemInterfaceFactory $sourceReservationResultItemFactory,
        DecodeMetaDataInterface $decodeMetaData,
        EncodeMetaDataInterface $encodeMetaData
    ) {
        $this->getReservationsByMetadata = $getReservationsByMetadata;
        $this->sourceReservationResultFactory = $sourceReservationResultFactory;
        $this->sourceReservationResultItemFactory = $sourceReservationResultItemFactory;
        $this->decodeMetaData = $decodeMetaData;
        $this->encodeMetaData = $encodeMetaData;
    }

    /**
     * @param int $orderId
     * @return SourceReservationResultInterface
     */
    public function execute(int $orderId): ?SourceReservationResultInterface
    {
        $reservations = $this->getReservationsByMetadata->execute(
            $this->encodeMetaData->execute(['order' => $orderId])
        );

        $resultItems = array_map(function (
            SourceReservationInterface $reservation
        ): SourceReservationResultItemInterface {
            $metaData = $this->decodeMetaData->execute($reservation->getMetadata());

            return $this->sourceReservationResultItemFactory->create([
                'reservation' => $reservation,
                'orderItemId' => (int) $metaData['order_item'],
            ]);
        },
        $reservations);

        return $this->sourceReservationResultFactory->create([
            'reservationItems' => $resultItems,
            'orderId' => $orderId,
        ]);
    }
}
