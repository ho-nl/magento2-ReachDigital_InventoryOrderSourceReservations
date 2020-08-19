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
use ReachDigital\IOSReservationsApi\Api\NullifyStockAndSourceReservationsInterface;

class NullifyStockAndSourceReservations implements NullifyStockAndSourceReservationsInterface
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
     * @var NullifyStockReservations
     */
    private $nullifyStockReservations;
    /**
     * @var NullifySourceReservations
     */
    private $nullifySourceReservations;

    public function __construct(
        GetItemsToCancelFromOrderItem $getItemsToCancelFromOrderItem,
        NullifyStockReservations $nullifyStockReservations,
        LoggerInterface $logger,
        NullifySourceReservations $nullifySourceReservations
    ) {
        $this->logger = $logger;
        $this->getItemsToCancelFromOrderItem = $getItemsToCancelFromOrderItem;
        $this->nullifyStockReservations = $nullifyStockReservations;
        $this->nullifySourceReservations = $nullifySourceReservations;
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
        $itemsToNullify = $this->nullifyStockReservations->execute($orderId, $itemsToNullify);
        $itemsToNullify = $this->nullifySourceReservations->execute($orderId, $itemsToNullify);

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
