<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace ReachDigital\IOSReservations\Plugin\MagentoSalesInventory;

use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoCommentCreationInterface;
use Magento\Sales\Api\Data\CreditmemoCreationArgumentsInterface;
use Magento\Sales\Api\Data\CreditmemoItemCreationInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\RefundInvoiceInterface;
use Magento\SalesInventory\Model\Order\ReturnProcessor;
use Magento\SalesInventory\Model\Plugin\Order\ReturnToStockInvoice;

class AlwaysAutoReturnToStockInvoice extends ReturnToStockInvoice
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
     * @var InvoiceRepositoryInterface
     */
    private $invoiceRepository;

    public function __construct(
        ReturnProcessor $returnProcessor,
        CreditmemoRepositoryInterface $creditmemoRepository,
        OrderRepositoryInterface $orderRepository,
        InvoiceRepositoryInterface $invoiceRepository,
        StockConfigurationInterface $stockConfiguration
    ) {
        parent::__construct(
            $returnProcessor,
            $creditmemoRepository,
            $orderRepository,
            $invoiceRepository,
            $stockConfiguration
        );
        $this->returnProcessor = $returnProcessor;
        $this->creditmemoRepository = $creditmemoRepository;
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
    }

    /**
     * @param RefundInvoiceInterface $refundService
     * @param int $resultEntityId
     * @param int $invoiceId
     * @param CreditmemoItemCreationInterface[] $items
     * @param bool|null $isOnline
     * @param bool|null $notify
     * @param bool|null $appendComment
     * @param CreditmemoCommentCreationInterface|null $comment
     * @param CreditmemoCreationArgumentsInterface|null $arguments
     * @return int
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        RefundInvoiceInterface $refundService,
        $resultEntityId,
        $invoiceId,
        array $items = [],
        $isOnline = false,
        $notify = false,
        $appendComment = false,
        CreditmemoCommentCreationInterface $comment = null,
        CreditmemoCreationArgumentsInterface $arguments = null
    ): int {
        $invoice = $this->invoiceRepository->get($invoiceId);
        $order = $this->orderRepository->get($invoice->getOrderId());

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
