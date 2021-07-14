<?php

namespace ReachDigital\IOSReservationsCancellation\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config;

class UpdateOrderCancelledState
{
    /**
     * @var Config
     */
    private $orderConfig;

    public function __construct(Config $orderConfig)
    {
        $this->orderConfig = $orderConfig;
    }

    /**
     * @throws LocalizedException
     */
    public function updateState(OrderInterface $order, string $comment = '')
    {
        if (!$order instanceof Order) {
            throw new LocalizedException(__('$order should be instance of %1', Order::class));
        }

        $state = Order::STATE_CANCELED;
        foreach ($order->getAllItems() as $item) {
            if ($item->getQtyOrdered() > $item->getQtyCanceled()) {
                $state = null;
                break;
            }
        }

        if ($state) {
            $order->setState($state)->setStatus($this->orderConfig->getStateDefaultStatus($state));
        }

        if (!empty($comment)) {
            $order->addCommentToStatusHistory($comment, false, false);
        }
    }
}
