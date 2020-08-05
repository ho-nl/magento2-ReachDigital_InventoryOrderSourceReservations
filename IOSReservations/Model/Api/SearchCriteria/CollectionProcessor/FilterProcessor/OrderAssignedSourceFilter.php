<?php
declare(strict_types=1);
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace ReachDigital\IOSReservations\Model\Api\SearchCriteria\CollectionProcessor\FilterProcessor;

use InvalidArgumentException;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\SearchCriteria\CollectionProcessor\FilterProcessor\CustomFilterInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Sales\Model\ResourceModel\Order\Collection;

class OrderAssignedSourceFilter implements CustomFilterInterface
{
    /**
     * Filter orders on its order items' assigned sources. Supported condition types are:
     *
     * notnull
     * null
     * eq
     * neq
     * like
     * nlike
     * in
     * notin
     *
     * @param Filter     $filter
     * @param AbstractDb $collection
     *
     * @return bool Whether the filter was applied
     * @since 101.0.0
     */
    public function apply(Filter $filter, AbstractDb $collection): bool
    {
        if (!($collection instanceof Collection)) {
            return false;
        }

        $select = $collection->getSelect();
        $adapter = $select->getAdapter();
        $filterValue = $filter->getValue();
        $filterField = 'source_reservation.source_code';

        $conditionMap = [
            'notnull' => $filterField . ' IS NOT NULL',
            'null' => $filterField . ' IS NULL',
            'eq' => $adapter->quoteInto($filterField . ' = ?', $filterValue),
            'neq' => $adapter->quoteInto($filterField . ' != ?', $filterValue),
            'like' => $adapter->quoteInto($filterField . ' LIKE ?', $filterValue),
            'nlike' => $adapter->quoteInto($filterField . ' NOT LIKE ?', $filterValue),
            'in' => $adapter->quoteInto(
                $filterField . ' IN (?)',
                $filterValue ? explode(',', $filterValue) : $filterValue
            ),
            'notin' => $adapter->quoteInto(
                $filterField . ' NOT IN (?)',
                $filterValue ? explode(',', $filterValue) : $filterValue
            ),
        ];

        if (!array_key_exists($filter->getConditionType(), $conditionMap)) {
            throw new InvalidArgumentException(
                (string) __('Unsupported filter condition: %1', $filter->getConditionType())
            );
        }

        // Join source reservations to apply filter
        $select->joinInner(
            ['source_reservation' => $collection->getTable('inventory_source_reservation')],
            'source_reservation.metadata LIKE concat(\'order(\',main_table.entity_id,\')%\') AND ' .
                $conditionMap[$filter->getConditionType()],
            []
        );
        $select->distinct();
        return true;
    }
}
