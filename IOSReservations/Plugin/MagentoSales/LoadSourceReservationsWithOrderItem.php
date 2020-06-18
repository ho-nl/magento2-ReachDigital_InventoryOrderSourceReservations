<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Plugin\MagentoSales;

use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\Data\OrderItemSearchResultInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use ReachDigital\ISReservations\Model\MetaData\EncodeMetaData;
use ReachDigital\ISReservations\Model\ResourceModel\GetReservationsByMetadata;

class LoadSourceReservationsWithOrderItem
{
    /**
     * @var GetReservationsByMetadata
     */
    private $getReservationsByMetadata;

    /**
     * @var EncodeMetaData
     */
    private $encodeMetaData;

    public function __construct(GetReservationsByMetadata $getReservationsByMetadata, EncodeMetaData $encodeMetaData)
    {
        $this->getReservationsByMetadata = $getReservationsByMetadata;
        $this->encodeMetaData = $encodeMetaData;
    }

    public function afterGet(
        /** @noinspection PhpUnusedParameterInspection */
        OrderItemRepositoryInterface $subject,
        OrderItemInterface $orderItem
    ): OrderItemInterface {
        $extensionAttributes = $orderItem->getExtensionAttributes();
        $orderId = $orderItem->getOrderId();
        $orderItemId = $orderItem->getItemId();
        $reservations = $this->getReservationsByMetadata->execute(
            $this->encodeMetaData->execute(['order' => $orderId, 'order_item' => $orderItemId])
        );
        /** @noinspection NullPointerExceptionInspection - should always be set */
        $extensionAttributes->setSourceReservations($reservations);
        return $orderItem;
    }

    public function afterGetList(
        /** @noinspection PhpUnusedParameterInspection */
        OrderItemRepositoryInterface $subject,
        OrderItemSearchResultInterface $orderItemSearchResult
    ): OrderItemSearchResultInterface {
        foreach ($orderItemSearchResult->getItems() as $item) {
            $this->afterGet($subject, $item);
        }
        return $orderItemSearchResult;
    }
}
