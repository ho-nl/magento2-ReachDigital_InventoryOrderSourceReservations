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

class NullifySourceReservations
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
     * @param ItemToSellInterface[] $itemsToNullify
     * @return ItemToSellInterface[]
     *
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws ValidationException
     */
    public function execute(int $orderId, array $itemsToNullify): array
    {
        $this->logger->info('nullify_source_reservations', [
            'module' => 'reach-digital/magento2-order-source-reservations',
            'order' => $orderId,
            'items_to_nullify' => array_map(function ($item) {
                return [$item->getSku(), $item->getQuantity()];
            }, $itemsToNullify),
        ]);

        $sourceCancellations = [];
        $sourceReservations = $this->getOrderSourceReservationQuantityBySkuAndSource->execute($orderId);

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
