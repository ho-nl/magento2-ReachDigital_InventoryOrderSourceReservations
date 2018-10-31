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
        array $orderSelectionMethods = []
    ) {
        $this->objectManager = $objectManager;
        $this->orderSelectionMethods = $orderSelectionMethods;
    }

    /**
     * Get a list of orders that need to be assigned to a source.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @param string                                         $algorithmCode
     *
     * @return mixed
     * @throws \LogicException
     */
    public function execute(
        ?int $limit,
        string $algorithmCode
    ): OrderSearchResultInterface {
        if (!isset($this->orderSelectionMethods[$algorithmCode])) {
            throw new \LogicException(
                (string) __('There is no such Order Selection Algorithm implemented: %1', $algorithmCode)
            );
        }
        $orderSelectionClassName = $this->orderSelectionMethods[$algorithmCode];

        /** @var OrderSelectionInterface $selection */
        $selection = $this->objectManager->create($orderSelectionClassName);
        if (false === $selection instanceof OrderSelectionInterface) {
            throw new \LogicException(
                (string) __('%1 doesn\'t implement OrderSelectionInterface', $orderSelectionClassName)
            );
        }
        return $selection->execute($limit);
    }
}
