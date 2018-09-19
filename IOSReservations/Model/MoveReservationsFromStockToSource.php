<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryShipping\Model\InventoryRequestFromOrderFactory;
use Magento\InventorySourceSelectionApi\Api\SourceSelectionServiceInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSource\AppendSourceReservations;
use ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSource\RevertStockReservations;
use ReachDigital\IOSReservationsApi\Api\MoveReservationsFromStockToSourceInterface;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultInterface;

class MoveReservationsFromStockToSource implements MoveReservationsFromStockToSourceInterface
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var SourceSelectionServiceInterface
     */
    private $sourceSelectionService;

    /**
     * @var InventoryRequestFromOrderFactory
     */
    private $inventoryRequestFromOrderFactory;

    /**
     * @var RevertStockReservations
     */
    private $revertStockReservations;

    /**
     * @var AppendSourceReservations
     */
    private $appendSourceReservations;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        SourceSelectionServiceInterface $sourceSelectionService,
        InventoryRequestFromOrderFactory $inventoryRequestFromOrderFactory,
        RevertStockReservations $revertStockReservations,
        AppendSourceReservations $appendSourceReservations
    ) {
        $this->orderRepository = $orderRepository;
        $this->sourceSelectionService = $sourceSelectionService;
        $this->inventoryRequestFromOrderFactory = $inventoryRequestFromOrderFactory;
        $this->revertStockReservations = $revertStockReservations;
        $this->appendSourceReservations = $appendSourceReservations;
    }

    /**
     * @param int    $orderId
     * @param string $algorithmCode
     *
     * @return SourceReservationResultInterface
     * @throws LocalizedException
     */
    public function execute(int $orderId, string $algorithmCode): SourceReservationResultInterface
    {
        $order = $this->orderRepository->get($orderId);

        //@todo check if test is available to try to reserve for order that is already reserved.
        $sourceSelectionRequest = $this->inventoryRequestFromOrderFactory->create($order);
        $sourceSelectionResult = $this->sourceSelectionService->execute($sourceSelectionRequest, $algorithmCode);

        if (! $sourceSelectionResult->isShippable()) {
            throw new LocalizedException(__('No sources could be selected for order: %1', $orderId));
        }

        //@todo should we implement a TransactionManager manager around this, DB Safety.
        $this->revertStockReservations->execute($order, $sourceSelectionResult);
        $sourceReservationResult = $this->appendSourceReservations->execute($order, $sourceSelectionResult);

        return $sourceReservationResult;
        //Check if the order can be sourced, isn't it already sourced?
        //Revert Stock Reservations
        //Append Source Reservations
    }
}
