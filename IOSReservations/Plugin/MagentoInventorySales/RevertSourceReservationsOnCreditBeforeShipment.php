<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Plugin\MagentoInventorySales;

use Closure;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventorySales\Model\ReturnProcessor\ProcessRefundItems;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterfaceFactory;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterfaceFactory;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Model\ReturnProcessor\GetSourceDeductedOrderItemsInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\InventorySourceDeductionApi\Model\ItemToDeductFactory;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionRequestFactory;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionService;
use ReachDigital\ISReservationsApi\Api\Data\SourceReservationInterface;
use ReachDigital\ISReservationsApi\Api\EncodeMetaDataInterface;
use ReachDigital\ISReservationsApi\Api\GetReservationsByMetadataInterface;
use ReachDigital\ISReservationsApi\Model\AppendSourceReservationsInterface;
use ReachDigital\ISReservationsApi\Model\SourceReservationBuilderInterface;

class RevertSourceReservationsOnCreditBeforeShipment
{
    /**
     * @var WebsiteRepositoryInterface
     */
    private $websiteRepository;

    /**
     * @var SalesChannelInterfaceFactory
     */
    private $salesChannelFactory;

    /**
     * @var SalesEventInterfaceFactory
     */
    private $salesEventFactory;

    /**
     * @var GetSourceDeductedOrderItemsInterface
     */
    private $getSourceDeductedOrderItems;

    /**
     * @var ItemToDeductFactory
     */
    private $itemToDeductFactory;

    /**
     * @var SourceDeductionRequestFactory
     */
    private $sourceDeductionRequestFactory;

    /**
     * @var SourceDeductionService
     */
    private $sourceDeductionService;

    /**
     * @var GetReservationsByMetadataInterface
     */
    private $getReservationsByMetadata;

    /**
     * @var EncodeMetaDataInterface
     */
    private $encodeMetaData;

    /**
     * @var SourceReservationBuilderInterface
     */
    private $sourceReservationBuilder;

    /**
     * @var AppendSourceReservationsInterface
     */
    private $appendReservations;

    public function __construct(
        WebsiteRepositoryInterface $websiteRepository,
        SalesChannelInterfaceFactory $salesChannelFactory,
        SalesEventInterfaceFactory $salesEventFactory,
        GetSourceDeductedOrderItemsInterface $getSourceDeductedOrderItems,
        ItemToDeductFactory $itemToDeductFactory,
        SourceDeductionRequestFactory $sourceDeductionRequestFactory,
        SourceDeductionService $sourceDeductionService,
        GetReservationsByMetadataInterface $getReservationsByMetadata,
        EncodeMetaDataInterface $encodeMetaData,
        SourceReservationBuilderInterface $sourceReservationBuilder,
        AppendSourceReservationsInterface $appendReservations
    ) {
        $this->websiteRepository = $websiteRepository;
        $this->salesChannelFactory = $salesChannelFactory;
        $this->salesEventFactory = $salesEventFactory;
        $this->getSourceDeductedOrderItems = $getSourceDeductedOrderItems;
        $this->itemToDeductFactory = $itemToDeductFactory;
        $this->sourceDeductionRequestFactory = $sourceDeductionRequestFactory;
        $this->sourceDeductionService = $sourceDeductionService;
        $this->getReservationsByMetadata = $getReservationsByMetadata;
        $this->encodeMetaData = $encodeMetaData;
        $this->sourceReservationBuilder = $sourceReservationBuilder;
        $this->appendReservations = $appendReservations;
    }

    /**
     * @param ProcessRefundItems $subject
     * @param Closure           $proceed
     * @param OrderInterface     $order
     * @param array              $itemsToRefund
     * @param array              $returnToStockItems
     *
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     */
    public function aroundExecute(
        ProcessRefundItems $subject,
        Closure $proceed,
        OrderInterface $order,
        array $itemsToRefund,
        array $returnToStockItems
    ): void {
        $salesChannel = $this->getSalesChannelForOrder($order);
        $deductedItems = $this->getSourceDeductedOrderItems->execute($order, $returnToStockItems);
        $backItemsPerSource = $nullifications = [];
        $reservations = $this->getReservationsBySkuAndSource($order);

        foreach ($itemsToRefund as $item) {
            $sku = $item->getSku();

            $totalDeductedQty = $this->getTotalDeductedQty($item, $deductedItems);
            $processedQty = $item->getProcessedQuantity() - $totalDeductedQty;
            $qtyBackToSource = $processedQty > 0 ? $item->getQuantity() - $processedQty : $item->getQuantity();
            $qtyToCompensate = $qtyBackToSource > 0 ? $item->getQuantity() - $qtyBackToSource : $item->getQuantity();

            if ($qtyToCompensate > 0) {
                // Compensate $qtyToCompensate on the available reservation qtys for each source
                // @todo Iterate over reservations in reverse
                foreach ($reservations[$sku] as $sourceCode => $sourceReservationQty) {
                    // See if, for this source, we can revert some or all of $qtyToRefund from existing reservations.
                    $revertableQty = min($qtyToCompensate, -$sourceReservationQty);
                    if (!$this->isZero($revertableQty)) {
                        $this->sourceReservationBuilder->setSku($item->getSku());
                        $this->sourceReservationBuilder->setQuantity($revertableQty);
                        $this->sourceReservationBuilder->setSourceCode($sourceCode);
                        $this->sourceReservationBuilder->setMetadata(
                            $this->encodeMetaData->execute([
                                'order' => $order->getEntityId(),
                                'refund_compensation' => null,
                            ])
                        );
                        $nullifications[] = $this->sourceReservationBuilder->build();
                        $qtyToCompensate -= $revertableQty;
                    } else {
                        break;
                    }
                }
                // @fixme: should remaining qtyBackToSource be compensated on stock reservation?
            }

            foreach ($deductedItems as $deductedItemResult) {
                $sourceCode = $deductedItemResult->getSourceCode();
                foreach ($deductedItemResult->getItems() as $deductedItem) {
                    if ($sku !== $deductedItem->getSku() || $this->isZero($qtyBackToSource)) {
                        continue;
                    }

                    $backQty = min($deductedItem->getQuantity(), $qtyBackToSource);

                    $backItemsPerSource[$sourceCode][] = $this->itemToDeductFactory->create([
                        'sku' => $deductedItem->getSku(),
                        'qty' => -$backQty,
                    ]);
                    $qtyBackToSource -= $backQty;
                }
            }
        }

        $salesEvent = $this->salesEventFactory->create([
            'type' => SalesEventInterface::EVENT_CREDITMEMO_CREATED,
            'objectType' => SalesEventInterface::OBJECT_TYPE_ORDER,
            'objectId' => (string) $order->getEntityId(),
        ]);

        foreach ($backItemsPerSource as $sourceCode => $items) {
            $sourceDeductionRequest = $this->sourceDeductionRequestFactory->create([
                'sourceCode' => $sourceCode,
                'items' => $items,
                'salesChannel' => $salesChannel,
                'salesEvent' => $salesEvent,
            ]);
            $this->sourceDeductionService->execute($sourceDeductionRequest);
        }

        if (count($nullifications)) {
            $this->appendReservations->execute($nullifications);
        }
    }

    /**
     * @param OrderInterface $order
     *
     * @return SourceReservationInterface[]
     */
    private function getReservationsBySkuAndSource(OrderInterface $order): array
    {
        $reservations = $this->getReservationsByMetadata->execute(
            $this->encodeMetaData->execute(['order' => $order->getEntityId()])
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
     * @param $item
     * @param array $deductedItems
     * @return float
     */
    private function getTotalDeductedQty($item, array $deductedItems): float
    {
        $result = 0;

        foreach ($deductedItems as $deductedItemResult) {
            foreach ($deductedItemResult->getItems() as $deductedItem) {
                if ($item->getSku() !== $deductedItem->getSku()) {
                    continue;
                }
                $result += $deductedItem->getQuantity();
            }
        }

        return $result;
    }

    /**
     * @param OrderInterface $order
     *
     * @return SalesChannelInterface
     * @throws NoSuchEntityException
     */
    private function getSalesChannelForOrder(OrderInterface $order): SalesChannelInterface
    {
        $websiteId = (int) $order->getStore()->getWebsiteId();
        $websiteCode = $this->websiteRepository->getById($websiteId)->getCode();

        return $this->salesChannelFactory->create([
            'data' => [
                'type' => SalesChannelInterface::TYPE_WEBSITE,
                'code' => $websiteCode,
            ],
        ]);
    }

    /**
     * Compare float number with some epsilon
     *
     * @param float $floatNumber
     *
     * @return bool
     */
    private function isZero(float $floatNumber): bool
    {
        return $floatNumber < 0.0000001;
    }
}
