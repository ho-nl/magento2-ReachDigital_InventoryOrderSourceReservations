# Order Source Reservations

### Backstory
We had a requirement to make a connection with a fulfillment warehouse, we wanted to be future-proof and want to intergrate it with the new Multi Source Inventory functionality introduced in Magento 2.3 If you don't fully understand MSI yet, please read our blogpost [The Definitive Guide to Magento MSI Multi Source Inventory](https://www.reachdigital.nl/en/blog/magento-msi-multi-source-inventory-features)).

While writing specifications for the integration with the warehouse it came to our attention that MSI has a feature-gap regarding the creation of shipments:

We need to make an API-call to the actual source (warehouse) to actually request a shipment for the ordered products. But
to know what to actually communicate to the warehouse, the Source Selection Algorithm needs to have run. We can't run
that algorithm during the shipment creation because the product hasn't actually shipped and thus the source isn't
deducted at that point.

### Proposed solution

To implement a warehouse connector based on MSI, we need to know to what warehouse to send which products. The warehouse selection is done in MSI with the Souce Selection Algorithm. The SSA is triggered via the UI, right before creating the shipment.

The feature-gap here is that we need the result of the SSA, because we need to send an API call at some point to the warehouse... After we've send the API calls to the warehouse we cant have the result of the SSA be changed.

1. If the SSA has been ran a single time we need to store the result, it can't change.
2. If an item has a source selected we have a moment to send of an API call to the warehouse.

To store the result of the SSA we make an [Inventory Source Reservations](https://github.com/ho-nl/magento2-ReachDigital_InventorySourceReservations).

#### Flow

- 🔸 Already handled by Magento
- 🔹 Added by this package

Order created:

- 🔸Create StockReservations ✅

Invoice created:

- 🔹Cron to 🔹Add SourceReservations + 🔹Revert StockReservations ✅

Shipment Created

- 🔹Revert SourceReservations instead of Stock + 🔸Deduct Source ✅

Order Cancelled

- 🔸Revert StockReservations ✅

Credit Order when not shipped:

- 🔹Revert Source Reservations by refunded qty, if reservation exists. ✅
- 🔹Low Prio: Hide 'Return Qty to Source' because it isn't deducted yet.

Credit Order when shipped:

- 🔸Increment Source ✅


### Step by step

1. Order created: When a new order is created in Magento MSI will make a reservation on the Stock🔸, this will be exactly the same.

    - Order Cancelled: The Stock reservation is nullified and the qty is released to be sold again.

2. Create invoice: 🔹Cron [MoveReservationsFromStockToSourceRunner](https://github.com/ho-nl/magento2-ReachDigital_InventoryOrderSourceReservations/blob/master/IOSReservations/Model/MoveReservationsFromStockToSourceRunner.php#L65-L78): Periodically we run the 'heavy' SSA on all orders that are succesfully invoiced (and therefor authorized to be shipped).

    - 🔹[OrderSelectionService](https://github.com/ho-nl/magento2-ReachDigital_InventoryOrderSourceReservations/blob/master/IOSReservationsPriorityApi/Api/OrderSelectionServiceInterface.php) will return a list of unsourced orders based on a certain algorithm (only byDateCreated implemented right now).
    - 🔹[Selection criteria](https://github.com/ho-nl/magento2-ReachDigital_InventoryOrderSourceReservations/blob/master/IOSReservationsPriority/Model/Algorithms/ByDateCreatedAlgorithm.php#L63-L65) All state:processing and unsourced orders.
    - 🔹[MoveReservationsFromStockToSource](https://github.com/ho-nl/magento2-ReachDigital_InventoryOrderSourceReservations/blob/master/IOSReservations/Model/MoveReservationsFromStockToSource.php) Will actually move the reservations from the Stock to the Source.
    - To integrate with a warehouse it becomes trivial to find a point where to hook into, to actually send the reservations to the actual warehouse: 🔹afterExecute on MoveReservationsFromStockToSource.
 
3. Create shipment
    - 🔹 [DeductSourceAndNullifyReservationOnShipment](https://github.com/ho-nl/magento2-ReachDigital_InventoryOrderSourceReservations/blob/master/IOSReservations/Plugin/MagentoInventoryShipping/DeductSourceAndNullifyReservationOnShipment.php#L103-L130): Instead of nullifying MSI's Stock Reservation we now nullify the Source Reservation. The Stock reservation already happened in step two.
    - The SSA will always return the earlier created reservations: 🔹 [PriorityBasedAlgorithmWithSourceReservations](https://github.com/ho-nl/magento2-ReachDigital_InventoryOrderSourceReservations/blob/master/IOSReservations/Plugin/InventorySourceSelection/PriorityBasedAlgorithmWithSourceReservations.php#L101-L103)

4. Create creditmemo:
   - 🔹 [RevertSourceReservationsOnCreditBeforeShipment](https://github.com/ho-nl/magento2-ReachDigital_InventoryOrderSourceReservations/blob/master/IOSReservations/Plugin/MagentoInventorySales/RevertSourceReservationsOnCreditBeforeShipment.php) will automatically reverty any source reservations.
   
   
