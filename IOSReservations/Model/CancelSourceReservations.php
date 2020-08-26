<?php

namespace ReachDigital\IOSReservations\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Validation\ValidationException;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterface;
use Psr\Log\LoggerInterface;
use ReachDigital\ISReservationsApi\Api\EncodeMetaDataInterface;
use ReachDigital\ISReservationsApi\Api\GetReservationsByMetadataInterface;
use ReachDigital\ISReservationsApi\Model\AppendSourceReservationsInterface;
use ReachDigital\ISReservationsApi\Model\SourceReservationBuilderInterface;

class CancelSourceReservations
{
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
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var GetOrderSourceReservationQuantityBySkuAndSource
     */
    private $getOrderSourceReservationQuantityBySkuAndSource;

    public function __construct(
        EncodeMetaDataInterface $encodeMetaData,
        AppendSourceReservationsInterface $appendSourceReservations,
        SourceReservationBuilderInterface $sourceReservationBuilder,
        LoggerInterface $logger,
        GetOrderSourceReservationQuantityBySkuAndSource $getOrderSourceReservationQuantityBySkuAndSource
    ) {
        $this->encodeMetaData = $encodeMetaData;
        $this->appendSourceReservations = $appendSourceReservations;
        $this->sourceReservationBuilder = $sourceReservationBuilder;
        $this->logger = $logger;
        $this->getOrderSourceReservationQuantityBySkuAndSource = $getOrderSourceReservationQuantityBySkuAndSource;
    }

    /**
     * Will nullify source reservations if they are available. Will return items that are not nullified.
     *
     * @param ItemToSellInterface[] $itemsToCancel
     * @return ItemToSellInterface[]
     *
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws ValidationException
     */
    public function execute(int $orderId, array $itemsToCancel): array
    {
        $this->logger->info('cancel_source_reservations', [
            'module' => 'reach-digital/magento2-order-source-reservations',
            'order' => $orderId,
            'items' => array_map(function ($item) {
                return [$item->getSku(), $item->getQuantity()];
            }, $itemsToCancel),
        ]);

        if (empty($itemsToCancel)) {
            return $itemsToCancel;
        }

        $sourceCancellations = [];
        $sourceReservations = $this->getOrderSourceReservationQuantityBySkuAndSource->execute($orderId);

        foreach ($itemsToCancel as $itemToCancel) {
            $qtyToCompensate = $itemToCancel->getQuantity();

            if (!isset($sourceReservations[$itemToCancel->getSku()])) {
                continue;
            }

            // Compensate $qtyToCompensate on the available reservation qty's for each source
            foreach ($sourceReservations[$itemToCancel->getSku()] as $sourceCode => $sourceReservationQty) {
                // See if, for this source, we can revert some or all of $qtyToRefund from existing sourceReservations.
                $revertibleQty = min($qtyToCompensate, -$sourceReservationQty);
                if (!$this->isLtZero($revertibleQty)) {
                    $this->sourceReservationBuilder->setSku($itemToCancel->getSku());
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
                    continue;
                }
            }

            // Set the remaining qty so it can be processed somewhere else.
            $itemToCancel->setQuantity($qtyToCompensate);
        }

        if ($sourceCancellations) {
            $this->appendSourceReservations->execute($sourceCancellations);
        }

        // Return the remaining qty
        return array_filter($itemsToCancel, function ($item) {
            return $item->getQuantity() > 0;
        });
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
