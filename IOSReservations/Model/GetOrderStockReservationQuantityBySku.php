<?php

namespace ReachDigital\IOSReservations\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryReservations\Model\ReservationBuilder;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\InventorySales\Model\ResourceModel\GetAssignedStockIdForWebsite;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;

class GetOrderStockReservationQuantityBySku
{
    /**
     * @var ResourceConnection
     */
    private $resource;
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;
    /**
     * @var GetAssignedStockIdForWebsite
     */
    private $getAssignedStockIdForWebsite;
    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;
    /**
     * @var WebsiteRepositoryInterface
     */
    private $websiteRepository;
    /**
     * @var ReservationBuilder
     */
    private $reservationBuilder;

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(
        ResourceConnection $resource,
        OrderRepositoryInterface $orderRepository,
        GetAssignedStockIdForWebsite $getAssignedStockIdForWebsite,
        StoreRepositoryInterface $storeRepository,
        WebsiteRepositoryInterface $websiteRepository,
        ReservationBuilder $reservationBuilder
    ) {
        $this->resource = $resource;
        $this->orderRepository = $orderRepository;
        $this->getAssignedStockIdForWebsite = $getAssignedStockIdForWebsite;
        $this->storeRepository = $storeRepository;
        $this->websiteRepository = $websiteRepository;
        $this->reservationBuilder = $reservationBuilder;
    }

    /**
     * @returns float[]
     * @throws NoSuchEntityException
     */
    public function execute(int $orderId): array
    {
        $connection = $this->resource->getConnection();
        $reservationTable = $this->resource->getTableName('inventory_reservation');

        $order = $this->orderRepository->get($orderId);

        $stockId = $this->getAssignedStockIdForWebsite->execute(
            $this->websiteRepository
                ->getById($this->storeRepository->getById((int) $order->getStoreId())->getWebsiteId())
                ->getCode()
        );

        $skus = array_map(function ($item) {
            return $item->getSku();
        }, $order->getItems());

        $select = $connection
            ->select()
            ->from($reservationTable, [ReservationInterface::SKU, ReservationInterface::QUANTITY])
            ->where(ReservationInterface::STOCK_ID . ' = ?', $stockId)
            ->where(ReservationInterface::SKU . ' IN(?)', $skus)
            ->where(ReservationInterface::METADATA . ' -> "$.object_type" = ?', 'order')
            ->where(ReservationInterface::METADATA . ' -> "$.object_id" = ?', (string) $orderId);

        $reservationsBySku = [];
        foreach ($connection->fetchAll($select) as $reservation) {
            $sku = $reservation['sku'];
            $reservationsBySku[$sku] = $reservationsBySku[$sku] ?? 0;
            $reservationsBySku[$sku] += $reservation['quantity'];
        }

        return $reservationsBySku;
    }
}
