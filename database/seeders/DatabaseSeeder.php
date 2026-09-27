<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Manufacturer;
use App\Models\Medicine;
use App\Models\Purchase;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@medistore.test'], ['name' => 'Admin', 'password' => 'password', 'role' => 'admin', 'is_active' => true]);
        User::updateOrCreate(['email' => 'cashier@medistore.test'], ['name' => 'Cashier', 'password' => 'password', 'role' => 'cashier', 'is_active' => true]);

        Setting::put([
            'store_name' => 'MediStore Pharmacy', 'store_address' => 'Main Bazar, Your City', 'store_phone' => '0300-0000000',
            'license_no' => 'DL-12345', 'currency' => 'Rs.', 'default_tax' => '0', 'max_discount_percent' => '10',
            'expiry_alert_days' => '60', 'return_days' => '7', 'cashier_price_edit' => '0',
            'receipt_footer' => 'Get well soon! Medicines are returnable within 7 days with this receipt.',
        ]);

        $cats = collect(['Tablet', 'Capsule', 'Syrup', 'Injection', 'Ointment', 'Drops', 'Surgical'])
            ->mapWithKeys(fn ($n) => [$n => Category::firstOrCreate(['name' => $n])->id]);
        $mfr = collect(['GSK', 'Abbott', 'Getz Pharma', 'Searle', 'Sami Pharma'])
            ->mapWithKeys(fn ($n) => [$n => Manufacturer::firstOrCreate(['name' => $n])->id]);

        $sup1 = Supplier::firstOrCreate(['name' => 'HealthCare Distributors'], ['contact_person' => 'Imran', 'phone' => '0300-1234567', 'address' => 'Medicine Market']);
        $sup2 = Supplier::firstOrCreate(['name' => 'City Pharma Traders'], ['contact_person' => 'Bilal', 'phone' => '0321-7654321', 'address' => 'Industrial Area']);
        Customer::firstOrCreate(['phone' => '03001112233'], ['name' => 'Ali Khan', 'address' => 'Street 5']);

        // name, strength, generic, category, company, unit, cost, price, reorder, qty, expiry days, supplier
        $items = [
            ['Panadol', '500mg', 'Paracetamol', 'Tablet', 'GSK', 'strip', 25, 35, 20, 150, 400, $sup1],
            ['Brufen', '400mg', 'Ibuprofen', 'Tablet', 'Abbott', 'strip', 40, 55, 15, 8, 300, $sup1],
            ['Augmentin', '625mg', 'Amoxicillin + Clavulanate', 'Tablet', 'GSK', 'strip', 280, 350, 10, 40, 200, $sup2],
            ['Risek', '20mg', 'Omeprazole', 'Capsule', 'Getz Pharma', 'strip', 70, 95, 15, 60, 45, $sup1],
            ['Hydryllin', '120ml', 'Diphenhydramine', 'Syrup', 'Searle', 'bottle', 90, 120, 10, 6, 150, $sup2],
            ['Polyfax', '20g', 'Polymyxin B + Bacitracin', 'Ointment', 'GSK', 'tube', 110, 150, 5, 3, 500, $sup2],
            ['Softin', '10mg', 'Loratadine', 'Tablet', 'Sami Pharma', 'strip', 60, 85, 10, 45, 365, $sup1],
            ['Ceftriaxone', '1g', 'Ceftriaxone', 'Injection', 'Getz Pharma', 'vial', 150, 210, 8, 25, 280, $sup1],
        ];

        $stock = app(StockService::class);

        DB::transaction(function () use ($items, $cats, $mfr, $stock) {
            $bySupplier = [];
            foreach ($items as $i => [$name, $strength, $generic, $cat, $company, $unit, $cost, $price, $reorder, $qty, $days, $sup]) {
                $m = Medicine::firstOrCreate(['name' => $name, 'strength' => $strength], [
                    'generic_name' => $generic, 'category_id' => $cats[$cat], 'manufacturer_id' => $mfr[$company], 'unit' => $unit,
                    'barcode' => '8901000' . str_pad($i + 1, 6, '0', STR_PAD_LEFT), 'purchase_price' => $cost, 'sale_price' => $price,
                    'reorder_level' => $reorder, 'rack' => 'R-' . (($i % 4) + 1), 'is_active' => true,
                ]);
                $bySupplier[$sup->id][] = [$m, $qty, $days, $cost, $price];
            }

            // opening stock via real purchases => batches, ledger, supplier balance all correct
            foreach ($bySupplier as $supplierId => $rows) {
                if (Purchase::where('supplier_id', $supplierId)->exists()) {
                    continue;
                }
                $total = collect($rows)->sum(fn ($r) => $r[1] * $r[3]);
                $p = Purchase::create([
                    'supplier_id' => $supplierId, 'supplier_invoice_no' => 'OPEN-' . $supplierId, 'purchase_date' => today(),
                    'subtotal' => $total, 'total' => $total, 'paid' => $total, 'due' => 0, 'payment_status' => 'paid', 'note' => 'Opening stock',
                ]);
                $p->update(['reference_no' => 'PUR-' . now()->format('Ymd') . '-' . str_pad($p->id, 5, '0', STR_PAD_LEFT)]);

                foreach ($rows as [$m, $qty, $days, $cost, $price]) {
                    $batch = $stock->addBatch($m, [
                        'purchase_id' => $p->id, 'supplier_id' => $supplierId, 'batch_no' => 'B' . random_int(1000, 9999),
                        'expiry_date' => today()->addDays($days), 'purchase_price' => $cost, 'sale_price' => $price, 'quantity' => $qty,
                    ], $p->reference_no);
                    $p->items()->create([
                        'medicine_id' => $m->id, 'medicine_batch_id' => $batch->id, 'batch_no' => $batch->batch_no, 'expiry_date' => $batch->expiry_date,
                        'quantity' => $qty, 'purchase_price' => $cost, 'sale_price' => $price, 'total' => $qty * $cost,
                    ]);
                }
            }
        });

        StockService::flushCache();
    }
}
