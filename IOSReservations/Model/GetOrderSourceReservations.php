<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Model;

use ReachDigital\IOSReservationsApi\Api\GetOrderSourceReservationsInterface;
use ReachDigital\ISReservations\Model\MetaData\DecodeMetaData;
use ReachDigital\ISReservations\Model\MetaData\EncodeMetaData;
use ReachDigital\ISReservations\Model\ResourceModel\GetReservationsByMetadata;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultInterface;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultInterfaceFactory;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultItemInterface;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultItemInterfaceFactory;
use ReachDigital\ISReservationsApi\Model\SourceReservationInterface;

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

    /**
     * @var EncodeMetaData
     */
    private $encodeMetaData;

    public function __construct(
        GetReservationsByMetadata $getReservationsByMetadata,
        SourceReservationResultInterfaceFactory $sourceReservationResultFactory,
        SourceReservationResultItemInterfaceFactory $sourceReservationResultItemFactory,
        DecodeMetaData $decodeMetaData,
        EncodeMetaData $encodeMetaData
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
    public function execute(int $orderId): ? SourceReservationResultInterface
    {
        $reservations = $this->getReservationsByMetadata->execute(
            $this->encodeMetaData->execute([ 'order' => $orderId]));

        $resultItems = array_map(function(SourceReservationInterface $reservation): SourceReservationResultItemInterface {
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
