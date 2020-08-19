<?php

namespace ReachDigital\IOSReservations\Model;

use ReachDigital\ISReservationsApi\Api\EncodeMetaDataInterface;
use ReachDigital\ISReservationsApi\Api\GetReservationsByMetadataInterface;
use ReachDigital\ISReservationsApi\Model\AppendSourceReservationsInterface;
use ReachDigital\ISReservationsApi\Model\SourceReservationBuilderInterface;

class GetOrderSourceReservationQuantityBySkuAndSource
{
    /**
     * @var GetReservationsByMetadataInterface
     */
    private $getReservationsByMetadata;
    /**
     * @var EncodeMetaDataInterface
     */
    private $encodeMetaData;
    /**
     * @var AppendSourceReservationsInterface
     */
    private $appendSourceReservations;
    /**
     * @var SourceReservationBuilderInterface
     */
    private $sourceReservationBuilder;

    public function __construct(
        GetReservationsByMetadataInterface $getReservationsByMetadata,
        EncodeMetaDataInterface $encodeMetaData,
        AppendSourceReservationsInterface $appendSourceReservations,
        SourceReservationBuilderInterface $sourceReservationBuilder
    ) {
        $this->getReservationsByMetadata = $getReservationsByMetadata;
        $this->encodeMetaData = $encodeMetaData;
        $this->appendSourceReservations = $appendSourceReservations;
        $this->sourceReservationBuilder = $sourceReservationBuilder;
    }

    /**
     * @return array
     */
    public function execute(int $orderId)
    {
        $reservations = $this->getReservationsByMetadata->execute(
            $this->encodeMetaData->execute(['order' => $orderId])
        );

        $reservationsBySkuAndSource = [];
        foreach ($reservations as $reservation) {
            $sku = $reservation->getSku();
            $sourceCode = $reservation->getSourceCode();

            $reservationsBySkuAndSource[$sku] = $reservationsBySkuAndSource[$sku] ?? [];
            $reservationsBySkuAndSource[$sku][$sourceCode] = $reservationsBySkuAndSource[$sku][$sourceCode] ?? 0;
            $reservationsBySkuAndSource[$sku][$sourceCode] += $reservation->getQuantity();
        }

        return $reservationsBySkuAndSource;
    }
}
