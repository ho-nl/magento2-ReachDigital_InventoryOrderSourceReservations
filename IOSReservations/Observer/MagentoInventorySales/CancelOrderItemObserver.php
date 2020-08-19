<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Observer\MagentoInventorySales;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\InventorySales\Model\GetItemsToCancelFromOrderItem;
use Magento\Sales\Model\Order\Item as OrderItem;
use ReachDigital\IOSReservationsApi\Api\NullifyStockAndSourceReservationsInterface;

class CancelOrderItemObserver implements ObserverInterface
{
    /**
     * @var GetItemsToCancelFromOrderItem
     */
    private $getItemsToCancelFromOrderItem;
    /**
     * @var NullifyStockAndSourceReservationsInterface
     */
    private $nullifyStockAndSourceReservations;

    public function __construct(
        GetItemsToCancelFromOrderItem $getItemsToCancelFromOrderItem,
        NullifyStockAndSourceReservationsInterface $nullifyStockAndSourceReservations
    ) {
        $this->getItemsToCancelFromOrderItem = $getItemsToCancelFromOrderItem;
        $this->nullifyStockAndSourceReservations = $nullifyStockAndSourceReservations;
    }

    /**
     * When cancelling a complete order line:
     * 1. Get the items that need to be cancelled.
     * 2. Nullify the items that need to be cancelled.
     */
    public function execute(Observer $observer)
    {
        /** @var OrderItem $orderItem */
        $orderItem = $observer->getEvent()->getData('item');
        $itemsToCancel = $this->getItemsToCancelFromOrderItem->execute($orderItem);
        if (empty($itemsToCancel)) {
            return;
        }
        $this->nullifyStockAndSourceReservations->execute((string) $orderItem->getOrderId(), $itemsToCancel);
    }
}
