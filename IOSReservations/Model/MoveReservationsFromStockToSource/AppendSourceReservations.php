<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSource;

use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventorySourceSelectionApi\Api\Data\SourceSelectionResultInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultInterface;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultInterfaceFactory;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultItemInterface;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultItemInterfaceFactory;
use ReachDigital\ISReservationsApi\Model\AppendReservationsInterface;
use ReachDigital\ISReservationsApi\Model\ReservationBuilderInterface;

class AppendSourceReservations
{
    /**
     * @var SourceReservationResultInterfaceFactory
     */
    private $sourceReservationResultFactory;

    /**
     * @var SourceReservationResultItemInterfaceFactory
     */
    private $sourceReservationResultItemFactory;

    /**
     * @var AppendReservationsInterface
     */
    private $appendReservations;

    /**
     * @var ReservationBuilderInterface
     */
    private $reservationBuilder;

    /**
     * @var GetSkusByProductIdsInterface
     */
    private $getSkusByProductIds;

    public function __construct(
        AppendReservationsInterface $appendReservations,
        SourceReservationResultInterfaceFactory $sourceReservationResultInterfaceFactory,
        SourceReservationResultItemInterfaceFactory $sourceReservationResultItemInterfaceFactory,
        ReservationBuilderInterface $reservationBuilder
    ) {
        $this->sourceReservationResultFactory = $sourceReservationResultInterfaceFactory;
        $this->sourceReservationResultItemFactory = $sourceReservationResultItemInterfaceFactory;
        $this->appendReservations = $appendReservations;
        $this->reservationBuilder = $reservationBuilder;
    }

    /**
     * @param OrderInterface                 $order
     * @param SourceSelectionResultInterface $sourceSelectionResult
     *
     * @return SourceReservationResultInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Validation\ValidationException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(
        OrderInterface $order,
        SourceSelectionResultInterface $sourceSelectionResult
    ) : SourceReservationResultInterface {

        $result = $this->toOrderLinkObject($order, $sourceSelectionResult);

        $reservations = array_map(function(SourceReservationResultItemInterface $item) {
            return $item->getReservation();
        }, $result->getReservationItems());

        $this->appendReservations->execute($reservations);

        return $result;
    }

    /**
     * Compare float number with some epsilon
     * @param float $floatNumber
     * @return bool
     */
    private function isZero(float $floatNumber): bool
    {
        return $floatNumber < 0.0000001;
    }

    /**
     * @param OrderInterface                 $order
     * @param SourceSelectionResultInterface $sourceSelectionResult
     *
     * @return mixed
     * @throws \Magento\Framework\Validation\ValidationException
     */
    private function toOrderLinkObject(
        OrderInterface $order,
        SourceSelectionResultInterface $sourceSelectionResult
    ): SourceReservationResultInterface {
        $resultItems = [];
        $ssItems     = $sourceSelectionResult->getSourceSelectionItems();
        /** @var Order\Item $orderItem */
        foreach ($order->getItems() as $orderItem) {
            $qtyToDeliver = $orderItem->getQtyToShip();
            //check if order item is not delivered yet
            if ($orderItem->isDeleted()
                || $orderItem->getParentItemId()
                || $this->isZero((float)$qtyToDeliver)
                || $orderItem->getIsVirtual()
            ) {
                continue;
            }
            foreach ($ssItems as $k => $ssItem) {
                $resultItems[] = $this->sourceReservationResultItemFactory->create([
                    'reservation' => $this->reservationBuilder
                        ->setSku($ssItem->getSku())
                        ->setQuantity($ssItem->getQtyToDeduct() * -1)
                        ->setSourceCode($ssItem->getSourceCode())
                        ->setMetadata("order:{$order->getEntityId()},order_item:{$orderItem->getItemId()}")
                        ->build(),
                    'orderItemId' => (int)$orderItem->getItemId()
                ]);
                $qtyToDeliver -= $ssItem->getQtyToDeduct();
                unset($ssItems[$k]);
                if ($this->isZero($qtyToDeliver)) {
                    break;
                }
            }
        }
        return $this->sourceReservationResultFactory->create([
            'reservationItems' => $resultItems,
            'orderId' => (int)$order->getEntityId()
        ]);
}
}
