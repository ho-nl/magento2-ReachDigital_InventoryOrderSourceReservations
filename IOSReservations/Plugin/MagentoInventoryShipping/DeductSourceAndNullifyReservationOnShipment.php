<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Plugin\MagentoInventoryShipping;

use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventoryCatalogApi\Model\IsSingleSourceModeInterface;
use Magento\InventoryShipping\Model\GetItemsToDeductFromShipment;
use Magento\InventoryShipping\Model\SourceDeductionRequestFromShipmentFactory;
use Magento\InventoryShipping\Observer\SourceDeductionProcessor;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionRequestInterface;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionServiceInterface;
use Magento\Sales\Model\Order;
use ReachDigital\ISReservations\Model\AppendSourceReservations;
use ReachDigital\ISReservations\Model\MetaData\EncodeMetaData;
use ReachDigital\ISReservations\Model\SourceReservationBuilder;
use Magento\Framework\Event\Observer as EventObserver;

class DeductSourceAndNullifyReservationOnShipment
{
    /**
     * @var AppendSourceReservations
     */
    private $appendReservations;

    /**
     * @var SourceReservationBuilder
     */
    private $reservationBuilder;

    /**
     * @var GetItemsToDeductFromShipment
     */
    private $getItemsToDeductFromShipment;

    /**
     * @var SourceDeductionRequestFromShipmentFactory
     */
    private $sourceDeductionRequestFromShipmentFactory;

    /**
     * @var SourceDeductionServiceInterface
     */
    private $sourceDeductionService;

    /**
     * @var IsSingleSourceModeInterface
     */
    private $isSingleSourceMode;

    /**
     * @var DefaultSourceProviderInterface
     */
    private $defaultSourceProvider;

    /**
     * @var EncodeMetaData
     */
    private $encodeMetaData;

    public function __construct(
        IsSingleSourceModeInterface $isSingleSourceMode,
        DefaultSourceProviderInterface $defaultSourceProvider,
        AppendSourceReservations $appendReservations,
        SourceReservationBuilder $reservationBuilder,
        GetItemsToDeductFromShipment $getItemsToDeductFromShipment,
        SourceDeductionRequestFromShipmentFactory $sourceDeductionRequestFromShipmentFactory,
        SourceDeductionServiceInterface $sourceDeductionService,
        EncodeMetaData $encodeMetaData
    ) {
        $this->appendReservations = $appendReservations;
        $this->reservationBuilder = $reservationBuilder;
        $this->getItemsToDeductFromShipment = $getItemsToDeductFromShipment;
        $this->sourceDeductionRequestFromShipmentFactory = $sourceDeductionRequestFromShipmentFactory;
        $this->sourceDeductionService = $sourceDeductionService;
        $this->isSingleSourceMode = $isSingleSourceMode;
        $this->defaultSourceProvider = $defaultSourceProvider;
        $this->encodeMetaData = $encodeMetaData;
    }

    /**
     * Plugin to perform source deduction on shipment, and nullify the related source reservation instead of stock
     * reservation.
     *
     * Must wrap execute() to avoid call to private placeCompensatingReservation method (which does the no longer needed
     * stock reservation nullification)
     *
     * @param SourceDeductionProcessor $subject
     * @param \Closure                 $proceed
     * @param EventObserver            $observer
     *
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Validation\ValidationException
     */
    public function aroundExecute(SourceDeductionProcessor $subject, \Closure $proceed, EventObserver $observer): void
    {
        /** @var \Magento\Sales\Model\Order\Shipment $shipment */
        /** @noinspection PhpUndefinedMethodInspection */
        $shipment = $observer->getEvent()->getShipment();
        if ($shipment->getOrigData('entity_id')) {
            return;
        }

        if (
            $shipment->getExtensionAttributes() !== null &&
            $shipment->getExtensionAttributes()->getSourceCode() !== null
        ) {
            $sourceCode = $shipment->getExtensionAttributes()->getSourceCode();
        } elseif ($this->isSingleSourceMode->execute()) {
            $sourceCode = $this->defaultSourceProvider->getCode();
        }

        $shipmentItems = $this->getItemsToDeductFromShipment->execute($shipment);

        if (!empty($shipmentItems)) {
            $sourceDeductionRequest = $this->sourceDeductionRequestFromShipmentFactory->execute(
                $shipment,
                $sourceCode,
                $shipmentItems
            );
            $this->sourceDeductionService->execute($sourceDeductionRequest);
            $this->placeCompensatingSourceReservation($sourceDeductionRequest, $shipment->getOrder());
        }
    }

    /**
     * @param SourceDeductionRequestInterface $sourceDeductionRequest
     *
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Validation\ValidationException
     */
    private function placeCompensatingSourceReservation(
        SourceDeductionRequestInterface $sourceDeductionRequest,
        Order $order
    ): void {
        $reservations = [];

        $metaData = $this->encodeMetaData->execute(['order' => $order->getEntityId()]);

        foreach ($sourceDeductionRequest->getItems() as $item) {
            $this->reservationBuilder->setQuantity($item->getQty());
            $this->reservationBuilder->setSku($item->getSku());
            /** @noinspection DisconnectedForeachInstructionInspection */
            $this->reservationBuilder->setSourceCode($sourceDeductionRequest->getSourceCode());
            /** @noinspection DisconnectedForeachInstructionInspection */
            $this->reservationBuilder->setMetadata($metaData);
            $reservations[] = $this->reservationBuilder->build();
        }

        $this->appendReservations->execute($reservations);
    }
}
