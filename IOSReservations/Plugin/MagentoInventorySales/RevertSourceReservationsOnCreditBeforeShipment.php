<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Plugin\MagentoInventorySales;

use Magento\Framework\Exception\InputException;
use Magento\InventorySales\Model\ReturnProcessor\ProcessRefundItems;
use Magento\InventorySales\Model\ReturnProcessor\Request\ItemsToRefund;
use Magento\InventorySalesApi\Model\ReturnProcessor\Request\ItemsToRefundInterfaceFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Creditmemo;
use ReachDigital\ISReservations\Model\AppendReservations;
use ReachDigital\ISReservations\Model\MetaData\EncodeMetaData;
use ReachDigital\ISReservations\Model\ResourceModel\GetReservationsByMetadata;
use ReachDigital\ISReservationsApi\Model\ReservationInterface;
use ReachDigital\ISReservationsApi\Model\ReservationBuilderInterface;

class RevertSourceReservationsOnCreditBeforeShipment
{
}
