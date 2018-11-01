<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

class RevertSourceReservationsOnCreditBeforeShipmentTest extends \PHPUnit\Framework\TestCase
{
    public function setUp()
    {
    }

    /**
     *
     * @test
     *
     * @covers \ReachDigital\IOSReservations\Plugin\MagentoInventorySales\RevertSourceReservationsOnCreditBeforeShipment
     *
     */
    public function should_revert_source_reservations_on_credit_before_shipping_if_available(): void
    {
        // Have an invoiced order
        // Assign order to sources
        // Credit order
        // Assert that source reservations have been nullified
    }
}