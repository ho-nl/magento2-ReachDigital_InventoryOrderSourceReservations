<?php

namespace ReachDigital\IOSReservations\Model;

use Magento\Sales\Api\Data\OrderItemInterface;
use ReachDigital\ISReservationsApi\Api\DecodeMetaDataInterface;
use ReachDigital\ISReservationsApi\Api\EncodeMetaDataInterface;
use ReachDigital\ISReservationsApi\Api\GetReservationsByMetadataListInterface;

class AddSourceReservationsToOrderItems
{
    /**
     * @var EncodeMetaDataInterface
     */
    private $encodeMetaData;
    /**
     * @var DecodeMetaDataInterface
     */
    private $decodeMetaData;
    /**
     * @var GetReservationsByMetadataListInterface
     */
    private $getReservationsByMetadataList;

    public function __construct(
        EncodeMetaDataInterface $encodeMetaData,
        DecodeMetaDataInterface $decodeMetaData,
        GetReservationsByMetadataListInterface $getReservationsByMetadataList
    ) {
        $this->encodeMetaData = $encodeMetaData;
        $this->decodeMetaData = $decodeMetaData;
        $this->getReservationsByMetadataList = $getReservationsByMetadataList;
    }

    /**f
     * @param OrderItemInterface[] $orderItems
     */
    public function execute(array $orderItems): void
    {
        $matches = [];
        foreach ($orderItems as $orderItem) {
            $extensionAttributes = $orderItem->getExtensionAttributes();
            if ($extensionAttributes->getSourceReservations()) {
                continue;
            }
            $matches[] = $this->encodeMetaData->execute([
                'order' => $orderItem->getOrderId(),
                'order_item' => $orderItem->getItemId(),
            ]);
        }

        if (!$matches) {
            return;
        }

        $byMeta = [];
        foreach ($this->getReservationsByMetadataList->execute($matches) as $reservation) {
            if (!isset($byMeta[$reservation->getMetadata()])) {
                $byMeta[$reservation->getMetadata()] = [];
            }
            $byMeta[$reservation->getMetadata()][] = $reservation;
        }

        foreach ($orderItems as $orderItem) {
            $metaDataKey = $this->encodeMetaData->execute([
                'order' => $orderItem->getOrderId(),
                'order_item' => $orderItem->getItemId(),
            ]);
            if (isset($byMeta[$metaDataKey])) {
                $orderItem->getExtensionAttributes()->setSourceReservations($byMeta[$metaDataKey]);
            }
        }
    }
}
