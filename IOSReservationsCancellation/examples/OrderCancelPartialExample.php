<?php

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use ReachDigital\IOSReservationsCancellation\Model\Data\ItemToCancelFactory;
use ReachDigital\IOSReservationsCancellationApi\Api\OrderCancelPartialInterface;

class OrderCancelPartialExample
{
    /** @var OrderRepositoryInterface */
    private $orderRepository;
    /**
     * @var OrderCancelPartialInterface
     */
    private $orderCancelPartial;
    /**
     * @var ItemToCancelFactory
     */
    private $itemToCancelFactory;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        OrderCancelPartialInterface $orderCancelPartial,
        ItemToCancelFactory $itemToCancelFactory
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderCancelPartial = $orderCancelPartial;
        $this->itemToCancelFactory = $itemToCancelFactory;
    }

    public function execute()
    {
        $order = $this->orderRepository->get(1234);

        $orderId = $order->getEntityId();
        $orderItems = $order->getItems();
        $itemId1 = current($orderItems)->getItemId();
        $itemId2 = next($orderItems)->getItemId();

        $this->orderCancelPartial->execute($orderId, [
            $this->itemToCancelFactory->create($itemId1, 1),
            $this->itemToCancelFactory->create($itemId2, 1),
        ]);

        if ($order->getState() == Order::STATE_CANCELED) {
            // Send special cancellation email
        }
    }
}
