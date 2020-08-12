<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Plugin\MagentoInventorySales;

use Closure;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Validation\ValidationException;
use Magento\InventorySales\Model\GetItemsToCancelFromOrderItem;
use Magento\InventorySales\Observer\CatalogInventory\CancelOrderItemObserver;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Item as OrderItem;
use Psr\Log\LoggerInterface;
use ReachDigital\IOSReservations\Model\MagentoInventorySales\CancelOrderItems;
use ReachDigital\IOSReservations\Model\NullifySourceReservations;
use ReachDigital\ISReservations\Model\MetaData\DecodeMetaData;
use ReachDigital\ISReservationsApi\Api\Data\SourceReservationInterface;
use ReachDigital\ISReservationsApi\Api\EncodeMetaDataInterface;
use ReachDigital\ISReservationsApi\Api\GetReservationsByMetadataInterface;
use ReachDigital\ISReservationsApi\Model\AppendSourceReservationsInterface;
use ReachDigital\ISReservationsApi\Model\SourceReservationBuilderInterface;

class PreventSourceItemQuantityDeductionOnCancellation
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
     * @var CancelOrderItems
     */
    private $cancelOrderItems;
    /**
     * @var NullifySourceReservations
     */
    private $nullifySourceReservations;

    public function __construct(
        GetItemsToCancelFromOrderItem $getItemsToCancelFromOrderItem,
        CancelOrderItems $cancelOrderItems,
        LoggerInterface $logger,
        NullifySourceReservations $nullifySourceReservations
    ) {
        $this->logger = $logger;
        $this->getItemsToCancelFromOrderItem = $getItemsToCancelFromOrderItem;
        $this->cancelOrderItems = $cancelOrderItems;
        $this->nullifySourceReservations = $nullifySourceReservations;
    }

    /**
     * Around plugin to prevent reservation item quantity deduction when order is cancelled and order is already
     * assigned, can happen when assigning happens before invoicing, for instance when an order is authorised but not
     * yet captured.
     *
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws ValidationException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     *
     * todo: Replace the observer instead of creating a plugin on the observer
     */
    public function aroundExecute(CancelOrderItemObserver $subject, Closure $proceed, Observer $observer)
    {
        /** @var OrderItem $orderItem */
        $orderItem = $observer->getEvent()->getItem();
        $logger = $this->createLogger($orderItem->getOrder()->getId(), $orderItem->getItemId());

        $logger('cancellation_start');

        $itemsToCancel = $this->getItemsToCancelFromOrderItem->execute($orderItem);
        if (empty($itemsToCancel)) {
            return;
        }

        $logger('revert_source_start');
        $remainingItemsToCancel = $this->nullifySourceReservations->execute(
            (string) $orderItem->getOrderId(),
            $itemsToCancel
        );
        $logger('revert_source_end');

        if ($remainingItemsToCancel) {
            $logger('revert_stock_start');
            $this->cancelOrderItems->execute($remainingItemsToCancel, $orderItem);
            $logger('revert_stock_end');
        }

        $logger('cancellation_end');
    }

    private function createLogger($orderId, $orderItemId)
    {
        return function (string $stage) use ($orderId, $orderItemId) {
            $this->logger->info(
                json_encode([
                    'module' => 'reach-digital/magento2-order-source-reservations',
                    'order' => $orderId,
                    'order_item' => $orderItemId,
                    'stage' => $stage,
                ])
            );
        };
    }
}
