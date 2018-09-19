# Inventory Order Source Reservations Priority Api

Responsibility:
Provides a list of orders that need to be sourced, but allows us to define an algorithm which provides a list of orders
and its sort order that shall be sourced by the IOSReservations module.

Modules:
- Inventory Order Source Reservations Priority Api
- Inventory Order Source Reservations Priority
- Inventory Order Source Reservations Priority Admin UI

Implementations:
Currently there is only one Algorithm implemented:
`\ReachDigital\IOSReservationsPriority\Model\Algorithms\ByDateCreatedAlgorithm` which will provide a list of unsourced
orders based on their date placed.
