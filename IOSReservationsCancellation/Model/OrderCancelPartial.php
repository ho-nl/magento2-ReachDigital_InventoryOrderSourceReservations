<?php

namespace ReachDigital\IOSReservationsCancellation\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Validation\ValidationException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
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

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        CancelStockAndSourceReservations $revertStockAndSourceReservations,
        ConvertOrderItemToSkusToCancel $convertToSkusToCancel
    ) {
        $this->orderRepository = $orderRepository;
        $this->revertStockAndSourceReservations = $revertStockAndSourceReservations;
        $this->convertToSkusToCancel = $convertToSkusToCancel;
    }

    /**
     * @param int $orderId
     * @param ItemToCancelInterface[] $itemsToCancel
     * @param bool $sendEmail
     *
     * @throws LocalizedException
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws ValidationException
     * @throws OrderItemNoQuantityToCancel
     * @throws OrderItemNotExists
     */
    public function execute(int $orderId, array $itemsToCancel, bool $sendEmail = false): void
    {
        $order = $this->orderRepository->get($orderId);

        // @see Order::registerCancellation
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

            // Update the order totals when cancelled
        }

        //Cancel complete order when nothing left to ship or cancel

        //Send email upon full cancellation
    }

    /**
     * @throws OrderItemNotExists
     */
    private function getOrderItemById(OrderInterface $order, int $itemId): OrderItem
    {
        foreach ($order->getItems() as /* @var OrderItem $item */ $item) {
            if ((int) $item->getItemId() === $itemId && $order instanceof OrderItem) {
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
        $item->setQtyCanceled($item->getQtyCanceled() + $itemToCancel->getQuantity());
        $item->setTaxCanceled(
            $item->getTaxCanceled() + ($item->getBaseTaxAmount() * $itemToCancel) / $item->getQtyOrdered()
        );
        $item->setDiscountTaxCompensationCanceled(
            $item->getDiscountTaxCompensationCanceled() +
                ($item->getDiscountTaxCompensationAmount() * $itemToCancel) / $item->getQtyOrdered()
        );

        $order = $item->getOrder();

        $order->setSubtotalCanceled($order->getSubtotal() - $order->getSubtotalInvoiced());
        $order->setBaseSubtotalCanceled($order->getBaseSubtotal() - $order->getBaseSubtotalInvoiced());

        $order->setTaxCanceled($order->getTaxAmount() - $order->getTaxInvoiced());
        $order->setBaseTaxCanceled($order->getBaseTaxAmount() - $order->getBaseTaxInvoiced());

        $order->setShippingCanceled($order->getShippingAmount() - $order->getShippingInvoiced());
        $order->setBaseShippingCanceled($order->getBaseShippingAmount() - $order->getBaseShippingInvoiced());

        $order->setDiscountCanceled(abs($order->getDiscountAmount()) - $order->getDiscountInvoiced());
        $order->setBaseDiscountCanceled(abs($order->getBaseDiscountAmount()) - $order->getBaseDiscountInvoiced());

        $order->setTotalCanceled($order->getGrandTotal() - $order->getTotalPaid());
        $order->setBaseTotalCanceled($order->getBaseGrandTotal() - $order->getBaseTotalPaid());

        $item->getPrice();
        $item->getPriceInclTax();
        $item->getRowTotal();
        $item->getBaseRowTotal();
        $item->getBaseRowTotalInclTax();
        $item->getRowTotalInclTax();
    }
}
