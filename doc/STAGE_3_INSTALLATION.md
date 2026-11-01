# MediStore Stage 3 – Customer Growth & Replenishment

This patch is built against the latest uploaded MediStore project. It does not replace or modify `.env`, `.env.example`, WhatsApp credentials, mail configuration, or storage configuration.

## Included

1. **Customer loyalty wallet** – earns points from collected payments, supports redemption in POS, records a customer points ledger, and proportionally reverses/restores points when an invoice is returned. Voiding a sale restores redeemed points.
2. **Customer credit limits** – optional per-customer limit. A credit sale is rejected when the new outstanding balance would exceed the configured limit. Blank means unlimited; zero means no credit is allowed.
3. **Promotions and coupon codes** – percentage or fixed discounts, minimum subtotal, maximum discount, start/end date, total usage limit, and per-customer usage limit. The server validates each coupon again inside the checkout transaction.
4. **Smart reorder suggestions** – uses recent branch sales, average daily demand, lead time, target stock coverage, reorder level, non-expired stock, and pending/approved purchase-order quantities.
5. **Customer receivables ageing** – groups active-branch outstanding invoices into current, 1–30, 31–60, 61–90, and over-90-day buckets.
6. **Customer account activity** – customer profile now includes credit availability, loyalty points, payment history, and loyalty ledger.

## Installation

Back up the database and project first. Copy the ZIP contents into the project root, allowing the listed files to merge/replace. Do not replace any `.env` file.

Run from the Laravel project root:

```bash
php artisan migrate
php artisan optimize:clear
npm run build
```

No new Composer or npm packages are required.

## Initial settings

Open **Settings** and configure:

- **Loyalty points per Rs 100 paid**: default `1`. Set to `0` to disable earning.
- **Value of 1 loyalty point**: default `1` (one point gives one currency unit of discount).

Points are awarded against payments actually collected, including later payments against credit invoices. Points are reversed/restored proportionally when invoice returns are processed.

## Using Stage 3

### Customer credit limits

Open **Customers**, edit a customer, and set a credit limit. Leave it blank for unlimited credit. Only administrators can change this field. The POS validates the credit limit again on the server at checkout.

### Promotions

Open **Promotions & Coupons**, create a promotion and set its active dates and usage limits. In POS, select the customer if the coupon has a per-customer limit, enter the code, and press **Apply**. Coupon discount is calculated by the server; the preview is only for display.

### Smart reorder

Open **Smart Reorder**. Adjust history days, supplier lead time, and target stock coverage. The recommended quantity subtracts stock already expected on pending-approval or approved purchase orders. It is a recommendation, not an automatic purchase.

### Receivables ageing

Open **Customer Ageing** to review due invoices by age for the selected branch.

## Notes

- Promotions are shared across branches; redemption/usage limits are global.
- Customer balance and credit limit are customer-wide, while ageing and invoice statements are filtered to the active branch.
- Reorder suggestions use sale-item quantities in base stock units and convert open purchase-order pack quantities into base units.
- The reorder model is a simple demand-based estimate. Check supplier lead times, seasonal demand, and expiry risk before approving a purchase.
- The patch intentionally does not include or alter any environment file or external-service credentials.
