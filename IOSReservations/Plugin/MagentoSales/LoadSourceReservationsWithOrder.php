<?php

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Plugin\MagentoSales;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use ReachDigital\IOSReservations\Model\AddSourceReservationsToOrderItems;

class LoadSourceReservationsWithOrder
{
    /**
     * @var AddSourceReservationsToOrderItems
     */
    private $addSourceReservationsToOrderItems;

    public function __construct(AddSourceReservationsToOrderItems $addSourceReservationsToOrderItems)
    {
        $this->addSourceReservationsToOrderItems = $addSourceReservationsToOrderItems;
    }

    public function afterGet(
        /** @noinspection PhpUnusedParameterInspection */
        OrderRepositoryInterface $subject,
        OrderInterface $order
    ): OrderInterface {
        $this->addSourceReservationsToOrderItems->execute($order->getItems());
        return $order;
    }

    public function afterGetList(
        /** @noinspection PhpUnusedParameterInspection */
        OrderRepositoryInterface $subject,
        OrderSearchResultInterface $orderSearchResult
    ): OrderSearchResultInterface {
        $orderItems = [];
        foreach ($orderSearchResult->getItems() as $order) {
            foreach ($order->getItems() as $item) {
                $orderItems[] = $item;
            }
        }
        $this->addSourceReservationsToOrderItems->execute($orderItems);
        return $orderSearchResult;
    }
}
