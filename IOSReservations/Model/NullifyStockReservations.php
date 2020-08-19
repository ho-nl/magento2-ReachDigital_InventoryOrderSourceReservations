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
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterfaceFactory;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterfaceFactory;
use Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Psr\Log\LoggerInterface;

class NullifyStockReservations
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
        LoggerInterface $logger
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
    }

    /**
     * @param string $orderId
     * @param ItemToSellInterface[] $itemsToNullify
     *
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(string $orderId, array $itemsToNullify): void
    {
        // @todo check if stock reservation is available before nullifying.

        $this->logger->info('nullify_stock_reservations', [
            'module' => 'reach-digital/magento2-order-source-reservations',
            'order' => $orderId,
            'items_to_nullify' => array_map(function ($item) {
                return [$item->getSku(), $item->getQuantity()];
            }, $itemsToNullify),
        ]);

        $websiteId = $this->storeRepository->get($this->orderRepository->get($orderId)->getStoreId())->getWebsiteId();

        $salesChannel = $this->salesChannelFactory->create([
            'data' => [
                'type' => SalesChannelInterface::TYPE_WEBSITE,
                'code' => $this->getWebsiteCodeByWebsiteId->execute((int) $websiteId),
            ],
        ]);

        $salesEvent = $this->salesEventFactory->create([
            'type' => SalesEventInterface::EVENT_ORDER_CANCELED,
            'objectType' => SalesEventInterface::OBJECT_TYPE_ORDER,
            'objectId' => (string) $orderId,
        ]);

        $this->placeReservationsForSalesEvent->execute($itemsToNullify, $salesChannel, $salesEvent);

        $this->priceIndexer->reindexList(
            $this->getProductIdsBySkus->execute(
                array_map(function ($item) {
                    return $item->getSku();
                }, $itemsToNullify)
            )
        );
    }
}
