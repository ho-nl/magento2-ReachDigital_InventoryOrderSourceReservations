# IOSReservationsCancellationApi

Adds the ability to cancel order lines and quantities on order lines.

```php
$om = \Magento\Framework\App\ObjectManager::getInstance();
$orderCancelPartial = $om->get(
  \ReachDigital\IOSReservationsCancellationApi\Api\OrderCancelPartialInterface::class
);
$itemToCancelFactory = $om->get(
  \ReachDigital\IOSReservationsCancellation\Model\Data\ItemToCancelFactory::class
);
$orderCancelPartial->execute(1234, [
  $itemToCancelFactory->create(1235, 1),
  $itemToCancelFactory->create(1236, 1),
]);
```

## Features

- Cancels individual quantities on order lines
- Cancels order lines
- Reverts reservations
- Updates cancelled prices on order lines and the order.
- Marks the order as cancelled when all items are cancelled.

## Future

- Currently, the implementation depends on ReachDigital_IOSReservationsApi, but
  it can be made so that it doesn't have a hard dependency on it.
- There is no AdminUi to execute the `OrderCancelPartialInterface::execute`
- There is no REST API to execute the `OrderCancelPartialInterface::execute`
