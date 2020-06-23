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
use ReachDigital\IOSReservations\Model\AddSourceReservationsToOrderItems;

class LoadSourceReservationsWithOrderItem
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
        OrderItemRepositoryInterface $subject,
        OrderItemInterface $orderItem
    ): OrderItemInterface {
        $this->addSourceReservationsToOrderItems->execute([$orderItem]);
        return $orderItem;
    }

    public function afterGetList(
        /** @noinspection PhpUnusedParameterInspection */
        OrderItemRepositoryInterface $subject,
        OrderItemSearchResultInterface $orderItemSearchResult
    ): OrderItemSearchResultInterface {
        $this->addSourceReservationsToOrderItems->execute($orderItemSearchResult->getItems());
        return $orderItemSearchResult;
    }
}
