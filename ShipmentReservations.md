# Shipment Source Reservations

This solution wont be implemented.

### Source Reservations for Shipments

By creating such a system we can solve an additional important problem: A Source
is deferred to the shipment creation, but we need a moment in between the
selection of a Source and the actual creation of a shipment. To actually create
a shipment we need to communicate the 'items to be shipped' to the actual
warehouse via an API or other process.

In our opinion there are two solutions to this;

- Create 'Proforma Shipments' that is a shipment that isn't actually shipped but
  is used as a fulfillment promise by the warehouse.
- Create 'Shipment Request' entity that is a newly saved entity that does the
  same, but we have a separate entity.

Advantage of the 'Proforma Shipment' is that we only need to introduce a 'state'
for the shipments in the system. Advantage of the 'Shipment Request' is that the
current shipment flow doesn't need to be updated?

The creation of these new 'promises' will be handled by the Source Selection
algorithm.

We can discrimate the following states a shipment can be in:

**Requested** The orders' stock reservation is nullified, source reservation is
made by the shipment. The source selection algorithm has ran and the sources are
selected. This can be used as a starting point to communicate the shipment to
the source warehouse.

**Shipped** The shipments' source reservation is nullified, actual source
deduction is made by the shipment. When the actual package leaves the warehouse
the warehouse confirms the shipment back to Magento and confirms the shipment.

**Cancelled** The shipments' source revervation is nullified and the orders'
stock reservation is re-made. (up for discussion)

#### Discussion

Should we also implement a process-step-status for the shipment to have a more
fine-grained flow how the shipment moves through the warehouse. Most warehouses
have more detailed information availble, which is interesting for the Merchant,
but isn't interesting from a source deduction standpoint.
