<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSource;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Validation\ValidationException;
use Magento\InventorySourceSelectionApi\Api\Data\SourceSelectionResultInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultInterface;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultInterfaceFactory;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultItemInterface;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultItemInterfaceFactory;
use ReachDigital\ISReservationsApi\Api\EncodeMetaDataInterface;
use ReachDigital\ISReservationsApi\Model\AppendSourceReservationsInterface;
use ReachDigital\ISReservationsApi\Model\SourceReservationBuilderInterface;

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
     * @var AppendSourceReservationsInterface
     */
    private $appendReservations;

    /**
     * @var SourceReservationBuilderInterface
     */
    private $reservationBuilder;

    /**
     * @var EncodeMetaDataInterface
     */
    private $encodeMetaData;

    public function __construct(
        AppendSourceReservationsInterface $appendReservations,
        SourceReservationResultInterfaceFactory $sourceReservationResultInterfaceFactory,
        SourceReservationResultItemInterfaceFactory $sourceReservationResultItemInterfaceFactory,
        SourceReservationBuilderInterface $reservationBuilder,
        EncodeMetaDataInterface $encodeMetaData
    ) {
        $this->sourceReservationResultFactory = $sourceReservationResultInterfaceFactory;
        $this->sourceReservationResultItemFactory = $sourceReservationResultItemInterfaceFactory;
        $this->appendReservations = $appendReservations;
        $this->reservationBuilder = $reservationBuilder;
        $this->encodeMetaData = $encodeMetaData;
    }

    /**
     * @param OrderInterface                 $order
     * @param SourceSelectionResultInterface $sourceSelectionResult
     *
     * @return SourceReservationResultInterface
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws ValidationException
     \     */
    public function execute(
        OrderInterface $order,
        SourceSelectionResultInterface $sourceSelectionResult
    ): SourceReservationResultInterface {
        $result = $this->toOrderLinkObject($order, $sourceSelectionResult);

        $reservations = array_map(function (SourceReservationResultItemInterface $item) {
            return $item->getReservation();
        }, $result->getReservationItems());

        if (!$reservations) {
            return $result;
        }

        $this->appendReservations->execute($reservations);

        return $result;
    }

    /**
     * Compare float number with some epsilon
     * @param float $floatNumber
     * @return bool
     */
    private function isZeroOrLess(float $floatNumber): bool
    {
        return $floatNumber < 0.0000001;
    }

    /**
     * @param OrderInterface                 $order
     * @param SourceSelectionResultInterface $sourceSelectionResult
     *
     * @return mixed
     * @throws ValidationException
     */
    private function toOrderLinkObject(
        OrderInterface $order,
        SourceSelectionResultInterface $sourceSelectionResult
    ): SourceReservationResultInterface {
        $resultItems = [];
        $ssItems = $sourceSelectionResult->getSourceSelectionItems();
        /** @var Order\Item $orderItem */
        foreach ($order->getItems() as $orderItem) {
            $qtyToDeliver = $orderItem->getQtyToShip();
            //check if order item is not delivered yet
            if (
                $orderItem->isDeleted() ||
                $orderItem->getParentItemId() ||
                $this->isZeroOrLess((float) $qtyToDeliver) ||
                $orderItem->getIsVirtual()
            ) {
                continue;
            }
            foreach ($ssItems as $k => $ssItem) {
                if ($ssItem->getSku() !== $orderItem->getSku()) {
                    continue;
                }
                $resultItems[] = $this->sourceReservationResultItemFactory->create([
                    'reservation' => $this->reservationBuilder
                        ->setSku($ssItem->getSku())
                        ->setQuantity($ssItem->getQtyToDeduct() * -1)
                        ->setSourceCode($ssItem->getSourceCode())
                        ->setMetadata(
                            $this->encodeMetaData->execute([
                                'order' => $order->getEntityId(),
                                'order_item' => $orderItem->getItemId(),
                            ])
                        )
                        ->build(),
                    'orderItemId' => (int) $orderItem->getItemId(),
                ]);
                $qtyToDeliver -= $ssItem->getQtyToDeduct();
                unset($ssItems[$k]);
                if ($this->isZeroOrLess($qtyToDeliver)) {
                    break;
                }
            }
        }
        return $this->sourceReservationResultFactory->create([
            'reservationItems' => $resultItems,
            'orderId' => (int) $order->getEntityId(),
        ]);
    }
}
