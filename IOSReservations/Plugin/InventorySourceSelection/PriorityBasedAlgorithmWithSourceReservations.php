<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Plugin\InventorySourceSelection;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventoryApi\Api\SourceItemRepositoryInterface;
use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventorySourceSelectionApi\Api\Data\InventoryRequestInterface;
use Magento\InventorySourceSelectionApi\Api\Data\SourceSelectionItemInterfaceFactory;
use Magento\InventorySourceSelectionApi\Api\Data\SourceSelectionResultInterfaceFactory;
use Magento\InventorySourceSelectionApi\Model\SourceSelectionInterface;
use ReachDigital\ISReservationsApi\Model\GetSourceReservationsQuantityInterface;
use Magento\InventorySourceSelectionApi\Api\Data\SourceSelectionResultInterface;

class PriorityBasedAlgorithmWithSourceReservations implements SourceSelectionInterface
{
    /**
     * @var GetSourcesAssignedToStockOrderedByPriorityInterface
     */
    private $getSourcesAssignedToStockOrderedByPriority;

    /**
     * @var SourceSelectionItemInterfaceFactory
     */
    private $sourceSelectionItemFactory;

    /**
     * @var SourceSelectionResultInterfaceFactory
     */
    private $sourceSelectionResultFactory;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var SourceItemRepositoryInterface
     */
    private $sourceItemRepository;

    /**
     * @var GetSourceReservationsQuantityInterface
     */
    private $getSourceReservationsQuantity;

    public function __construct(
        GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStockOrderedByPriority,
        SourceSelectionItemInterfaceFactory $sourceSelectionItemFactory,
        SourceSelectionResultInterfaceFactory $sourceSelectionResultFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SourceItemRepositoryInterface $sourceItemRepository,
        GetSourceReservationsQuantityInterface $getSourceReservationsQuantity
    ) {
        $this->getSourcesAssignedToStockOrderedByPriority = $getSourcesAssignedToStockOrderedByPriority;
        $this->sourceSelectionItemFactory = $sourceSelectionItemFactory;
        $this->sourceSelectionResultFactory = $sourceSelectionResultFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->sourceItemRepository = $sourceItemRepository;
        $this->getSourceReservationsQuantity = $getSourceReservationsQuantity;
    }

    /**
     * @inheritdoc
     *
     * @throws InputException
     * @throws LocalizedException
     */
    public function execute(InventoryRequestInterface $inventoryRequest): SourceSelectionResultInterface
    {
        $isShippable = true;
        $stockId = $inventoryRequest->getStockId();
        $sources = $this->getEnabledSourcesOrderedByPriorityByStockId($stockId);
        $sourceItemSelections = [];

        foreach ($inventoryRequest->getItems() as $item) {
            $itemSku = $item->getSku();
            $qtyToDeliver = $item->getQty();
            foreach ($sources as $source) {
                $sourceItem = $this->getSourceItemBySourceCodeAndSku($source->getSourceCode(), $itemSku);
                if (null === $sourceItem) {
                    continue;
                }

                if ((int) $sourceItem->getStatus() !== SourceItemInterface::STATUS_IN_STOCK) {
                    continue;
                }

                $sourceItemQty =
                    $sourceItem->getQuantity() +
                    $this->getSourceReservationsQuantity->execute($item->getSku(), $source->getSourceCode());
                $qtyToDeduct = min($sourceItemQty, $qtyToDeliver);

                // check if source has some qty of SKU, so it's possible to take them into account
                if ($this->isZero((float) $sourceItemQty)) {
                    continue;
                }

                $sourceItemSelections[] = $this->sourceSelectionItemFactory->create([
                    'sourceCode' => $sourceItem->getSourceCode(),
                    'sku' => $itemSku,
                    'qtyToDeduct' => $qtyToDeduct,
                    'qtyAvailable' => $sourceItemQty,
                ]);

                $qtyToDeliver -= $qtyToDeduct;
                if ($this->isZero((float) $qtyToDeliver)) {
                    break;
                }
            }

            // if we go throw all sources from the stock and there is still some qty to delivery,
            // then it doesn't have enough items to delivery
            if (!$this->isZero($qtyToDeliver)) {
                $isShippable = false;
            }
        }

        return $this->sourceSelectionResultFactory->create([
            'sourceItemSelections' => $sourceItemSelections,
            'isShippable' => $isShippable,
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

    /**
     * Get enabled sources ordered by priority by $stockId
     *
     * @param int $stockId
     * @return array
     *
     * @throws InputException
     * @throws LocalizedException
     */
    private function getEnabledSourcesOrderedByPriorityByStockId(int $stockId): array
    {
        $sources = $this->getSourcesAssignedToStockOrderedByPriority->execute($stockId);
        $sources = array_filter($sources, function (SourceInterface $source) {
            return $source->isEnabled();
        });
        return $sources;
    }

    /**
     * Returns source item from specific source by given SKU. Return null if source item is not found
     *
     * @param string $sourceCode
     * @param string $sku
     * @return SourceItemInterface|null
     */
    private function getSourceItemBySourceCodeAndSku(string $sourceCode, string $sku): ?SourceItemInterface
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(SourceItemInterface::SOURCE_CODE, $sourceCode)
            ->addFilter(SourceItemInterface::SKU, $sku)
            ->create();
        $sourceItemsResult = $this->sourceItemRepository->getList($searchCriteria);

        return $sourceItemsResult->getTotalCount() > 0 ? current($sourceItemsResult->getItems()) : null;
    }
}
