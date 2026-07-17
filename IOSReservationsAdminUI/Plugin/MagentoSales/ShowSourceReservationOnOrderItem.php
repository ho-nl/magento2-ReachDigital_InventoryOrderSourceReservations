<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace ReachDigital\IOSReservationsAdminUI\Plugin\MagentoSales;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryApi\Api\SourceRepositoryInterface;
use Magento\Sales\Model\Order\Item;
use ReachDigital\IOSReservationsApi\Api\GetOrderSourceReservationsInterface;

/**
 * Shows the assigned source per order line in the admin order view, as an
 * extra `additional_options` row rendered by the default items renderer:
 *
 *   Source: DC magazijn 999: reserved
 *
 * Reservations attach to the shippable line (the configurable parent or a
 * standalone simple); child lines resolve through their parent so the row
 * appears regardless of which line the renderer walks. Lines without a
 * reservation (order not yet invoiced, or the SSA has not run yet) show no
 * row.
 *
 * Admin-area only: registered through etc/adminhtml/di.xml, with a state
 * check as belt-and-braces so order emails never grow this row.
 */
class ShowSourceReservationOnOrderItem
{
    /** @var array<int, array<int, string>>  order id => [order item id => source code] */
    private $reservationsByOrder = [];

    /** @var array<string, string>  source code => display name */
    private $sourceNames = [];

    /** @var GetOrderSourceReservationsInterface */
    private $getOrderSourceReservations;

    /** @var SourceRepositoryInterface */
    private $sourceRepository;

    /** @var State */
    private $appState;

    public function __construct(
        GetOrderSourceReservationsInterface $getOrderSourceReservations,
        SourceRepositoryInterface $sourceRepository,
        State $appState
    ) {
        $this->getOrderSourceReservations = $getOrderSourceReservations;
        $this->sourceRepository = $sourceRepository;
        $this->appState = $appState;
    }

    /**
     * @param Item $subject
     * @param array|mixed $result
     * @return array|mixed
     */
    public function afterGetProductOptions(Item $subject, $result)
    {
        if (!is_array($result) || !$this->isAdminArea() || !$subject->getOrderId()) {
            return $result;
        }

        $sourceCode = $this->getReservedSourceCode($subject);
        if ($sourceCode === null) {
            return $result;
        }

        $additional = $result['additional_options'] ?? [];
        if (!is_array($additional)) {
            $additional = [];
        }

        // getProductOptions is called several times per rendered line — only
        // append when our row is not there yet.
        foreach ($additional as $option) {
            if (($option['label'] ?? null) === (string) __('Source')) {
                return $result;
            }
        }

        $additional[] = [
            'label' => (string) __('Source'),
            'value' => sprintf('%s: %s', $this->getSourceName($sourceCode), (string) __('reserved')),
        ];
        $result['additional_options'] = $additional;

        return $result;
    }

    private function getReservedSourceCode(Item $item): ?string
    {
        $orderId = (int) $item->getOrderId();

        if (!array_key_exists($orderId, $this->reservationsByOrder)) {
            $byItemId = [];
            $reservationResult = $this->getOrderSourceReservations->execute($orderId);
            if ($reservationResult !== null) {
                foreach ($reservationResult->getReservationItems() as $resultItem) {
                    $byItemId[$resultItem->getOrderItemId()] = $resultItem->getReservation()->getSourceCode();
                }
            }
            $this->reservationsByOrder[$orderId] = $byItemId;
        }

        $byItemId = $this->reservationsByOrder[$orderId];

        return $byItemId[(int) $item->getItemId()] ?? ($byItemId[(int) $item->getParentItemId()] ?? null);
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

    private function isAdminArea(): bool
    {
        try {
            return $this->appState->getAreaCode() === Area::AREA_ADMINHTML;
        } catch (LocalizedException $e) {
            return false;
        }
    }
}
