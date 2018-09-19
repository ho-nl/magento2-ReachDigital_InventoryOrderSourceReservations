<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSource;

use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;
use Magento\InventoryReservationsApi\Model\AppendReservationsInterface;
use Magento\InventoryReservationsApi\Model\ReservationBuilderInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\InventorySourceSelectionApi\Api\Data\SourceSelectionResultInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Api\StoreRepositoryInterface;

class RevertStockReservations
{

    /**
     * @var AppendReservationsInterface
     */
    private $appendReservations;

    /**
     * @var ReservationBuilderInterface
     */
    private $reservationBuilder;

    /**
     * @var GetProductIdsBySkusInterface
     */
    private $getProductIdsBySkus;

    /**
     * @var StockByWebsiteIdResolverInterface
     */
    private $stockByWebsiteIdResolver;

    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    public function __construct(
        AppendReservationsInterface $appendReservations,
        ReservationBuilderInterface $reservationBuilder,
        GetProductIdsBySkusInterface $getProductIdsBySkus,
        StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver,
        StoreRepositoryInterface $storeRepository
    ) {
        $this->appendReservations = $appendReservations;
        $this->reservationBuilder = $reservationBuilder;
        $this->getProductIdsBySkus = $getProductIdsBySkus;
        $this->stockByWebsiteIdResolver = $stockByWebsiteIdResolver;
        $this->storeRepository = $storeRepository;
    }

    /**
     * @param OrderInterface                 $order
     * @param SourceSelectionResultInterface $sourceSelectionResult
     *
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Validation\ValidationException
     */
    public function execute(
        OrderInterface $order,
        SourceSelectionResultInterface $sourceSelectionResult
    ): void {
        $reservations = [];
        foreach ($sourceSelectionResult->getSourceSelectionItems() as $item) {
            //@todo Should we check if the product still exists? See ProcessBackItemQtyPlugin for explanation
            //@see Magento\InventorySales\Plugin\CatalogInventory\StockManagement\ProcessBackItemQtyPlugin::aroundBackItemQty

            //We create a negative reservation
            $qty = (float)$item->getQtyToDeduct() * -1;

            $store = $this->storeRepository->getById((int) $order->getStoreId());
            $stockId = (int)$this->stockByWebsiteIdResolver->execute((int)$store->getWebsiteId())->getStockId();

            $reservations[] = $this->reservationBuilder
                ->setSku($item->getSku())
                ->setQuantity($qty)
                ->setStockId($stockId)
                ->build();
        }
        $this->appendReservations->execute($reservations);
    }
}
