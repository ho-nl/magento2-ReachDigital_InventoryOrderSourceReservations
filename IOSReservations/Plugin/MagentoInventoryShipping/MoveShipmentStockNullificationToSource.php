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
use ReachDigital\ISReservations\Model\AppendReservations;
use ReachDigital\ISReservations\Model\ReservationBuilder;
use Magento\Framework\Event\Observer as EventObserver;

class MoveShipmentStockNullificationToSource
{
    /**
     * @var AppendReservations
     */
    private $appendReservations;

    /**
     * @var ReservationBuilder
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

    public function __construct(
        IsSingleSourceModeInterface $isSingleSourceMode,
        DefaultSourceProviderInterface $defaultSourceProvider,
        AppendReservations $appendReservations,
        ReservationBuilder $reservationBuilder,
        GetItemsToDeductFromShipment $getItemsToDeductFromShipment,
        SourceDeductionRequestFromShipmentFactory $sourceDeductionRequestFromShipmentFactory,
        SourceDeductionServiceInterface $sourceDeductionService
    )
    {
        $this->appendReservations = $appendReservations;
        $this->reservationBuilder = $reservationBuilder;
        $this->getItemsToDeductFromShipment = $getItemsToDeductFromShipment;
        $this->sourceDeductionRequestFromShipmentFactory = $sourceDeductionRequestFromShipmentFactory;
        $this->sourceDeductionService = $sourceDeductionService;
        $this->isSingleSourceMode = $isSingleSourceMode;
        $this->defaultSourceProvider = $defaultSourceProvider;
    }

    /*
     * Must wrap execute() to avoid call to private placeCompensatingReservation method
     */
    public function aroundExecute(SourceDeductionProcessor $subject, \Closure $proceed, EventObserver $observer):void
    {
        /** @var \Magento\Sales\Model\Order\Shipment $shipment */
        $shipment = $observer->getEvent()->getShipment();
        if ($shipment->getOrigData('entity_id')) {
            return;
        }

        if (!empty($shipment->getExtensionAttributes())
            && !empty($shipment->getExtensionAttributes()->getSourceCode())) {
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
            $this->placeCompensatingSourceReservation($sourceDeductionRequest);
        }
    }

    private function placeCompensatingSourceReservation(SourceDeductionRequestInterface $sourceDeductionRequest):void
    {
        $reservations = [];

        foreach ($sourceDeductionRequest->getItems() as $item) {
            $this->reservationBuilder->setQuantity($item->getQty());
            $this->reservationBuilder->setSku($item->getSku());
            $this->reservationBuilder->setSourceCode($sourceDeductionRequest->getSourceCode());
            $reservations[] = $this->reservationBuilder->build();
        }

        $this->appendReservations->execute($reservations);
    }
}
