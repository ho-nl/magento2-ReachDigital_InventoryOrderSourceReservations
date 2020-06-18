<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);
namespace ReachDigital\IOSReservationsPriority\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;

class GetUnSourcedOrderIds
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @return string[]
     */
    public function execute(): array
    {
        $conn = $this->resourceConnection->getConnection();
        $resTable = $this->resourceConnection->getTableName('inventory_source_reservation');
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $orderItemTable = $this->resourceConnection->getTableName('sales_order_item');

        // Fetch IDs of all orders being processed, which have at least one order_item without a source reservation
        $select = $conn
            ->select()
            ->distinct(true)
            ->from(['o' => $orderTable], OrderInterface::ENTITY_ID)
            ->joinInner(['oi' => $orderItemTable], 'oi.order_id = o.entity_id', [])
            ->joinLeft(['r' => $resTable], "r.metadata like concat('%order_item(', oi.item_id, ')%')", [])
            ->where(
                sprintf("r.reservation_id is null AND o.%s = '%s'", OrderInterface::STATE, Order::STATE_PROCESSING)
            );
        return $conn->fetchCol($select);
    }
}
