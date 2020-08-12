<?php

namespace ReachDigital\IOSReservations\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Validation\ValidationException;
use Magento\InventorySales\Model\GetItemsToCancelFromOrderItem;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterface;
use Magento\InventorySalesApi\Model\ReturnProcessor\Request\ItemsToRefundInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;
use ReachDigital\IOSReservations\Model\MagentoInventorySales\CancelOrderItems;
use ReachDigital\ISReservations\Model\MetaData\DecodeMetaData;
use ReachDigital\ISReservationsApi\Api\EncodeMetaDataInterface;
use ReachDigital\ISReservationsApi\Api\GetReservationsByMetadataInterface;
use ReachDigital\ISReservationsApi\Model\AppendSourceReservationsInterface;
use ReachDigital\ISReservationsApi\Model\SourceReservationBuilderInterface;

class NullifySourceReservations
{
    /**
     * @var GetReservationsByMetadataInterface
     */
    private $getReservationsByMetadata;
    /**
     * @var EncodeMetaDataInterface
     */
    private $encodeMetaData;

    /**
     * @var AppendSourceReservationsInterface
     */
    private $appendSourceReservations;
    /**
     * @var SourceReservationBuilderInterface
     */
    private $sourceReservationBuilder;

    public function __construct(
        GetReservationsByMetadataInterface $getReservationsByMetadata,
        EncodeMetaDataInterface $encodeMetaData,
        AppendSourceReservationsInterface $appendSourceReservations,
        SourceReservationBuilderInterface $sourceReservationBuilder,
        LoggerInterface $logger
    ) {
        $this->getReservationsByMetadata = $getReservationsByMetadata;
        $this->encodeMetaData = $encodeMetaData;
        $this->appendSourceReservations = $appendSourceReservations;
        $this->sourceReservationBuilder = $sourceReservationBuilder;
    }

    /**
     * @param string $orderId
     * @param ItemToSellInterface[] $itemsToNullify
     * @return ItemToSellInterface[]
     *
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws ValidationException
     */
    public function execute(string $orderId, array $itemsToNullify)
    {
        $sourceCancellations = [];
        $sourceReservations = $this->getReservationsBySkuAndSource($orderId);

        foreach ($itemsToNullify as $itemToNullify) {
            $qtyToCompensate = $itemToNullify->getQuantity();

            if (!isset($sourceReservations[$itemToNullify->getSku()])) {
                continue;
            }

            // Compensate $qtyToCompensate on the available reservation qty's for each source
            foreach ($sourceReservations[$itemToNullify->getSku()] as $sourceCode => $sourceReservationQty) {
                // See if, for this source, we can revert some or all of $qtyToRefund from existing sourceReservations.
                $revertibleQty = min($qtyToCompensate, -$sourceReservationQty);
                if (!$this->isLtZero($revertibleQty)) {
                    $this->sourceReservationBuilder->setSku($itemToNullify->getSku());
                    $this->sourceReservationBuilder->setQuantity($revertibleQty);
                    $this->sourceReservationBuilder->setSourceCode($sourceCode);
                    $this->sourceReservationBuilder->setMetadata(
                        $this->encodeMetaData->execute([
                            'order' => $orderId,
                            'refund_compensation' => null,
                        ])
                    );
                    $sourceCancellations[] = $this->sourceReservationBuilder->build();
                    $qtyToCompensate -= $revertibleQty;
                } else {
                    break;
                }
            }

            // Set the remaining to be cancelled on the stock
            $itemToNullify->setQuantity($qtyToCompensate);
        }

        if ($sourceCancellations) {
            $this->appendSourceReservations->execute($sourceCancellations);
        }

        // Cancel the remaining quantity
        return array_filter($itemsToNullify, function ($item) {
            return $item->getQuantity() > 0;
        });
    }

    private function getReservationsBySkuAndSource(string $orderId): array
    {
        $reservations = $this->getReservationsByMetadata->execute(
            $this->encodeMetaData->execute(['order' => $orderId])
        );

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

    /**
     * Compare float number with some epsilon
     *
     * @param float $floatNumber
     *
     * @return bool
     */
    private function isLtZero(float $floatNumber): bool
    {
        return $floatNumber < 0.0000001;
    }
}
