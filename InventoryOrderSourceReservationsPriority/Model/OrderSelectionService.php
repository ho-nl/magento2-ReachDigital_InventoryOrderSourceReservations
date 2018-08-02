<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\InventoryOrderSourceReservationsPriority\Model;

use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use ReachDigital\InventoryOrderSourceReservationsPriorityApi\Api\OrderSelectionServiceInterface;
use ReachDigital\InventoryOrderSourceReservationsPriorityApi\Model\OrderSelectionInterface;

class OrderSelectionService implements OrderSelectionServiceInterface
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var array
     */
    private $orderSelectionMethods;

    public function __construct(
        ObjectManagerInterface $objectManager,
        array $orderSectionMethods = []
    ) {
        $this->objectManager = $objectManager;
        $this->orderSelectionMethods = $orderSectionMethods;
    }

    /**
     * Get a list of orders that need to be assigned to a source. Sorted by priority
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @param string                                         $algorithmCode
     *
     * @return mixed
     * @throws \LogicException
     */
    public function execute(
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria,
        string $algorithmCode
    ): OrderSearchResultInterface {
        if (!isset($this->orderSelectionMethods[$algorithmCode])) {
            throw new \LogicException(
                __('There is no such Order Selection Algorithm implemented: %1', $algorithmCode)
            );
        }
        $sourceSelectionClassName = $this->orderSelectionMethods[$algorithmCode];

        /** @var OrderSelectionInterface $sourceSelectionAlgorithm */
        $sourceSelectionAlgorithm = $this->objectManager->create($sourceSelectionClassName);
        if (false === $sourceSelectionAlgorithm instanceof OrderSelectionInterface) {
            throw new \LogicException(
                __('%1 doesn\'t implement OrderSelectionInterface', $sourceSelectionClassName)
            );
        }
        return $sourceSelectionAlgorithm->execute($searchCriteria);
    }
}
