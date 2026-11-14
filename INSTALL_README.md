# MediStore – Professional upgrade (install steps)

1. Unzip this over your project (overwrite files).
2. Backend:
   ```
   php artisan migrate            # adds new columns + drug_interactions table (+ starter interaction list)
   php artisan config:clear
   ```
3. Frontend:
   ```
   npm install                    # adds qrcode, xlsx, jspdf, jspdf-autotable
   npm run build
   ```
4. Optional tests: `php artisan test`  (14 new tests + existing)
5. Scheduler (for FBR retry): make sure `php artisan schedule:run` runs every minute (cron).

Then in the app:
- Settings -> receipt paper, NTN/STRN, FBR POS ID/token/URL, safety-check switch.
- Users & Roles -> set Manager PIN + permissions for managers/pharmacists.
- Medicines -> choose drug class (narcotic/controlled are tracked in the register automatically).
- Customers -> fill allergies for the POS safety check.
- Quality & Compliance -> Drug Interactions (review the starter list) and Controlled Register.
