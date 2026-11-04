# Online storefront and stock reservation upgrade

This patch adds the customer-facing `/shop` storefront and batch-level stock reservations for online orders.

## Files changed

- `database/migrations/2026_11_07_000001_create_online_order_reservations.php`
- `app/Services/OnlineOrderInventoryService.php`
- `app/Services/StockService.php`
- `app/Http/Requests/CheckoutRequest.php`
- `app/Http/Controllers/Api/StageSixController.php`
- `app/Http/Controllers/Api/CustomerPortalController.php`
- `app/Http/Controllers/Api/PosController.php`
- `app/Console/Commands/ExpireOnlineOrderReservations.php`
- `routes/api.php`
- `routes/console.php`
- `resources/js/pages/Storefront.vue`
- `resources/js/router/index.js`

## Behaviour

1. Public storefront: `/shop`.
2. Public API: `/api/storefront/branches`, `/api/storefront/catalog`, `/api/storefront/orders`, `/api/storefront/track-order`.
3. Prescription-only and controlled medicines are excluded from public ordering.
4. Each submitted online order reserves real batch quantities using row locks and FEFO order. Reservations do not change physical stock or stock ledger quantities.
5. Pending reservations expire after 30 minutes. Confirmed reservations are held until fulfilment/cancellation.
6. Ordinary POS sales cannot consume stock reserved by active online orders.
7. To fulfil a confirmed online order, submit the exact cart to `POST /api/pos/checkout` with `online_order_id`. The POS transaction consumes that order's reserved batches and automatically links the sale and moves the order to `ready`.
8. The older manual `link-sale` endpoint remains for compatibility and now requires exact medicine/quantity matching. New integrations should use `online_order_id` during checkout.
9. Expired holds are released by `store:expire-order-reservations`, scheduled every minute.

## Install

Back up the database and files first. Then copy the patch files into the matching project paths and run:

```bash
php artisan migrate --force
php artisan optimize:clear
npm install
npm run build
php artisan store:expire-order-reservations
```

Ensure the Laravel scheduler is running every minute:

```cron
* * * * * cd /path/to/medistore && php artisan schedule:run >> /dev/null 2>&1
```

The queue worker should also be supervised in production for webhooks and notifications.

## POS API example for fulfilling an online order

Include `online_order_id` in the existing POS checkout payload, and send the exact medicine quantities in base units. For pack sales, the backend converts the pack quantity using the medicine's configured `pack_size` before matching the order.

```json
{
  "online_order_id": 123,
  "items": [
    { "id": 45, "quantity": 2, "sale_unit": "unit" }
  ],
  "paid": 0,
  "payment_method": "credit"
}
```

Use a valid customer selection/payment method according to the pharmacy's checkout policy. The customer/order data is applied from the confirmed online order. Do not use the example IDs in production.

## Important deployment notes

- This patch is a focused online ordering and stock-integrity upgrade, not a certification that the whole application is production-ready.
- Run migrations and frontend builds in staging first; exercise two simultaneous orders for the last units of a batch, online cancellation, reservation expiry, POS counter sale during a reservation, and POS fulfilment of a confirmed order.
- This patch does not yet implement company-level (`company_id`) tenant isolation. The current app has branch-level scoping, which is not equivalent to multi-company SaaS isolation. Do not host unrelated pharmacy companies in one database until that separate tenant-boundary work is completed and tested.
- Payment gateway capture/refund is not implemented by this patch. The storefront submits an order request; the pharmacy arranges payment and fulfils it through the existing POS.
- The source archive included `.env` and `.git/`. Neither is included in this patch. Remove `.env` and `.git/` from any distributable source archive, and rotate any credentials that may have been shared outside your trusted environment.
