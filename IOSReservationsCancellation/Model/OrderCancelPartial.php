<?php

namespace ReachDigital\IOSReservationsCancellation\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Validation\ValidationException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use ReachDigital\IOSReservations\Model\CancelStockAndSourceReservations;
use ReachDigital\IOSReservationsCancellationApi\Api\Data\ItemToCancelInterface;
use ReachDigital\IOSReservationsCancellationApi\Api\OrderCancelPartialInterface;
use ReachDigital\IOSReservationsCancellationApi\Exception\OrderItemNoQuantityToCancel;
use ReachDigital\IOSReservationsCancellationApi\Exception\OrderItemNotExists;

class OrderCancelPartial implements OrderCancelPartialInterface
{
    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var CancelStockAndSourceReservations */
    private $revertStockAndSourceReservations;

    /** @var ConvertOrderItemToSkusToCancel */
    private $convertToSkusToCancel;

    /** @var UpdateOrderCancelledTotalsAndState */
    private $updateOrderCancelledTotalsAndState;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        CancelStockAndSourceReservations $revertStockAndSourceReservations,
        ConvertOrderItemToSkusToCancel $convertToSkusToCancel,
        UpdateOrderCancelledTotalsAndState $updateOrderCancelledTotalsAndState
    ) {
        $this->orderRepository = $orderRepository;
        $this->revertStockAndSourceReservations = $revertStockAndSourceReservations;
        $this->convertToSkusToCancel = $convertToSkusToCancel;
        $this->updateOrderCancelledTotalsAndState = $updateOrderCancelledTotalsAndState;
    }

    /**
     * @param ItemToCancelInterface[] $itemsToCancel
     *
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws OrderItemNoQuantityToCancel
     * @throws OrderItemNotExists
     * @throws ValidationException
     *
     * @see Order::registerCancellation
     */
    public function execute(int $orderId, array $itemsToCancel, bool $sendEmail = false): void
    {
        $order = $this->orderRepository->get($orderId);
        $this->assertCanCancelOrder($order);

        // Get the actual order item and convert to the underlying non-complex sku (get the simple of the configurable)
        foreach ($itemsToCancel as $itemToCancel) {
            $orderItem = $this->getOrderItemById($order, $itemToCancel->getItemId());

            // Get simple items to cancel
            $skusToCancel = $this->convertToSkusToCancel->execute($orderItem, $itemToCancel->getQuantity());

            // Revert stock and source reservations if available
            $this->revertStockAndSourceReservations->execute($orderId, $skusToCancel);

            // Update the qtyCancelled on the order item
            $this->updateItemState($orderItem, $itemToCancel);
        }
        $this->updateOrderCancelledTotalsAndState->execute($order);
        $this->orderRepository->save($order);
    }

    /**
     * @throws OrderItemNotExists
     */
    private function getOrderItemById(OrderInterface $order, int $itemId): OrderItem
    {
        foreach ($order->getItems() as /* @var OrderItem $item */ $item) {
            if (((int) $item->getItemId()) === $itemId && $item instanceof OrderItem) {
                return $item;
            }
        }

        throw OrderItemNotExists::create($order->getEntityId(), $itemId);
    }

    /**
     * @throws LocalizedException
     */
    private function assertCanCancelOrder(OrderInterface $order): void
    {
        $canCancel = $order->canCancel() || $order->isPaymentReview() || $order->isFraudDetected();
        if (!$canCancel) {
            throw new LocalizedException(__("Order can't be cancelled"));
        }
    }

    private function updateItemState(OrderItem $item, ItemToCancelInterface $itemToCancel): void
    {
        $this->setQuantityCancelled($item, $itemToCancel);
        foreach ($item->getChildrenItems() as $item) {
            $this->setQuantityCancelled($item, $itemToCancel);
        }
    }

    private function setQuantityCancelled(OrderItem $item, ItemToCancelInterface $itemToCancel)
    {
        $item->setQtyCanceled($item->getQtyCanceled() + $itemToCancel->getQuantity());
        $item->setTaxCanceled(
            $item->getTaxCanceled() +
                ($item->getBaseTaxAmount() * $itemToCancel->getQuantity()) / $item->getQtyOrdered()
        );
        $item->setDiscountTaxCompensationCanceled(
            $item->getDiscountTaxCompensationCanceled() +
                ($item->getDiscountTaxCompensationAmount() * $itemToCancel->getQuantity()) / $item->getQtyOrdered()
        );
    }
}
