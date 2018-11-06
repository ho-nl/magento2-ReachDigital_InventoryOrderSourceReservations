<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Plugin\MagentoSales;

use Magento\Framework\Exception\InputException;
use Magento\Sales\Model\Order\Creditmemo;
use ReachDigital\ISReservations\Model\AppendReservations;
use ReachDigital\ISReservations\Model\ResourceModel\GetReservationsByMetadata;
use ReachDigital\ISReservationsApi\Model\ReservationInterface;
use ReachDigital\ISReservationsApi\Model\ReservationBuilderInterface;

class RevertSourceReservationsOnCreditBeforeShipment
{

    /**
     * @var GetReservationsByMetadata
     */
    private $getReservationsByMetadata;

    /**
     * @var ReservationBuilderInterface
     */
    private $reservationBuilder;

    /**
     * @var AppendReservations
     */
    private $appendReservations;

    public function __construct(
        GetReservationsByMetadata $getReservationsByMetadata,
        ReservationBuilderInterface $reservationBuilder,
        AppendReservations $appendReservations
    )
    {
        $this->getReservationsByMetadata = $getReservationsByMetadata;
        $this->reservationBuilder = $reservationBuilder;
        $this->appendReservations = $appendReservations;
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
     *
     * @param Creditmemo $creditmemo
     * @param \Closure   $proceed
     *
     * @return Creditmemo
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Validation\ValidationException
     */
    public function aroundAfterSave(
        Creditmemo $creditmemo,
        \Closure $proceed
    ): Creditmemo
    {
        $reservations = $this->getReservationsByMetadata->execute(sprintf('order:%s,order_item:', $creditmemo->getOrder()->getId()));
        $nullifications = [];

        // Iterate over items, find reservations
        /** @var Creditmemo\Item $item */
        foreach ($creditmemo->getItems() as $item) {
            $qtyToRevert = $item->getQty();

            if ($this->isZero($qtyToRevert)) {
                continue;
            }

            // Iterate over each assigned source, get summed reserved qty for that source
            // @todo: ensure we revert reservation in reverse order
            $reservedQtys = $this->sumReservedQtys($item, $reservations);
            foreach ($reservedQtys as $sourceCode => $reservedQty) {
                // Ensure we only revert a qty that was not higher than the reserved qty on this source
                $nullifyQty = min($qtyToRevert, - $reservedQty);
                if (!$this->isZero($nullifyQty)) {
                    $this->reservationBuilder->setSku($item->getSku());
                    $this->reservationBuilder->setQuantity($nullifyQty);
                    $this->reservationBuilder->setSourceCode($sourceCode);
                    $this->reservationBuilder->setMetadata(
                        sprintf('order:%s,order_item:%s', $creditmemo->getOrderId(), $item->getOrderItemId()));
                    $nullifications[] = $this->reservationBuilder->build();
                    $qtyToRevert -= $nullifyQty;
                } else {
                    break;
                }
            }

            // The quantity that cant be reverted by nullifying reservations must already have been shipped. Therefore
            // we need to add that qty (dependencing on the 'return_to_stock' checkbox) back to the source.

            // Magento by default will always add the qty's back to the source (when the checkbox is checked) when a
            // credit is created. So here we wont actually have to add back to the source, but prevent Magento from
            // adding back to the source.

            // ReturnProcessor processes returns, ProcessReturnQtyOnCreditMemoPlugin::aroundExecute plugs into this and
            // invokes \Magento\InventorySales\Model\ReturnProcessor\ProcessRefundItems::execute which will return qtys
            // to sources, or else add them back as stock reservations.
        }
        if (count($nullifications)) {
            $this->appendReservations->execute($nullifications);
        }

        return $proceed();
    }

    /**
     * Sum up reserved per-source qtys for item
     * @param                        $item
     * @param ReservationInterface[] $reservations
     *
     * @return float[]
     */
    private function sumReservedQtys(Creditmemo\Item $item, array &$reservations): array
    {
        $qtys = [];
        $orderItem = $item->getOrderItem();
        foreach ($reservations as $reservation) {
            if ($reservation->getMetadata() === sprintf('order:%s,order_item:%s', $orderItem->getOrderId(), $item->getOrderItem()->getId())) {
                $sourceCode = $reservation->getSourceCode();
                if (!isset($qtys[$sourceCode])) {
                    $qtys[$sourceCode] = 0;
                }
                $qtys[$sourceCode] += $reservation->getQuantity();
            }
        }
        return $qtys;
    }
}
