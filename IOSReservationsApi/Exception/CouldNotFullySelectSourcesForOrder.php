<?php
declare(strict_types=1);
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\IOSReservationsApi\Exception;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

class CouldNotFullySelectSourcesForOrder extends LocalizedException
{
    public static function create(int $orderId)
    {
        return new self(__('Could not fully select all sources for order %s', $orderId));
    }
}
