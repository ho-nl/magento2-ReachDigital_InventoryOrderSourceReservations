<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\InventoryOrderSourceReservationsPriority\Model\Algorithms;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use ReachDigital\InventoryOrderSourceReservationsPriorityApi\Model\OrderSelectionInterface;

class ByDatePlacedAlgorithm implements OrderSelectionInterface
{
    /**
     * @inheritdoc
     */
    public function execute(SearchCriteriaInterface $searchCriteria): OrderSearchResultInterface
    {
        // TODO: Implement execute() method.
    }
}
