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
use Magento\InventorySourceSelectionApi\Api\SourceSelectionServiceInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSource\AppendSourceReservations;
use ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSource\RevertStockReservations;
use ReachDigital\IOSReservationsApi\Api\MoveReservationsFromStockToSourceInterface;
use ReachDigital\IOSReservationsApi\Api\Data\SourceReservationResultInterface;
use ReachDigital\IOSReservationsApi\Exception\CouldNotCreateSourceSelectionRequestFromOrder;
use ReachDigital\IOSReservationsApi\Exception\CouldNotFullySelectSourcesForOrder;

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
     * @var GetSourceSelectionRequestFromOrderFactory
     */
    private $getSourceSelectionRequestFromOrderFactory;

    /**
     * @var RevertStockReservations
     */
    private $revertStockReservations;

    /**
     * @var AppendSourceReservations
     */
    private $appendSourceReservations;

    /**
     * @var GetOrderSourceReservationConfig
     */
    private $getOrderSourceReservationConfig;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        SourceSelectionServiceInterface $sourceSelectionService,
        GetSourceSelectionRequestFromOrderFactory $getSourceSelectionRequestFromOrderFactory,
        RevertStockReservations $revertStockReservations,
        AppendSourceReservations $appendSourceReservations,
        GetOrderSourceReservationConfig $getOrderSourceReservationConfig
    ) {
        $this->orderRepository = $orderRepository;
        $this->sourceSelectionService = $sourceSelectionService;
        $this->getSourceSelectionRequestFromOrderFactory = $getSourceSelectionRequestFromOrderFactory;
        $this->revertStockReservations = $revertStockReservations;
        $this->appendSourceReservations = $appendSourceReservations;
        $this->getOrderSourceReservationConfig = $getOrderSourceReservationConfig;
    }

    /**
     * @param int $orderId
     * @param string $algorithmCode
     *
     * @return SourceReservationResultInterface
     * @throws CouldNotCreateSourceSelectionRequestFromOrder
     * @throws CouldNotFullySelectSourcesForOrder
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws ValidationException
     */
    public function execute(int $orderId, string $algorithmCode): SourceReservationResultInterface
    {
        $order = $this->orderRepository->get($orderId);

        $sourceSelectionRequest = $this->getSourceSelectionRequestFromOrderFactory->create($order);
        $sourceSelectionResult = $this->sourceSelectionService->execute($sourceSelectionRequest, $algorithmCode);

        $allowPartialShipping = $this->getOrderSourceReservationConfig->allowPartialShipping();
        if (!$allowPartialShipping && !$sourceSelectionResult->isShippable()) {
            throw CouldNotFullySelectSourcesForOrder::create($orderId);
        }

        $this->revertStockReservations->execute($order, $sourceSelectionResult);
        return $this->appendSourceReservations->execute($order, $sourceSelectionResult);
    }
}
