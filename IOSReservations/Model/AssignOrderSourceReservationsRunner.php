<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Model;

use Magento\InventorySourceSelectionApi\Api\GetDefaultSourceSelectionAlgorithmCodeInterface;
use ReachDigital\IOSReservationsApi\Api\MoveReservationsFromStockToSourceInterface;
use ReachDigital\IOSReservationsApi\Api\AssignOrderSourceReservationsRunnerInterface;
use ReachDigital\IOSReservationsPriorityApi\Api\OrderSelectionServiceInterface;
use ReachDigital\IOSReservationsPriorityApi\Api\GetOrderSelectionAlgorithmCodeInterface;

/**
 * @todo rename to MoveReservationsFromStockToSourceRunner + Interface
 */
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
     * @var MoveReservationsFromStockToSourceInterface
     */
    private $assignOrderSourceReservations;

    /**
     * @var GetDefaultSourceSelectionAlgorithmCodeInterface
     */
    private $getDefaultSourceSelectionAlgorithmCode;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    public function __construct(
        OrderSelectionServiceInterface $orderSelectionService,
        GetOrderSelectionAlgorithmCodeInterface $getOrderSelectionAlgorithmCode,
        MoveReservationsFromStockToSourceInterface $assignOrderSourceReservations,
        GetDefaultSourceSelectionAlgorithmCodeInterface $getDefaultSourceSelectionAlgorithmCode,
        \Psr\Log\LoggerInterface $logger
    )
    {
        $this->orderSelectionService = $orderSelectionService;
        $this->getOrderSelectionAlgorithmCode = $getOrderSelectionAlgorithmCode;
        $this->assignOrderSourceReservations = $assignOrderSourceReservations;
        $this->getDefaultSourceSelectionAlgorithmCode = $getDefaultSourceSelectionAlgorithmCode;
        $this->logger = $logger;
    }

    /**
     * Assign the unassigned orders to their correct sources.
     */
    public function execute(): void
    {
        $orderSearchResults = $this->orderSelectionService->execute(
            null, //@todo Implement functionality with very large sales collections.
            $this->getOrderSelectionAlgorithmCode->execute()
        );
        foreach($orderSearchResults->getItems() as $order) {
            try {
                $this->assignOrderSourceReservations->execute(
                    $order->getEntityId(),
                    $this->getDefaultSourceSelectionAlgorithmCode->execute()
                );
            } catch (\Throwable $e) {
                $this->logger->critical($e);
            }
        }
    }
}
