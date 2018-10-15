<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Plugin\InventorySales;

use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface;

class MoveShipmentStockNullificationToSource {

    public function aroundExecute(
        PlaceReservationsForSalesEventInterface $subject,
        \Closure $proceed,
        array $items,
        SalesChannelInterface $salesChannel,
        SalesEventInterface $salesEvent
    ) {
        // @todo hook into sales_order_shipment_save_after event. See vendor/magento/module-inventory-shipping/etc/events.xml:9
        // @see \Magento\InventoryShipping\Observer\SourceDeductionProcessor::placeCompensatingReservation

        return $proceed($items, $salesChannel, $salesEvent);
    }
}
