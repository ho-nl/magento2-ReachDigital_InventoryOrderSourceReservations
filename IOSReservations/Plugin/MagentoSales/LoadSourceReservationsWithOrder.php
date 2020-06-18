<?php /** @noinspection PhpUnusedParameterInspection */
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Plugin\MagentoSales;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use ReachDigital\ISReservations\Model\MetaData\EncodeMetaData;
use ReachDigital\ISReservations\Model\ResourceModel\GetReservationsByMetadata;

class LoadSourceReservationsWithOrder
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

    public function afterGet($subject, OrderInterface $order): OrderInterface
    {
        // @todo load source reservations in one go (but in batches, due to complex ON-clause for joining on metadata)
        foreach ($order->getItems() as $orderItem) {
            $extensionAttributes = $orderItem->getExtensionAttributes();
            $orderId = $orderItem->getOrderId();
            $orderItemId = $orderItem->getItemId();
            $reservations = $this->getReservationsByMetadata->execute(
                $this->encodeMetaData->execute(['order' => $orderId, 'order_item' => $orderItemId])
            );
            /** @noinspection NullPointerExceptionInspection - should always be set */
            $extensionAttributes->setSourceReservations($reservations);
        }
        return $order;
    }

    public function afterGetList(
        OrderRepositoryInterface $subject,
        OrderSearchResultInterface $orderSearchResult
    ): OrderSearchResultInterface {
        foreach ($orderSearchResult->getItems() as $order) {
            foreach ($order->getItems() as $item) {
                $extensionAttributes = $item->getExtensionAttributes();
                $orderId = $item->getOrderId();
                $orderItemId = $item->getItemId();
                $reservations = $this->getReservationsByMetadata->execute(
                    $this->encodeMetaData->execute(['order' => $orderId, 'order_item' => $orderItemId])
                );
                /** @noinspection NullPointerExceptionInspection - should always be set */
                $extensionAttributes->setSourceReservations($reservations);
            }
        }

        return $orderSearchResult;
    }
}
