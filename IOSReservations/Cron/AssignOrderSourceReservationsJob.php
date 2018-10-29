<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace ReachDigital\IOSReservations\Cron;

use ReachDigital\IOSReservations\Model\AssignOrderSourceReservationsRunner;

class AssignOrderSourceReservationsJob
{

    /**
     * @var AssignOrderSourceReservationsRunner
     */
    private $assignOrderSourceReservationsRunner;

    public function __construct(
        AssignOrderSourceReservationsRunner $assignOrderSourceReservationsRunner
    )
    {
        $this->assignOrderSourceReservationsRunner = $assignOrderSourceReservationsRunner;
    }

    public function execute(): void
    {
        $this->assignOrderSourceReservationsRunner->execute();
    }
}