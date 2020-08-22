<?php

namespace ReachDigital\IOSReservations\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Validation\ValidationException;
use Magento\InventorySales\Model\GetItemsToCancelFromOrderItem;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterface;
use Psr\Log\LoggerInterface;

class CancelStockAndSourceReservations
{
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var GetItemsToCancelFromOrderItem
     */
    private $getItemsToCancelFromOrderItem;
    /**
     * @var CancelStockReservations
     */
    private $cancelStockReservations;
    /**
     * @var CancelSourceReservations
     */
    private $cancelSourceReservations;

    public function __construct(
        GetItemsToCancelFromOrderItem $getItemsToCancelFromOrderItem,
        CancelStockReservations $cancelStockReservations,
        CancelSourceReservations $cancelSourceReservations,
        LoggerInterface $logger
    ) {
        $this->getItemsToCancelFromOrderItem = $getItemsToCancelFromOrderItem;
        $this->cancelStockReservations = $cancelStockReservations;
        $this->cancelSourceReservations = $cancelSourceReservations;
        $this->logger = $logger;
    }

    /**
     * @param ItemToSellInterface[] $itemsToNullify
     * @return ItemToSellInterface[]
     *
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws ValidationException
     */
    public function execute(int $orderId, array $itemsToNullify): array
    {
        $itemsToNullify = $this->cancelStockReservations->execute($orderId, $itemsToNullify);
        $itemsToNullify = $this->cancelSourceReservations->execute($orderId, $itemsToNullify);

        $this->logger->warning('remaining_items_to_cancel', [
            'module' => 'reach-digital/magento2-order-source-reservations',
            'order' => $orderId,
            'items' => array_map(function ($item) {
                return [$item->getSku(), $item->getQuantity()];
            }, $itemsToNullify),
        ]);

        return $itemsToNullify;
    }
}
