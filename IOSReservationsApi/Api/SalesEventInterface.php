<?php

namespace ReachDigital\IOSReservationsApi\Api;

interface SalesEventInterface extends \Magento\InventorySalesApi\Api\Data\SalesEventInterface
{
    const EVENT_ORDER_ASSIGNED = 'order_assigned';
}
