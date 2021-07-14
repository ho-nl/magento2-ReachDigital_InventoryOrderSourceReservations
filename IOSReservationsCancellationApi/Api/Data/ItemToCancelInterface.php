<?php

namespace ReachDigital\IOSReservationsCancellationApi\Api\Data;

interface ItemToCancelInterface
{
    public const ITEM_ID = 'item_id';
    public const QUANTITY = 'quantity';

    public function getItemId(): int;
    public function setItemId(int $id): void;

    public function getQuantity(): float;
    public function setQuantity(float $quantity): void;
}
