<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Plugin\MagentoInventorySales;

use Magento\Framework\Exception\InputException;
use Magento\InventorySales\Model\ReturnProcessor\ProcessRefundItems;
use Magento\InventorySales\Model\ReturnProcessor\Request\ItemsToRefund;
use Magento\InventorySalesApi\Model\ReturnProcessor\Request\ItemsToRefundInterfaceFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Creditmemo;
use ReachDigital\ISReservations\Model\AppendReservations;
use ReachDigital\ISReservations\Model\MetaData\EncodeMetaData;
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

    /**
     * @var ItemsToRefundInterfaceFactory
     */
    private $itemsToRefundFactory;

    /**
     * @var EncodeMetaData
     */
    private $encodeMetaData;

    public function __construct(
        GetReservationsByMetadata $getReservationsByMetadata,
        ReservationBuilderInterface $reservationBuilder,
        AppendReservations $appendReservations,
        ItemsToRefundInterfaceFactory $itemsToRefundFactory,
        EncodeMetaData $encodeMetaData
    )
    {
        $this->getReservationsByMetadata = $getReservationsByMetadata;
        $this->reservationBuilder = $reservationBuilder;
        $this->appendReservations = $appendReservations;
        $this->itemsToRefundFactory = $itemsToRefundFactory;
        $this->encodeMetaData = $encodeMetaData;
    }

    /**
     * Compare float number with some epsilon
     * @param float $floatNumber
     * @return bool
     */
    private function isZeroOrNegative(float $floatNumber): bool
    {
        return $floatNumber < 0.0000001;
    }

    /**
     * Revert reservations for refunded qtys and adjust $itemsToRefund appropriately.
     *
     *
     *
     * @param ProcessRefundItems $subject
     * @param OrderInterface     $order
     * @param ItemsToRefund[]    $itemsToRefund
     * @param array              $returnToStockItems
     *
     * @return array
     * @throws InputException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Validation\ValidationException
     */
    public function beforeExecute(
        ProcessRefundItems $subject,
        OrderInterface $order,
        array $itemsToRefund,
        array $returnToStockItems): array
    {
        // Collect reservation qtys by sku and source code
        $reservations = $this->getReservationsBySkuAndSource($order);
        $nullifications = [];
        $adjustedItemsToRefund = [];

        // For each sku being refunded, attempt to revert outstanding (not yet shipped, i.e. negative) reserved qtys.
        foreach ($itemsToRefund as $item) {
            $qtyToRefund = $item->getQuantity();
            $sku = $item->getSku();
            // @todo reverse reservations order?
            foreach ($reservations[$sku] as $sourceCode => $sourceReservationQty) {
                // See if, for this source, we can revert some or all of $qtyToRefund from existing reservations.
                $revertableQty = min($qtyToRefund, -$sourceReservationQty);
                if (!$this->isZeroOrNegative($revertableQty)) {
                    $this->reservationBuilder->setSku($item->getSku());
                    $this->reservationBuilder->setQuantity($revertableQty);
                    $this->reservationBuilder->setSourceCode($sourceCode);
                    $this->reservationBuilder->setMetadata($this->encodeMetaData->execute([
                        'order' => $order->getEntityId(),
                        'refund_revert' => null
                    ]));
                    $nullifications[] = $this->reservationBuilder->build();
                    $qtyToRefund -= $revertableQty;
                } else {
                    break;
                }
            }

            // The quantity that can't be reverted by nullifying source reservations (the remaining $qtyToRevert) must
            // already have been shipped. Therefore we need to add that qty (dependencing on the 'return_to_stock'
            // checkbox) back to the source.

            // Magento by default will always add the qty's back to the source (when the checkbox is checked) when a
            // credit is created. So here we must ensure that the amount Magento would add back to the source is the
            // remaining $qtyToRevert amount, not the original qty in $itemsToRefund.

            // @fixme: replace ProcessRefundItems entirely, just don't split the logic since it doesn't work, qty is used in complicated logic and we can't simple adjust it here
            $adjustedItemsToRefund[] = $this->itemsToRefundFactory->create([
                'sku' => $item->getSku(),
                'qty' => $qtyToRefund,
                'processedQty' => $item->getProcessedQuantity() // @fixme what to do with processed qty?
            ]);
        }

        if (count($nullifications)) {
            $this->appendReservations->execute($nullifications);
        }

        return [ $order, $adjustedItemsToRefund, $returnToStockItems ];
    }

    /**
     * @param OrderInterface $order
     *
     * @return ReservationInterface[]
     */
    private function getReservationsBySkuAndSource(OrderInterface $order): array
    {
        $reservations = $this->getReservationsByMetadata->execute(
            $this->encodeMetaData->execute([ 'order' => $order->getEntityId() ]));

        $reservationsBySkuAndSource = [];
        foreach ($reservations as $reservation) {
            $sku = $reservation->getSku();
            $sourceCode = $reservation->getSourceCode();

            $reservationsBySkuAndSource[$sku] = $reservationsBySkuAndSource[$sku] ?? [];
            $reservationsBySkuAndSource[$sku][$sourceCode] = $reservationsBySkuAndSource[$sku][$sourceCode] ?? 0;
            $reservationsBySkuAndSource[$sku][$sourceCode] += $reservation->getQuantity();
        }

        return $reservationsBySkuAndSource;
    }
}
