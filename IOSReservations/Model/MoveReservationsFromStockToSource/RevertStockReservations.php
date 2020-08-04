<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSource;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Validation\ValidationException;
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
     * @var StockByWebsiteIdResolverInterface
     */
    private $stockByWebsiteIdResolver;

    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(
        AppendReservationsInterface $appendReservations,
        ReservationBuilderInterface $reservationBuilder,
        StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver,
        StoreRepositoryInterface $storeRepository,
        SerializerInterface $serializer
    ) {
        $this->appendReservations = $appendReservations;
        $this->reservationBuilder = $reservationBuilder;
        $this->stockByWebsiteIdResolver = $stockByWebsiteIdResolver;
        $this->storeRepository = $storeRepository;
        $this->serializer = $serializer;
    }

    /**
     * @param OrderInterface                 $order
     * @param SourceSelectionResultInterface $sourceSelectionResult
     *
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws ValidationException
     */
    public function execute(OrderInterface $order, SourceSelectionResultInterface $sourceSelectionResult): void
    {
        $reservations = [];
        foreach ($sourceSelectionResult->getSourceSelectionItems() as $item) {
            $store = $this->storeRepository->getById((int) $order->getStoreId());
            $stockId = (int) $this->stockByWebsiteIdResolver->execute((int) $store->getWebsiteId())->getStockId();

            /**
             * Please note to not change the metadata format only here, as it is used in
             * @see \ReachDigital\IOSReservations\Plugin\MagentoInventorySales\PreventSourceItemQuantityDeductionOnCancellation::aroundExecute
             */
            $reservations[] = $this->reservationBuilder
                ->setSku($item->getSku())
                ->setQuantity($item->getQtyToDeduct())
                ->setStockId($stockId)
                ->setMetadata(
                    $this->serializer->serialize([
                        // @fixme does it make sense to 'fake' a sales event here? Maybe order-source assignment should be implemented as an actual salesevent?
                        // @see  \Magento\InventorySales\Model\PlaceReservationsForSalesEvent::execute
                        'event_type' => 'order_assigned',
                        'object_type' => 'order',
                        'object_id' => $order->getEntityId(),
                    ])
                )
                ->build();
        }
        $this->appendReservations->execute($reservations);
    }
}
