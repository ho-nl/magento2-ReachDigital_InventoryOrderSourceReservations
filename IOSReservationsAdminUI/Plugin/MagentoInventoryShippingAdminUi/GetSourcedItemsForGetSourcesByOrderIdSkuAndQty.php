<?php

namespace ReachDigital\IOSReservationsAdminUI\Plugin\MagentoInventoryShippingAdminUi;

use Closure;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryApi\Api\SourceRepositoryInterface;
use Magento\InventoryShippingAdminUi\Ui\DataProvider\GetSourcesByOrderIdSkuAndQty;
use ReachDigital\ISReservationsApi\Api\EncodeMetaDataInterface;
use ReachDigital\ISReservationsApi\Api\GetReservationsByMetadataInterface;

class GetSourcedItemsForGetSourcesByOrderIdSkuAndQty
{
    /**
     * @var GetReservationsByMetadataInterface
     */
    private $getReservationsByMetadata;

    /**
     * @var SourceRepositoryInterface
     */
    private $sourceRepository;

    /**
     * @var string[]
     */
    private $sources;
    /**
     * @var EncodeMetaDataInterface
     */
    private $encodeMetaData;

    public function __construct(
        GetReservationsByMetadataInterface $getReservationsByMetadata,
        EncodeMetaDataInterface $encodeMetaData,
        SourceRepositoryInterface $sourceRepository
    ) {
        $this->getReservationsByMetadata = $getReservationsByMetadata;
        $this->sourceRepository = $sourceRepository;
        $this->encodeMetaData = $encodeMetaData;
    }

    /**
     * We're not calling the original method because we don't want to allow for any SSA to run at this point.
     *
     * @param GetSourcesByOrderIdSkuAndQty $subject
     * @param Closure $proceed
     * @param int $orderId
     * @param string $sku
     * @param float $qty
     * @return array
     * @throws NoSuchEntityException
     */
    public function aroundExecute(
        GetSourcesByOrderIdSkuAndQty $subject,
        Closure $proceed,
        int $orderId,
        string $sku,
        float $qty
    ) {
        $reservations = $this->getReservationsByMetadata->execute(
            $this->encodeMetaData->execute(['order' => $orderId])
        );

        $result = [];

        foreach ($reservations as $sourceReservation) {
            if ($sourceReservation->getSku() === $sku) {
                $sourceCode = $sourceReservation->getSourceCode();

                $qty += $sourceReservation->getQuantity();

                if (!isset($result[$sourceCode])) {
                    $result[$sourceCode] = [
                        'sourceName' => $this->getSourceName($sourceCode),
                        'sourceCode' => $sourceCode,
                        'qtyAvailable' => $sourceReservation->getQuantity() * -1,
                        'qtyToDeduct' => $sourceReservation->getQuantity() * -1,
                    ];
                } else {
                    $result[$sourceCode]['qtyAvailable'] += $sourceReservation->getQuantity() * -1;
                    $result[$sourceCode]['qtyToDeduct'] += $sourceReservation->getQuantity() * -1;
                }
            }
        }

        foreach ($result as $key => $item) {
            if ($this->isLessThanAlmostZero($item['qtyToDeduct'])) {
                unset($result[$key]);
            }
        }

        return array_values($result);
    }

    /**
     * Get source name by code
     *
     * @param string $sourceCode
     * @return mixed
     * @throws NoSuchEntityException
     */
    private function getSourceName(string $sourceCode): string
    {
        if (!isset($this->sources[$sourceCode])) {
            $this->sources[$sourceCode] = $this->sourceRepository->get($sourceCode)->getName();
        }

        return $this->sources[$sourceCode];
    }

    /**
     * Compare float number with some epsilon
     *
     * @param float $floatNumber
     *
     * @return bool
     */
    private function isLessThanAlmostZero(float $floatNumber): bool
    {
        return $floatNumber < 0.0000001;
    }
}
