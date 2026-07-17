<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace ReachDigital\IOSReservationsAdminUI\Plugin\MagentoInventoryShippingAdminUi;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryApi\Api\SourceRepositoryInterface;
use Magento\InventoryShippingAdminUi\Model\ResourceModel\GetAllocatedSourcesForOrder;
use ReachDigital\IOSReservationsApi\Api\GetOrderSourceReservationsInterface;

/**
 * Fills the order grid's "Allocated sources" column from source reservations.
 *
 * Core only derives allocated sources from shipments
 * (inventory_shipment_source), so the column stays empty until an order
 * ships. With order source reservations the allocation is known much
 * earlier — right after invoicing, when the SSA moves the stock reservation
 * to sources. When core finds no shipped sources yet, this fills the column
 * from the order's reservations; once shipments exist, core's answer wins.
 */
class AddReservedSourcesToAllocatedSources
{
    /** @var GetOrderSourceReservationsInterface */
    private $getOrderSourceReservations;

    /** @var SourceRepositoryInterface */
    private $sourceRepository;

    /** @var array<string, string>  source code => display name */
    private $sourceNames = [];

    public function __construct(
        GetOrderSourceReservationsInterface $getOrderSourceReservations,
        SourceRepositoryInterface $sourceRepository
    ) {
        $this->getOrderSourceReservations = $getOrderSourceReservations;
        $this->sourceRepository = $sourceRepository;
    }

    /**
     * @param string[] $result
     * @return string[]
     */
    public function afterExecute(GetAllocatedSourcesForOrder $subject, array $result, int $orderId): array
    {
        if ($result !== []) {
            return $result;
        }

        $reservationResult = $this->getOrderSourceReservations->execute($orderId);
        if ($reservationResult === null) {
            return $result;
        }

        $codes = [];
        foreach ($reservationResult->getReservationItems() as $item) {
            $codes[$item->getReservation()->getSourceCode()] = true;
        }

        return array_values(array_map([$this, 'getSourceName'], array_keys($codes)));
    }

    private function getSourceName(string $sourceCode): string
    {
        if (!isset($this->sourceNames[$sourceCode])) {
            try {
                $this->sourceNames[$sourceCode] = (string) $this->sourceRepository->get($sourceCode)->getName();
            } catch (NoSuchEntityException $e) {
                $this->sourceNames[$sourceCode] = $sourceCode;
            }
        }

        return $this->sourceNames[$sourceCode];
    }
}
