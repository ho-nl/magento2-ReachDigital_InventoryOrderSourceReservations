<?php

namespace ReachDigital\IOSReservationsCancellation\Model\Data;

use Magento\Framework\Model\AbstractExtensibleModel;
use ReachDigital\IOSReservationsCancellationApi\Api\Data\ItemToCancelInterface;
use ReachDigital\IOSReservationsCancellationApi\Api\Data\ItemToCancelExtensionInterface;

class ItemToCancel extends AbstractExtensibleModel implements ItemToCancelInterface
{
    public function getItemId(): int
    {
        return $this->getData(ItemToCancelInterface::ITEM_ID);
    }

    public function setItemId(int $id): void
    {
        $this->setData(ItemToCancelInterface::ITEM_ID);
    }

    public function getQuantity(): float
    {
        return $this->getData(ItemToCancelInterface::QUANTITY);
    }

    public function setQuantity(float $quantity): void
    {
        $this->setData(ItemToCancelInterface::QUANTITY);
    }

    /**
     * @inheritdoc
     */
    public function getExtensionAttributes(): ?ItemToCancelExtensionInterface
    {
        $extensionAttributes = $this->_getExtensionAttributes();
        if (null === $extensionAttributes) {
            /** @var ItemToCancelExtensionInterface $extensionAttributes */
            $extensionAttributes = $this->extensionAttributesFactory->create(ItemToCancelInterface::class);
            $this->setExtensionAttributes($extensionAttributes);
        }
        return $extensionAttributes;
    }

    /**
     * @inheritdoc
     */
    public function setExtensionAttributes(ItemToCancelExtensionInterface $extensionAttributes): void
    {
        $this->_setExtensionAttributes($extensionAttributes);
    }
}
