<?php

namespace ReachDigital\IOSReservationsCancellation\Model;

use Magento\InventorySales\Model\GetItemsToCancelFromOrderItem;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;

class UpdateOrderCancelledTotalsAndState
{
    /**
     * @var GetItemsToCancelFromOrderItem
     */
    private $getItemsToCancelFromOrderItem;
    /**
     * @var Order\Config
     */
    private $orderConfig;

    public function __construct(
        GetItemsToCancelFromOrderItem $getItemsToCancelFromOrderItem,
        \Magento\Sales\Model\Order\Config $orderConfig
    ) {
        $this->getItemsToCancelFromOrderItem = $getItemsToCancelFromOrderItem;
        $this->orderConfig = $orderConfig;
    }

    /**
     * @see Order::registerCancellation
     */
    public function execute(OrderInterface $order)
    {
        $subtotalCancelled = 0;
        $baseSubtotalCancelled = 0;

        $taxCancelled = 0;
        $baseTaxCancelled = 0;

        $discountCancelled = 0;
        $baseDiscountCancelled = 0;

        foreach ($order->getItems() as $item) {
            $cancelledPct = $item->getQtyCanceled() / $item->getQtyOrdered();

            $subtotalCancelled += $item->getRowTotal() * $cancelledPct;
            $baseSubtotalCancelled += $item->getBaseRowTotal() * $cancelledPct;

            $taxCancelled += $item->getTaxAmount() * $cancelledPct;
            $baseTaxCancelled += $item->getBaseTaxAmount() * $cancelledPct;

            $discountCancelled += $item->getDiscountAmount() * $cancelledPct;
            $baseDiscountCancelled += $item->getBaseDiscountAmount() * $cancelledPct;
        }

        $order->setSubtotalCanceled($subtotalCancelled);
        $order->setBaseSubtotalCanceled($baseSubtotalCancelled);

        $order->setTaxCanceled($taxCancelled);
        $order->setBaseTaxCanceled($baseTaxCancelled);

        $order->setShippingCanceled($order->getShippingAmount() - $order->getShippingInvoiced());
        $order->setBaseShippingCanceled($order->getBaseShippingAmount() - $order->getBaseShippingInvoiced());

        $order->setDiscountCanceled($discountCancelled);
        $order->setBaseDiscountCanceled($baseDiscountCancelled);

        $order->setTotalCanceled($subtotalCancelled);
        $order->setBaseTotalCanceled($baseSubtotalCancelled);

        $this->updateOrderState($order);
    }

    /**
     * @param OrderInterface $order
     */
    private function updateOrderState(OrderInterface $order): void
    {
        $allCancelled = true;
        foreach ($order->getItems() as /** @var OrderItem $item */ $item) {
            $toCancel = $this->getItemsToCancelFromOrderItem->execute($item);
            if (!empty($toCancel)) {
                $allCancelled = false;
            }
        }
        if ($allCancelled) {
            $order->setState(Order::STATE_CANCELED);
            $order->setStatus($this->orderConfig->getStateDefaultStatus($order->getState()));
        }
    }
}
