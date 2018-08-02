<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\InventoryOrderSourceReservations;

use Magento\InventorySourceSelectionApi\Api\GetDefaultSourceSelectionAlgorithmCodeInterface;
use ReachDigital\InventoryOrderSourceReservationsApi\Api\AssignOrderSourceReservationsInterface;
use ReachDigital\InventoryOrderSourceReservationsApi\Api\AssignOrderSourceReservationsRunnerInterface;
use ReachDigital\InventoryOrderSourceReservationsPriorityApi\Api\OrderSelectionServiceInterface;
use ReachDigital\InventoryOrderSourceReservationsPriorityApi\GetOrderSelectionAlgorithmCodeInterface;

class AssignOrderSourceReservationsRunner implements AssignOrderSourceReservationsRunnerInterface
{

    /**
     * @var OrderSelectionServiceInterface
     */
    private $orderSelectionService;

    /**
     * @var GetOrderSelectionAlgorithmCodeInterface
     */
    private $getOrderSelectionAlgorithmCode;

    /**
     * @var AssignOrderSourceReservationsInterface
     */
    private $assignOrderSourceReservations;

    /**
     * @var GetDefaultSourceSelectionAlgorithmCodeInterface
     */
    private $getDefaultSourceSelectionAlgorithmCode;

    public function __construct(
        OrderSelectionServiceInterface $orderSelectionService,
        GetOrderSelectionAlgorithmCodeInterface $getOrderSelectionAlgorithmCode,
        AssignOrderSourceReservationsInterface $assignOrderSourceReservations,
        GetDefaultSourceSelectionAlgorithmCodeInterface $getDefaultSourceSelectionAlgorithmCode
    )
    {
        $this->orderSelectionService = $orderSelectionService;
        $this->getOrderSelectionAlgorithmCode = $getOrderSelectionAlgorithmCode;
        $this->assignOrderSourceReservations = $assignOrderSourceReservations;
        $this->getDefaultSourceSelectionAlgorithmCode = $getDefaultSourceSelectionAlgorithmCode;
    }

    /**
     * Assign the unassigned orders to their correct sources.
     */
    public function execute(): void
    {
        $orderSearchResults = $this->orderSelectionService->execute(
            null, //@todo Limit?
            $this->getOrderSelectionAlgorithmCode->execute()
        );
        foreach($orderSearchResults->getItems() as $order) {
            //@todo Exception handling in loop? Should we display the error in the Admin Panel?
            $this->assignOrderSourceReservations->execute(
                $order->getEntityId(),
                $this->getDefaultSourceSelectionAlgorithmCode->execute()
            );
        }
    }
}
