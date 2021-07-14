<?php

namespace ReachDigital\IOSReservationsCancellation\Model;

use Magento\InventorySales\Model\GetItemsToCancelFromOrderItem;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Config;
use Magento\Sales\Model\Order\Item as OrderItem;

class OrderNothingLeftToCancel
{
    /**
     * @var GetItemsToCancelFromOrderItem
     */
    private $getItemsToCancelFromOrderItem;
    /**
     * @var Config
     */
    private $orderConfig;

    public function __construct(GetItemsToCancelFromOrderItem $getItemsToCancelFromOrderItem, Config $orderConfig)
    {
        $this->getItemsToCancelFromOrderItem = $getItemsToCancelFromOrderItem;
        $this->orderConfig = $orderConfig;
    }

    /**
     * @param OrderInterface $order
     */
    public function execute(OrderInterface $order): bool
    {
        $allCancelled = true;
        foreach ($order->getItems() as /** @var OrderItem $item */ $item) {
            $toCancel = $this->getItemsToCancelFromOrderItem->execute($item);
            if (!empty($toCancel)) {
                $allCancelled = false;
            }
        }
        return $allCancelled;
    }
}
