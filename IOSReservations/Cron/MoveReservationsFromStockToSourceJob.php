<?php
declare(strict_types=1);
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace ReachDigital\IOSReservations\Cron;

use ReachDigital\IOSReservations\Model\MoveReservationsFromStockToSourceRunner;

class MoveReservationsFromStockToSourceJob
{
    /**
     * @var MoveReservationsFromStockToSourceRunner
     */
    private $moveReservationsFromStockToSourceRunner;

    public function __construct(MoveReservationsFromStockToSourceRunner $moveReservationsFromStockToSourceRunner)
    {
        $this->moveReservationsFromStockToSourceRunner = $moveReservationsFromStockToSourceRunner;
    }

    public function execute(): void
    {
        $this->moveReservationsFromStockToSourceRunner->execute();
    }
}
