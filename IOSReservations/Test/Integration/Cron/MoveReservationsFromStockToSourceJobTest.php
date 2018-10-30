<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSourceRunner;


class MoveReservationsFromStockToSourceJobTest extends TestCase
{

    /** @var MoveReservationsFromStockToSourceRunner */
    private $moveReservationsFromStockToSourceFromStockToSourceRunner;

    protected function setUp()
    {
        $this->moveReservationsFromStockToSourceFromStockToSourceRunner = Bootstrap::getObjectManager()->get(MoveReservationsFromStockToSourceRunner::class);
    }

    /**
     * @test
     * @covers \ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSourceRunner
     * @covers \ReachDigital\IOSReservations\Cron\MoveReservationsFromStockToSourceJob
     *
     * - Test that assignment is done
     * - Test exception thrown when order is already assigned
     *
     */
    public function should_be_assigned_after_cron_invocation(): void
    {
        // Create order
        // Check assigned sources
        // Invoke cronjob
        // Check assigned sources

    }
}
