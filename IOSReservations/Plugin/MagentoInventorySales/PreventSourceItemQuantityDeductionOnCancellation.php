<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Plugin\MagentoInventorySales;

use Closure;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Validation\ValidationException;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\InventorySales\Observer\CatalogInventory\CancelOrderItemObserver;
use Magento\Sales\Model\Order\Item as OrderItem;
use Psr\Log\LoggerInterface;
use ReachDigital\ISReservations\Model\AppendSourceReservations;
use ReachDigital\ISReservations\Model\MetaData\EncodeMetaData;
use ReachDigital\ISReservations\Model\ResourceModel\GetReservationsByMetadata;
use ReachDigital\ISReservations\Model\SourceReservationBuilder;
use ReachDigital\ISReservationsApi\Api\Data\SourceReservationInterface;

class PreventSourceItemQuantityDeductionOnCancellation
{
    /**
     * @var SerializerInterface
     */
    private $serializer;
    /**
     * @var ResourceConnection
     */
    private $resource;
    /**
     * @var GetReservationsByMetadata
     */
    private $getReservationsByMetadata;
    /**
     * @var EncodeMetaData
     */
    private $encodeMetaData;
    /**
     * @var AppendSourceReservations
     */
    private $appendSourceReservations;
    /**
     * @var SourceReservationBuilder
     */
    private $sourceReservationBuilder;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        SerializerInterface $serializer,
        ResourceConnection $resource,
        GetReservationsByMetadata $getReservationsByMetadata,
        EncodeMetaData $encodeMetaData,
        AppendSourceReservations $appendSourceReservations,
        SourceReservationBuilder $sourceReservationBuilder,
        LoggerInterface $logger
    ) {
        $this->serializer = $serializer;
        $this->resource = $resource;
        $this->getReservationsByMetadata = $getReservationsByMetadata;
        $this->encodeMetaData = $encodeMetaData;
        $this->appendSourceReservations = $appendSourceReservations;
        $this->sourceReservationBuilder = $sourceReservationBuilder;
        $this->logger = $logger;
    }

    /**
     * Around plugin to prevent reservation item quantity deduction when order is cancelled and order is already
     * assigned, can happen when assigning happens before invoicing, for instance when an order is authorised but not
     * yet captured.
     *
     * @param CancelOrderItemObserver $subject
     * @param Closure $proceed
     * @param Observer $observer
     * @return mixed
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws ValidationException
     */
    public function aroundExecute(CancelOrderItemObserver $subject, Closure $proceed, Observer $observer)
    {
        /** @var OrderItem $orderItem */
        $orderItem = $observer->getEvent()->getItem();

        $logger = $this->createLogger($orderItem->getOrder()->getId(), $orderItem->getItemId());
        $logger('start_cancellation');

        $connection = $this->resource->getConnection();
        $reservationTable = $this->resource->getTableName('inventory_reservation');

        $metadata = $this->serializer->serialize([
            'event_type' => 'order_assigned',
            'object_type' => 'order',
            'object_id' => $orderItem->getOrder()->getEntityId(),
        ]);

        // Check if order item is already assigned
        $select = $connection
            ->select()
            ->from($reservationTable, [ReservationInterface::QUANTITY => 'SUM(' . ReservationInterface::QUANTITY . ')'])
            ->where(ReservationInterface::SKU . ' = ?', $orderItem->getSku())
            ->where('metadata = ?', $metadata)
            ->limit(1);

        $assignedQty = $connection->fetchOne($select);

        if (!$assignedQty) {
            $logger('revert_stock_start');

            // Not assigned yet, proceed adding order_canceled reservation
            $proceed($observer);
            $logger('revert_stock_end');
            return;
        }

        $logger('revert_source_start');

        // Already assigned; skip adding order_canceled reservation,
        // nullify source reservations instead

        $reservations = $this->getReservationsBySource((int) $orderItem->getOrderId(), $orderItem->getSku());

        $nullifications = [];
        foreach ($reservations as $sourceCode => $sourceReservationQty) {
            $this->sourceReservationBuilder->setSku($orderItem->getSku());
            $this->sourceReservationBuilder->setQuantity((float) -$sourceReservationQty);
            $this->sourceReservationBuilder->setSourceCode($sourceCode);
            $this->sourceReservationBuilder->setMetadata(
                $this->encodeMetaData->execute([
                    'order' => $orderItem->getOrderId(),
                    'refund_compensation' => null,
                ])
            );
            $nullifications[] = $this->sourceReservationBuilder->build();
        }

        $this->appendSourceReservations->execute($nullifications);

        $logger('revert_source_end');
        return;
    }

    /**
     * @param int $orderId
     * @param string $sku
     * @return SourceReservationInterface[]
     */
    private function getReservationsBySource(int $orderId, string $sku): array
    {
        $reservations = $this->getReservationsByMetadata->execute(
            $this->encodeMetaData->execute(['order' => $orderId])
        );

        $reservationsBySkuAndSource = [];
        foreach ($reservations as $reservation) {
            $reservationSku = $reservation->getSku();
            $sourceCode = $reservation->getSourceCode();

            $reservationsBySkuAndSource[$reservationSku] = $reservationsBySkuAndSource[$reservationSku] ?? [];
            $reservationsBySkuAndSource[$reservationSku][$sourceCode] =
                $reservationsBySkuAndSource[$reservationSku][$sourceCode] ?? 0;
            $reservationsBySkuAndSource[$reservationSku][$sourceCode] += $reservation->getQuantity();
        }

        return $reservationsBySkuAndSource[$sku] ?? [];
    }

    private function createLogger($orderId, $orderItemId)
    {
        return function (string $stage) use ($orderId, $orderItemId) {
            $this->logger->info(
                json_encode([
                    'module' => 'reach-digital/magento2-order-source-reservations',
                    'order' => $orderId,
                    'order_item' => $orderItemId,
                    'stage' => $stage,
                ])
            );
        };
    }
}
