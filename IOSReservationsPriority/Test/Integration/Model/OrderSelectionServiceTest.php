<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace ReachDigital\IOSReservationsPriority\Test\Integration\Model;

use Magento\Framework\App\ObjectManager;
use ReachDigital\IOSReservationsPriorityApi\Api\OrderSelectionServiceInterface;

class OrderSelectionServiceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @test
     */
    public function should_retrieve_unsourced_orders_by_date(): void
    {
        /** @var OrderSelectionServiceInterface $orderSelectionService */
        $orderSelectionService = ObjectManager::getInstance()->get(OrderSelectionServiceInterface::class);
        $orderSelectionService->execute(null, 'byDateCreated');
        //@todo
    }

    public function should_skip_sourced_orders(): void
    {

    }
}
