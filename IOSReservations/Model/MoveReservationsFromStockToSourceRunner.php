<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Validation\ValidationException;
use Magento\InventorySourceSelectionApi\Api\GetDefaultSourceSelectionAlgorithmCodeInterface;
use Psr\Log\LoggerInterface;
use ReachDigital\IOSReservationsApi\Api\MoveReservationsFromStockToSourceInterface;
use ReachDigital\IOSReservationsApi\Api\MoveReservationsFromStockToSourceRunnerInterface;
use ReachDigital\IOSReservationsApi\Exception\CouldNotCreateSourceSelectionRequestFromOrder;
use ReachDigital\IOSReservationsApi\Exception\CouldNotFullySelectSourcesForOrder;
use ReachDigital\IOSReservationsPriorityApi\Api\OrderSelectionServiceInterface;
use ReachDigital\IOSReservationsPriorityApi\Api\GetOrderSelectionAlgorithmCodeInterface;

class MoveReservationsFromStockToSourceRunner implements MoveReservationsFromStockToSourceRunnerInterface
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
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        OrderSelectionServiceInterface $orderSelectionService,
        GetOrderSelectionAlgorithmCodeInterface $getOrderSelectionAlgorithmCode,
        MoveReservationsFromStockToSourceInterface $assignOrderSourceReservations,
        GetDefaultSourceSelectionAlgorithmCodeInterface $getDefaultSourceSelectionAlgorithmCode,
        LoggerInterface $logger
    ) {
        $this->orderSelectionService = $orderSelectionService;
        $this->getOrderSelectionAlgorithmCode = $getOrderSelectionAlgorithmCode;
        $this->assignOrderSourceReservations = $assignOrderSourceReservations;
        $this->getDefaultSourceSelectionAlgorithmCode = $getDefaultSourceSelectionAlgorithmCode;
        $this->logger = $logger;
    }

    /**
     * Assign the unassigned orders to their correct sources.
     *
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws ValidationException
     */
    public function execute(): void
    {
        $orderSearchResults = $this->orderSelectionService->execute(
            1000,
            $this->getOrderSelectionAlgorithmCode->execute()
        );
        foreach ($orderSearchResults->getItems() as $order) {
            try {
                $this->assignOrderSourceReservations->execute(
                    (int) $order->getEntityId(),
                    $this->getDefaultSourceSelectionAlgorithmCode->execute()
                );
            } catch (CouldNotCreateSourceSelectionRequestFromOrder | CouldNotFullySelectSourcesForOrder $e) {
                $this->logger->info($e->getMessage());
            }
        }
    }
}
