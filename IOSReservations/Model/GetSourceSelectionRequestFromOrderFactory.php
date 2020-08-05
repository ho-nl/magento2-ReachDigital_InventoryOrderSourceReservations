<?php
/**
 * Based on InventoryRequestFromOrderFactory, but takes the current source reservations in account.
 */
namespace ReachDigital\IOSReservations\Model;

use Magento\InventorySalesApi\Model\GetSkuFromOrderItemInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\InventorySourceSelectionApi\Api\Data\InventoryRequestInterface;
use Magento\InventorySourceSelectionApi\Api\Data\InventoryRequestInterfaceFactory;
use Magento\InventorySourceSelectionApi\Api\Data\ItemRequestInterfaceFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order\Item as OrderItem;
use ReachDigital\IOSReservationsApi\Exception\CouldNotCreateSourceSelectionRequestFromOrder;
use ReachDigital\ISReservationsApi\Api\EncodeMetaDataInterface;
use ReachDigital\ISReservationsApi\Api\GetReservationsByMetadataInterface;

class GetSourceSelectionRequestFromOrderFactory
{
    /**
     * @var ItemRequestInterfaceFactory
     */
    private $itemRequestFactory;

    /**
     * @var InventoryRequestInterfaceFactory
     */
    private $inventoryRequestFactory;

    /**
     * @var StockByWebsiteIdResolverInterface
     */
    private $stockByWebsiteIdResolver;

    /**
     * @var GetSkuFromOrderItemInterface
     */
    private $getSkuFromOrderItem;
    /**
     * @var GetReservationsByMetadataInterface
     */
    private $getReservationsByMetadata;
    /**
     * @var EncodeMetaDataInterface
     */
    private $encodeMetaData;

    public function __construct(
        ItemRequestInterfaceFactory $itemRequestFactory,
        InventoryRequestInterfaceFactory $inventoryRequestFactory,
        StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver,
        GetSkuFromOrderItemInterface $getSkuFromOrderItem,
        GetReservationsByMetadataInterface $getReservationsByMetadata,
        EncodeMetaDataInterface $encodeMetaData
    ) {
        $this->itemRequestFactory = $itemRequestFactory;
        $this->inventoryRequestFactory = $inventoryRequestFactory;
        $this->stockByWebsiteIdResolver = $stockByWebsiteIdResolver;
        $this->getSkuFromOrderItem = $getSkuFromOrderItem;
        $this->getReservationsByMetadata = $getReservationsByMetadata;
        $this->encodeMetaData = $encodeMetaData;
    }

    /**
     * @param OrderInterface $order
     * @return InventoryRequestInterface
     * @throws CouldNotCreateSourceSelectionRequestFromOrder
     */
    public function create(OrderInterface $order): InventoryRequestInterface
    {
        $reservationQtyBySku = $this->getReservationQtyBySku($order);

        $requestItems = [];
        $websiteId = $order->getStore()->getWebsiteId();
        $stockId = (int) $this->stockByWebsiteIdResolver->execute((int) $websiteId)->getStockId();

        /** @var OrderItemInterface|OrderItem $orderItem */
        foreach ($order->getItems() as $orderItem) {
            $itemSku = $this->getSkuFromOrderItem->execute($orderItem);
            $qtyToDeliver = $orderItem->getQtyToShip();

            if (isset($reservationQtyBySku[$itemSku])) {
                $resultingQty = $reservationQtyBySku[$itemSku] + $qtyToDeliver;

                if ($resultingQty > 0) {
                    // reservation: -2 + qtyToDelivery: 3 = 1, 1 to deliver, 0 to use later.
                    $qtyToDeliver = $resultingQty;
                    $reservationQtyBySku[$itemSku] = 0;
                } else {
                    // Can be the case if multiple order lines with the same SKU are present.
                    // reservation: -4 + qtyToDelivery: 3 = -1, 0 to deliver, -1 to use later.
                    $qtyToDeliver = 0;
                    $reservationQtyBySku[$itemSku] = $resultingQty;
                }
            }

            //check if order item is not delivered yet
            if (
                $orderItem->isDeleted() ||
                $orderItem->getParentItemId() ||
                $this->isZero((float) $qtyToDeliver) ||
                $orderItem->getIsVirtual()
            ) {
                continue;
            }

            $requestItems[] = $this->itemRequestFactory->create([
                'sku' => $itemSku,
                'qty' => $qtyToDeliver,
            ]);
        }

        if (!$requestItems) {
            throw CouldNotCreateSourceSelectionRequestFromOrder::create($order->getEntityId());
        }

        return $this->inventoryRequestFactory->create([
            'stockId' => $stockId,
            'items' => $requestItems,
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
     * @param OrderInterface $order
     * @return number[]
     */
    private function getReservationQtyBySku(OrderInterface $order): array
    {
        $reservations = $this->getReservationsByMetadata->execute(
            $this->encodeMetaData->execute(['order' => $order->getEntityId()])
        );
        /** @var number[] $reservationQtyBySku */
        $reservationQtyBySku = [];
        foreach ($reservations as $reservation) {
            if (isset($reservationQtyBySku[$reservation->getSku()])) {
                $reservationQtyBySku[$reservation->getSku()] += $reservation->getQuantity();
            } else {
                $reservationQtyBySku[$reservation->getSku()] = $reservation->getQuantity();
            }
        }
        return $reservationQtyBySku;
    }
}
