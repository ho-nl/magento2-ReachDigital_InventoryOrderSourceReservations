<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace ReachDigital\IOSReservationsPriorityApi\Model;

use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use ReachDigital\IOSReservationsPriorityApi\Api\OrderSelectionServiceInterface;

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
        ?\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria,
        string $algorithmCode
    ): OrderSearchResultInterface {
        if (!isset($this->orderSelectionMethods[$algorithmCode])) {
            throw new \LogicException(
                (string) __('There is no such Order Selection Algorithm implemented: %1', $algorithmCode)
            );
        }
        $sourceSelectionClassName = $this->orderSelectionMethods[$algorithmCode];

        /** @var OrderSelectionInterface $selection */
        $selection = $this->objectManager->create($sourceSelectionClassName);
        if (false === $selection instanceof OrderSelectionInterface) {
            throw new \LogicException(
                (string) __('%1 doesn\'t implement OrderSelectionInterface', $sourceSelectionClassName)
            );
        }
        return $selection->execute($searchCriteria);
    }
}
