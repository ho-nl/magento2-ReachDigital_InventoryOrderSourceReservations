<?php

namespace ReachDigital\IOSReservationsCancellation\Model\Data;

use Magento\Framework\ObjectManagerInterface;
use ReachDigital\IOSReservationsCancellationApi\Api\Data\ItemToCancelInterface;

class ItemToCancelFactory
{
    /** @var ObjectManagerInterface */
    private $objectManager;

    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    public function create(int $itemId, float $quantity): ItemToCancelInterface
    {
        /** @var ItemToCancelInterface $itemToCancel */
        $itemToCancel = $this->objectManager->create(ItemToCancelInterface::class);

        $itemToCancel->setItemId($itemId);
        $itemToCancel->setQuantity($quantity);

        return $itemToCancel;
    }
}
