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
use Magento\InventorySales\Model\SalesEventToArrayConverter;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterfaceFactory;
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
    /**
     * @var SalesEventInterfaceFactory
     */
    private $salesEventFactory;
    /**
     * @var SalesEventToArrayConverter
     */
    private $salesEventToArrayConverter;

    public function __construct(
        AppendReservationsInterface $appendReservations,
        ReservationBuilderInterface $reservationBuilder,
        StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver,
        StoreRepositoryInterface $storeRepository,
        SerializerInterface $serializer,
        SalesEventInterfaceFactory $salesEventFactory,
        SalesEventToArrayConverter $salesEventToArrayConverter
    ) {
        $this->appendReservations = $appendReservations;
        $this->reservationBuilder = $reservationBuilder;
        $this->stockByWebsiteIdResolver = $stockByWebsiteIdResolver;
        $this->storeRepository = $storeRepository;
        $this->serializer = $serializer;
        $this->salesEventFactory = $salesEventFactory;
        $this->salesEventToArrayConverter = $salesEventToArrayConverter;
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

            $salesEvent = $this->salesEventFactory->create([
                'type' => \ReachDigital\IOSReservationsApi\Api\SalesEventInterface::EVENT_ORDER_ASSIGNED,
                'objectType' => SalesEventInterface::OBJECT_TYPE_ORDER,
                'objectId' => (string) $order->getEntityId(),
            ]);

            $reservations[] = $this->reservationBuilder
                ->setSku($item->getSku())
                ->setQuantity($item->getQtyToDeduct())
                ->setStockId($stockId)
                ->setMetadata($this->serializer->serialize($this->salesEventToArrayConverter->execute($salesEvent)))
                ->build();
        }
        $this->appendReservations->execute($reservations);
    }
}
