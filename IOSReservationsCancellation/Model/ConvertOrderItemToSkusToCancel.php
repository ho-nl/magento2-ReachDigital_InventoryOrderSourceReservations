<?php

namespace ReachDigital\IOSReservationsCancellation\Model;

use Magento\InventorySales\Model\GetItemsToCancelFromOrderItem;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterface;
use Magento\Sales\Model\Order\Item as OrderItem;
use ReachDigital\IOSReservationsCancellationApi\Exception\OrderItemNoQuantityToCancel;

class ConvertOrderItemToSkusToCancel
{
    /**
     * @var GetItemsToCancelFromOrderItem
     */
    private $getItemsToCancelFromOrderItem;

    public function __construct(GetItemsToCancelFromOrderItem $getItemsToCancelFromOrderItem)
    {
        $this->getItemsToCancelFromOrderItem = $getItemsToCancelFromOrderItem;
    }

    /**
     * @return ItemToSellInterface[]
     * @throws OrderItemNoQuantityToCancel
     */
    public function execute(OrderItem $orderItem, float $quantityToCancel): array
    {
        $actualItems = $this->getItemsToCancelFromOrderItem->execute($orderItem);

        if (empty($actualItems)) {
            throw OrderItemNoQuantityToCancel::create($orderItem->getItemId(), $quantityToCancel, 0);
        }

        foreach ($actualItems as $actualItem) {
            if ($actualItem->getQuantity() < $quantityToCancel) {
                throw OrderItemNoQuantityToCancel::create(
                    $orderItem->getItemId(),
                    $quantityToCancel,
                    $actualItem->getQuantity()
                );
            }
            $actualItem->setQuantity($quantityToCancel);
        }

        return array_filter($actualItems, function ($item) {
            return $item->getQuantity() > 0;
        });
    }
}
