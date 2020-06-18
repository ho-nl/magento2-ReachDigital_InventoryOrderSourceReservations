<?php
declare(strict_types=1);
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\IOSReservationsPriority;

use ReachDigital\IOSReservationsPriorityApi\Api\Data\OrderSelectionAlgorithmInterface;

class OrderSelectionAlgorithm implements OrderSelectionAlgorithmInterface
{
    /**
     * @var string
     */
    private $code;

    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $description;

    /**
     * @param string $code
     * @param string $title
     * @param string $description
     */
    public function __construct(string $code, string $title, string $description)
    {
        $this->code = $code;
        $this->title = $title;
        $this->description = $description;
    }

    /**
     * @inheritdoc
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @inheritdoc
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return $this->description;
    }
}
