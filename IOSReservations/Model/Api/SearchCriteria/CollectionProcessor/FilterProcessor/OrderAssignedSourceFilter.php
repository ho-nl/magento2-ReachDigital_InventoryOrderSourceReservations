<?php
declare(strict_types=1);
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace ReachDigital\IOSReservations\Model\Api\SearchCriteria\CollectionProcessor\FilterProcessor;

use Magento\Framework\Api\Filter;
use Magento\Framework\Api\SearchCriteria\CollectionProcessor\FilterProcessor\CustomFilterInterface;
use Magento\Framework\Data\Collection\AbstractDb;

class OrderAssignedSourceFilter implements CustomFilterInterface
{
    /**
     * Filter orders on having the specified source assigned to one of its order items
     *
     * @param Filter     $filter
     * @param AbstractDb $collection
     *
     * @return bool Whether the filter was applied
     * @since 101.0.0
     */
    public function apply(Filter $filter, AbstractDb $collection): bool
    {
        if ( !($collection instanceof \Magento\Sales\Model\ResourceModel\Order\Collection)
            || $filter->getConditionType() !== 'eq' ) {
            return false;
        }
        $select = $collection->getSelect();

        $filterVaue = $filter->getValue();

        // Join source reservations, filter out reservations for other sources
        $select->joinInner(
            [ 'source_reservation' => $collection->getTable('inventory_source_reservation') ],
            // @fixme: Hardcoded metadata format. Should change ReachDigital\ISReservations\Model\MetaData\EncodeMetaData
            // @fixme: API so we can use it for building query parts as well
            $select->getAdapter()->quoteInto(
                'source_reservation.metadata LIKE concat(\'order(\',main_table.entity_id,\')%\') '.
                'AND source_reservation.source_code = ?',
                $filterVaue),
            [ ]
        );
        $select->distinct();

        return true;
    }
}
