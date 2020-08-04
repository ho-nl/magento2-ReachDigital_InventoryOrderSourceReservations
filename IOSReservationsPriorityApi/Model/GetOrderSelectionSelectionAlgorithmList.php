<?php
declare(strict_types=1);
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\IOSReservationsPriorityApi\Model;

use ReachDigital\IOSReservationsPriorityApi\Api\Data\OrderSelectionAlgorithmInterface;
use ReachDigital\IOSReservationsPriorityApi\Api\Data\OrderSelectionAlgorithmInterfaceFactory;
use ReachDigital\IOSReservationsPriorityApi\Api\GetOrderSelectionSelectionAlgorithmListInterface;

class GetOrderSelectionSelectionAlgorithmList implements GetOrderSelectionSelectionAlgorithmListInterface
{
    /**
     * @var array
     */
    private $availableAlgorithms;

    /**
     * @var OrderSelectionAlgorithmInterfaceFactory
     */
    private $orderSelectionAlgorithmInterfaceFactory;

    public function __construct(
        OrderSelectionAlgorithmInterfaceFactory $sourceSelectionAlgorithmFactory,
        array $availableAlgorithms = []
    ) {
        $this->availableAlgorithms = $availableAlgorithms;
        $this->orderSelectionAlgorithmInterfaceFactory = $sourceSelectionAlgorithmFactory;
    }

    /**
     * @return OrderSelectionAlgorithmInterface[]
     */
    public function execute(): array
    {
        $algorithmsList = [];
        foreach ($this->availableAlgorithms as $data) {
            $algorithmsList[] = $this->orderSelectionAlgorithmInterfaceFactory->create([
                'code' => $data['code'],
                'title' => $data['title'],
                'description' => $data['description'],
            ]);
        }
        return $algorithmsList;
    }
}
