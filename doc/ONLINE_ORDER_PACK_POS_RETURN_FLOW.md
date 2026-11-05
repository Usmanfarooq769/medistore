# Online order, pack selection, POS sale and customer return flow

Apply this patch on top of Product Completion Patch 01. It adds customer pack/tablet selection, pharmacy order detail view, direct POS fulfilment, customer order tracking details and pack-aware returns.

## Customer order units

Inventory is always stored in individual base units (tablets, capsules, or other medicine units). Customer-facing prices are derived from the medicine's configured pack sale price:

- `sale_unit = pack`: customer quantity is packs; base stock quantity = packs x `pack_size`.
- `sale_unit = unit`: customer quantity is individual units; base stock quantity = units.
- `online_order_items.quantity` always stores base inventory units for reservation and fulfilment.
- `sale_quantity`, `sale_unit`, and `pack_size` preserve what the customer selected for display and receipt details.
- `unit_price` on `online_order_items` is the price per individual base unit.

## Pharmacy order processing

1. Open Stage 6 > Online Orders.
2. Click **Details** to inspect customer, address, each medicine, pack/unit selection, quantity and amount.
3. Confirm the order. The stock reservation is extended until fulfilment.
4. Click **Create POS sale**. The POS opens with the exact order lines preloaded and locked against accidental quantity, pack, discount and price edits.
5. Select the actual payment method and amount, then complete checkout. `PosController::checkout()` creates the `sales` row. The database assigns `sales.id`; the invoice is `INV-YYYYMMDD-` plus the padded sale ID. The same transaction links `online_orders.sale_id`, consumes the reservation and moves the order to `ready`.
6. Assign delivery from the order list. The customer can track order status, items, invoice number and delivery information using order number + phone.

Do not manually type or link a Sale ID during the normal workflow. The legacy manual-link endpoint remains only for compatibility; use direct POS fulfilment for new orders.

## Customer returns

The existing **Returns > New Return > With invoice** workflow handles online and counter sales alike:

1. Search/scan the POS invoice number.
2. Select the exact item and quantity. For pack sales, the return screen offers complete-pack or individual-unit quantities; the API still receives base units.
3. Select the returned condition:
   - `Good`: returned to the original batch only if it is not expired.
   - `Damaged`: recorded as a loss; not added to sellable stock.
   - `Expired`: recorded as a loss; not added to sellable stock.
4. Save the return. A return number (`RET-...`) is generated, the sale item's returned quantity and sale returned amount are updated, the inventory movement is recorded, and the refund is applied to customer due first where applicable.

The return is not a simple increment to the medicine's total stock: the batch quantity and stock movement ledger are updated together by `StockService`.

## Install

Back up database and files. Extract this ZIP over the project after Patch 01, then run:

```bash
php artisan migrate --force
php artisan optimize:clear
npm run build
```

Test in staging with: one pack, one tablet, mixed pack/tablet lines, last-stock reservation, POS fulfilment, cancelled order, good return, damaged return, expired return, partial return, and repeated return beyond the remaining quantity.

## Compatibility note

Existing online order rows are migrated as individual units because the older online ordering screen did not store a pack choice. Historical orders remain visible; newly placed orders store the selected pack/unit accurately. Existing pending orders created before stock reservations were enabled should be reviewed and re-created if they cannot be confirmed due to having no active reservation.
