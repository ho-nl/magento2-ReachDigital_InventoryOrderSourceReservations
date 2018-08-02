<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\IOSReservationsApi\Exception;

use Magento\Framework\Exception\LocalizedException;

class SourceReservationForOrderInputException extends LocalizedException
{
    public const ERROR_CODE_INVALID_ITEM_COUNT = 1;
    public const MSG_INVALID_ITEM_COUNT = '%1 items requested for sku %1 should be %1';
}
