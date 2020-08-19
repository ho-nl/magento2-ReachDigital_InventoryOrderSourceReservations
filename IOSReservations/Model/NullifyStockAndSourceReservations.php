<?php

namespace ReachDigital\IOSReservations\Model;

use Magento\InventorySales\Model\GetItemsToCancelFromOrderItem;
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
     * @inheritDoc
     */
    public function execute(string $orderId, array $itemsToNullify): void
    {
        $remainingItemsToCancel = $this->nullifySourceReservations->execute((string) $orderId, $itemsToNullify);
        if ($remainingItemsToCancel) {
            $this->nullifyStockReservations->execute($orderId, $remainingItemsToCancel);
        }
    }
}
