<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservations\Plugin\MagentoInventoryShipping;

use Closure;
use Exception;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Validation\ValidationException;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventoryCatalogApi\Model\IsSingleSourceModeInterface;
use Magento\InventoryShipping\Model\GetItemsToDeductFromShipment;
use Magento\InventoryShipping\Model\SourceDeductionRequestFromShipmentFactory;
use Magento\InventoryShipping\Observer\SourceDeductionProcessor;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionRequestInterface;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionServiceInterface;
use Magento\Sales\Model\Order\Shipment;
use Magento\Framework\Event\Observer as EventObserver;
use ReachDigital\ISReservationsApi\Api\EncodeMetaDataInterface;
use ReachDigital\ISReservationsApi\Model\AppendSourceReservationsInterface;
use ReachDigital\ISReservationsApi\Model\SourceReservationBuilderInterface;

class DeductSourceAndNullifyReservationOnShipment
{
    /**
     * @var AppendSourceReservationsInterface
     */
    private $appendReservations;

    /**
     * @var SourceReservationBuilderInterface
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
     * @var EncodeMetaDataInterface
     */
    private $encodeMetaData;

    public function __construct(
        IsSingleSourceModeInterface $isSingleSourceMode,
        DefaultSourceProviderInterface $defaultSourceProvider,
        AppendSourceReservationsInterface $appendReservations,
        SourceReservationBuilderInterface $reservationBuilder,
        GetItemsToDeductFromShipment $getItemsToDeductFromShipment,
        SourceDeductionRequestFromShipmentFactory $sourceDeductionRequestFromShipmentFactory,
        SourceDeductionServiceInterface $sourceDeductionService,
        EncodeMetaDataInterface $encodeMetaData
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
     * @param Closure $proceed
     * @param EventObserver $observer
     *
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws ValidationException
     * @throws Exception
     */
    public function aroundExecute(SourceDeductionProcessor $subject, Closure $proceed, EventObserver $observer): void
    {
        /** @var Shipment $shipment */
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

        if (!$sourceCode) {
            throw new Exception('Source code not set');
        }

        $shipmentItems = $this->getItemsToDeductFromShipment->execute($shipment);

        if (!empty($shipmentItems)) {
            $sourceDeductionRequest = $this->sourceDeductionRequestFromShipmentFactory->execute(
                $shipment,
                $sourceCode,
                $shipmentItems
            );
            $this->sourceDeductionService->execute($sourceDeductionRequest);
            $this->placeCompensatingSourceReservation($sourceDeductionRequest, $shipment);
        }
    }

    /**
     * @param SourceDeductionRequestInterface $sourceDeductionRequest
     *
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws ValidationException
     */
    private function placeCompensatingSourceReservation(
        SourceDeductionRequestInterface $sourceDeductionRequest,
        Shipment $shipment
    ): void {
        $reservations = [];

        $metaData = $this->encodeMetaData->execute([
            'order' => $shipment->getOrderId(),
            'shipment' => $shipment->getId(),
        ]);

        foreach ($sourceDeductionRequest->getItems() as $item) {
            $this->reservationBuilder->setQuantity($item->getQty());
            $this->reservationBuilder->setSku($item->getSku());
            $this->reservationBuilder->setSourceCode($sourceDeductionRequest->getSourceCode());
            $this->reservationBuilder->setMetadata($metaData);
            $reservations[] = $this->reservationBuilder->build();
        }

        $this->appendReservations->execute($reservations);
    }
}
