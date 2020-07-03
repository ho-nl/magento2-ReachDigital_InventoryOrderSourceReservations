<?php

namespace ReachDigital\IOSReservations\Model;

use Magento\Sales\Api\Data\OrderItemInterface;
use ReachDigital\ISReservations\Model\MetaData\DecodeMetaData;
use ReachDigital\ISReservations\Model\MetaData\EncodeMetaData;
use ReachDigital\ISReservations\Model\ResourceModel\GetReservationsByMetadataList;

class AddSourceReservationsToOrderItems
{
    /**
     * @var EncodeMetaData
     */
    private $encodeMetaData;
    /**
     * @var GetReservationsByMetadataList
     */
    private $getReservationsByMetadataList;
    /**
     * @var DecodeMetaData
     */
    private $decodeMetaData;

    public function __construct(
        EncodeMetaData $encodeMetaData,
        DecodeMetaData $decodeMetaData,
        GetReservationsByMetadataList $getReservationsByMetadataList
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
