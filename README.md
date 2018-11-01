# Order Source Reservations

_Note: This is discussed earlier during the MSI Open Grooming meeting
(https://www.youtube.com/watch?v=fl8anqyN-1Q&index=2&list=PLrQ5FBCRsEbWKK6U_3Awe7X-nG7KY0WPW) but after the meeting
there were some questions how certain specifics were implemented._

### Backstory
We have a feature request from a customer to implement purchase orders and therefor it lead me to the idea that we
should have some form of source reservations. But while writing the specifications it came to my attention that we have
a feature-gap in the new MSI flow regarding shipments.

### Problem
We need to make an API-call to the actual source (warehouse) to actually request as shipment for certain products. But
to know what to actually communicate to the warehouse, the Source Selection Algorithm needs to have run. We can't run
that algorithm during the shipment creation because the product hasn't actually shipped and thus the source isn't
deducted at that point.


### Proposed solution
We split the Source Selection Algorithm and the Shipment creation into two steps, while making sure the source, stock
and salable qty remain consistent.

#### Flow

🔸 Already handled by Magento
🔹 Added by IOSR

New order
    - 🔸Create StockReservations ✅

Order Invoiced  
    - 🔹Cron to Revert StockReservations + 🔹Add SourceReservations ✅

Shipment Created
    - 🔹Revert SourceReservations instead of Stock + 🔸Deduct Source ✅

Order Cancelled
    - 🔸Revert StockReservations ✅

Credit Order when not shipped:
    - 🔹Revert Source Reservations if available. 🚼
    - 🔹Low Prio: Hide 'Return Qty to Source' because it isn't deducted yet.

Credit Order when shipped:
    - 🔸Increment Source ✅

#### ConfirmSourceReservationsForOrderInterface
The orders' stock reservation is nullified, source reservation is made.

#### GetSourceReservationsFromOrderInterface
Will retrieve already reserved source reservations.


#### Shipment step
The orders' source reservation is nullified, actual source deduction is made by the shipment.

### Implementation
One of the questions is that if we split the SSA from the shipment, when will we run the SSA? Because in the
manual-order-processing case it doesn't need to be run at all, it can happen exactly the same way it happens now, but
in the API-case it needs to be ran as soon as possible.

Proposal: Always run the default SSA (async) after an order has been placed and store the result in the source
reservation table.

The value of the result of the SSA can be used as prefilled values that can be used when the UI-part has ran. If the
result there is different, the current reservations will be reverted and a new reservation is made.


---

Flag on Source if qty is Strict or Loose;
Should the source selection algorithm wait for the actual product to be in stock.

---


VOORR1

0 OV
+15 PO
--+
15

VOORR2

5 OV
--+
5

STOCK

20
-2 O#1
--+
18

-----


VOORR1

0 OV
+15 PO
--+
15

VOORR2

0 OV
--+
0

STOCK
15
-2 O#1
--+
13


# Order Source Reservation Selector

To achieve a good reservation system we need to know when and whom gets priority in the selection of the sources. A new
interface will be created OrderSourceReservationPriorityAlgorithmInterface (or something like that). In the MVP this
will only have a single implementation OrderSourceReservationByDate.

Question: How often will this run? Because algorithm1 needs to run once per day, algorithm2 can run every 2 mins, etc.


# Interfaces

✅ OrderSelectionServiceInterface < ❓
✅ GetOrderSelectionSelectionAlgorithmListInterface < ❓
✅ GetDefaultOrderSelectionAlgorithmCodeInterface < ❓

❌ Data\InventoryRequestInterface < InventoryRequest
❌ Data\ItemRequestInterface < ItemRequest
✅ Data\SourceSelectionAlgorithmInterface < SourceSelectionAlgorithm
❌ Data\SourceSelectionItemInterface < SourceSelectionItem
❌ Data\SourceSelectionResultInterface

# Model Interfaces
✅ OrderSelectionInterface < ByDatePlacedAlgorithm



