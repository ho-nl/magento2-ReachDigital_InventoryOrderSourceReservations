<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace ReachDigital\IOSReservations\Plugin\MagentoSalesInventory;

use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\RefundOrderInterface;
use Magento\SalesInventory\Model\Order\ReturnProcessor;
use Magento\SalesInventory\Model\Plugin\Order\ReturnToStockOrder;

class AlwaysAutoReturnToStockOrder extends ReturnToStockOrder
{
    /**
     * @var ReturnProcessor
     */
    private $returnProcessor;

    /**
     * @var CreditmemoRepositoryInterface
     */
    private $creditmemoRepository;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var StockConfigurationInterface
     */
    private $stockConfiguration;

    /**
     * ReturnToStockPlugin constructor.
     *
     * @param ReturnProcessor $returnProcessor
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param StockConfigurationInterface $stockConfiguration
     */
    public function __construct(
        ReturnProcessor $returnProcessor,
        CreditmemoRepositoryInterface $creditmemoRepository,
        OrderRepositoryInterface $orderRepository,
        StockConfigurationInterface $stockConfiguration
    ) {
        parent::__construct($returnProcessor, $creditmemoRepository, $orderRepository, $stockConfiguration);
        $this->returnProcessor = $returnProcessor;
        $this->creditmemoRepository = $creditmemoRepository;
        $this->orderRepository = $orderRepository;
        $this->stockConfiguration = $stockConfiguration;
    }

    /**
     * @param RefundOrderInterface $refundService
     * @param int $resultEntityId
     * @param int $orderId
     * @param \Magento\Sales\Api\Data\CreditmemoItemCreationInterface[] $items
     * @param bool|null $notify
     * @param bool|null $appendComment
     * @param \Magento\Sales\Api\Data\CreditmemoCommentCreationInterface|null $comment
     * @param \Magento\Sales\Api\Data\CreditmemoCreationArgumentsInterface|null $arguments
     * @return int
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        RefundOrderInterface $refundService,
        $resultEntityId,
        $orderId,
        array $items = [],
        $notify = false,
        $appendComment = false,
        \Magento\Sales\Api\Data\CreditmemoCommentCreationInterface $comment = null,
        \Magento\Sales\Api\Data\CreditmemoCreationArgumentsInterface $arguments = null
    ): int {
        $order = $this->orderRepository->get($orderId);

        $returnToStockItems = [];
        if (
            $arguments !== null &&
            $arguments->getExtensionAttributes() !== null &&
            $arguments->getExtensionAttributes()->getReturnToStockItems() !== null
        ) {
            $returnToStockItems = $arguments->getExtensionAttributes()->getReturnToStockItems();
        }
        $creditmemo = $this->creditmemoRepository->get($resultEntityId);
        $this->returnProcessor->execute($creditmemo, $order, $returnToStockItems);
        return (int) $resultEntityId;
    }
}
