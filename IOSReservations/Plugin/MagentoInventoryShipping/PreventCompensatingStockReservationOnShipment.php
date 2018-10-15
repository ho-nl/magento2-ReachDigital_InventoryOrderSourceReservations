<?php

/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace ReachDigital\IOSReservations\Plugin\MagentoInventoryShipping;

use Magento\InventoryShipping\Observer\SourceDeductionProcessor;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionServiceInterface;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventoryCatalogApi\Model\IsSingleSourceModeInterface;
use Magento\InventoryShipping\Model\GetItemsToDeductFromShipment;
use Magento\InventoryShipping\Model\SourceDeductionRequestFromShipmentFactory;

class PreventCompensatingStockReservationOnShipment
{
    /**
     * @var IsSingleSourceModeInterface
     */
    private $isSingleSourceMode;

    /**
     * @var DefaultSourceProviderInterface
     */
    private $defaultSourceProvider;

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
     * @param IsSingleSourceModeInterface               $isSingleSourceMode
     * @param DefaultSourceProviderInterface            $defaultSourceProvider
     * @param GetItemsToDeductFromShipment              $getItemsToDeductFromShipment
     * @param SourceDeductionRequestFromShipmentFactory $sourceDeductionRequestFromShipmentFactory
     * @param SourceDeductionServiceInterface           $sourceDeductionService
     */
    public function __construct(
        IsSingleSourceModeInterface $isSingleSourceMode,
        DefaultSourceProviderInterface $defaultSourceProvider,
        GetItemsToDeductFromShipment $getItemsToDeductFromShipment,
        SourceDeductionRequestFromShipmentFactory $sourceDeductionRequestFromShipmentFactory,
        SourceDeductionServiceInterface $sourceDeductionService
    ) {
        $this->isSingleSourceMode = $isSingleSourceMode;
        $this->defaultSourceProvider = $defaultSourceProvider;
        $this->getItemsToDeductFromShipment = $getItemsToDeductFromShipment;
        $this->sourceDeductionRequestFromShipmentFactory = $sourceDeductionRequestFromShipmentFactory;
        $this->sourceDeductionService = $sourceDeductionService;
    }

    /**
     * Replace core implementation to prevent creation of compensating stock reservations during shipping, as these are
     * now done earlier, when reservations are moved from stock to source during source assignment.
     *
     * @noinspection PhpUnusedParameterInspection
     *
     * @param SourceDeductionProcessor $subject
     * @param \Closure                 $proceed
     * @param EventObserver            $observer
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function aroundExecute(SourceDeductionProcessor $subject, \Closure $proceed, EventObserver $observer): void
    {
        /** @var \Magento\Sales\Model\Order\Shipment $shipment */
        $shipment = $observer->getEvent()->getShipment();
        if ($shipment->getOrigData('entity_id')) {
            return;
        }

        if ($shipment->getExtensionAttributes() !== null
            && $shipment->getExtensionAttributes()->getSourceCode() !== null) {
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
        }
    }
}