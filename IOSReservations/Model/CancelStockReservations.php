<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace ReachDigital\IOSReservations\Model;

use Magento\Catalog\Model\Indexer\Product\Price\Processor;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryCatalog\Model\GetProductIdsBySkus;
use Magento\InventorySales\Model\GetItemsToCancelFromOrderItem;
use Magento\InventorySales\Model\ResourceModel\GetWebsiteCodeByWebsiteId;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterface;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterfaceFactory;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterfaceFactory;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterfaceFactory;
use Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Psr\Log\LoggerInterface;

class CancelStockReservations
{
    /**
     * @var Processor
     */
    private $priceIndexer;

    /**
     * @var SalesEventInterfaceFactory
     */
    private $salesEventFactory;

    /**
     * @var PlaceReservationsForSalesEventInterface
     */
    private $placeReservationsForSalesEvent;

    /**
     * @var SalesChannelInterfaceFactory
     */
    private $salesChannelFactory;

    /**
     * @var WebsiteRepositoryInterface
     */
    private $websiteRepository;

    /**
     * @var GetItemsToCancelFromOrderItem
     */
    private $getItemsToCancelFromOrderItem;
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;
    /**
     * @var GetWebsiteCodeByWebsiteId
     */
    private $getWebsiteCodeByWebsiteId;
    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;
    /**
     * @var GetProductIdsBySkus
     */
    private $getProductIdsBySkus;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var GetOrderStockReservationQuantityBySku
     */
    private $getOrderStockReservationQuantityBySku;
    /**
     * @var ItemToSellInterfaceFactory
     */
    private $itemToSellFactory;

    public function __construct(
        Processor $priceIndexer,
        SalesEventInterfaceFactory $salesEventFactory,
        PlaceReservationsForSalesEventInterface $placeReservationsForSalesEvent,
        SalesChannelInterfaceFactory $salesChannelFactory,
        WebsiteRepositoryInterface $websiteRepository,
        GetItemsToCancelFromOrderItem $getItemsToCancelFromOrderItem,
        OrderRepositoryInterface $orderRepository,
        GetWebsiteCodeByWebsiteId $getWebsiteCodeByWebsiteId,
        StoreRepositoryInterface $storeRepository,
        GetProductIdsBySkus $getProductIdsBySkus,
        LoggerInterface $logger,
        GetOrderStockReservationQuantityBySku $getOrderStockReservationQuantityBySku,
        ItemToSellInterfaceFactory $itemToSellFactory
    ) {
        $this->priceIndexer = $priceIndexer;
        $this->salesEventFactory = $salesEventFactory;
        $this->placeReservationsForSalesEvent = $placeReservationsForSalesEvent;
        $this->salesChannelFactory = $salesChannelFactory;
        $this->websiteRepository = $websiteRepository;
        $this->getItemsToCancelFromOrderItem = $getItemsToCancelFromOrderItem;
        $this->orderRepository = $orderRepository;
        $this->getWebsiteCodeByWebsiteId = $getWebsiteCodeByWebsiteId;
        $this->storeRepository = $storeRepository;
        $this->getProductIdsBySkus = $getProductIdsBySkus;
        $this->logger = $logger;
        $this->getOrderStockReservationQuantityBySku = $getOrderStockReservationQuantityBySku;
        $this->itemToSellFactory = $itemToSellFactory;
    }

    /**
     * @param ItemToSellInterface[] $itemsToNullify
     *
     * @return ItemToSellInterface[]
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(int $orderId, array $itemsToNullify): array
    {
        $this->logger->info('nullify_stock_reservations', [
            'module' => 'reach-digital/magento2-order-source-reservations',
            'order' => $orderId,
            'items' => array_map(function ($item) {
                return [$item->getSku(), $item->getQuantity()];
            }, $itemsToNullify),
        ]);

        $stockNullifications = [];
        $stockReservations = $this->getOrderStockReservationQuantityBySku->execute($orderId);

        foreach ($itemsToNullify as $itemToNullify) {
            $qtyToCompensate = $itemToNullify->getQuantity();

            if (!isset($stockReservations[$itemToNullify->getSku()])) {
                continue;
            }

            // See if, for this sku, we can revert some or all of $qtyToCompensate from existing stock reservation.
            $revertibleQty = min($qtyToCompensate, -$stockReservations[$itemToNullify->getSku()] ?? 0);

            if (!$this->isLtZero($revertibleQty)) {
                $stockNullifications[] = $this->itemToSellFactory->create([
                    'sku' => $itemToNullify->getSku(),
                    'qty' => $revertibleQty,
                ]);
                $qtyToCompensate -= $revertibleQty;
            }

            // Set the remaining qty so it can be processed somewhere else.
            $itemToNullify->setQuantity($qtyToCompensate);
        }

        if ($stockNullifications) {
            $salesChannel = $this->salesChannelFactory->create([
                'data' => [
                    'type' => SalesChannelInterface::TYPE_WEBSITE,
                    'code' => $this->getWebsiteCodeByWebsiteId->execute(
                        (int) $this->storeRepository
                            ->get($this->orderRepository->get($orderId)->getStoreId())
                            ->getWebsiteId()
                    ),
                ],
            ]);

            $salesEvent = $this->salesEventFactory->create([
                'type' => SalesEventInterface::EVENT_ORDER_CANCELED,
                'objectType' => SalesEventInterface::OBJECT_TYPE_ORDER,
                'objectId' => (string) $orderId,
            ]);

            $this->placeReservationsForSalesEvent->execute($stockNullifications, $salesChannel, $salesEvent);

            // @todo what is the price indexer doing here?
            $this->priceIndexer->reindexList(
                $this->getProductIdsBySkus->execute(
                    array_map(function ($item) {
                        return $item->getSku();
                    }, $stockNullifications)
                )
            );
        }

        // Return the remaining qty
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
