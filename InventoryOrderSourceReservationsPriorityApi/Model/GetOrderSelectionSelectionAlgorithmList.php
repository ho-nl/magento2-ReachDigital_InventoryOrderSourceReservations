<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\InventoryOrderSourceReservationsPriorityApi\Model;

use ReachDigital\InventoryOrderSourceReservationsPriorityApi\Api\Data\OrderSelectionAlgorithmInterfaceFactory;
use ReachDigital\InventoryOrderSourceReservationsPriorityApi\Api\GetOrderSelectionSelectionAlgorithmListInterface;

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
     * @return \ReachDigital\InventoryOrderSourceReservationsPriorityApi\Api\Data\OrderSelectionAlgorithmInterface[]
     */
    public function execute(): array
    {
        $algorithmsList = [];
        foreach ($this->availableAlgorithms as $data) {
            $algorithmsList[] = $this->orderSelectionAlgorithmInterfaceFactory->create([
                'code' => $data['code'],
                'title' => $data['title'],
                'description' => $data['description']
            ]);
        }
        return $algorithmsList;
    }
}
