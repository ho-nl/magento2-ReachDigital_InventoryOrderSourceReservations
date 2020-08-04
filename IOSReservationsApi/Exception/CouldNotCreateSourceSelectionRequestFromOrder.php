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

class CouldNotCreateSourceSelectionRequestFromOrder extends LocalizedException
{
    protected function __construct(Phrase $phrase, Exception $cause = null, $code = 0)
    {
        parent::__construct($phrase, $cause, $code);
    }

    public static function create(int $orderId)
    {
        return new self(__('No source items available to assign to order %s', $orderId));
    }
}
