<?php

namespace Tests\Feature;

use App\Models\CashierSession;
use App\Models\Customer;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfessionalUpgradeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function as(string $role, array $extra = []): User
    {
        $u = User::factory()->create(['role' => $role, 'is_active' => true, 'branch_id' => 1] + $extra);
        Sanctum::actingAs($u);
        return $u;
    }

    private function med(string $name, string $generic, float $price = 50, int $qty = 20, array $extra = []): Medicine
    {
        $m = Medicine::create(['name' => $name, 'generic_name' => $generic, 'strength' => '500mg', 'unit' => 'strip', 'purchase_price' => $price / 2, 'sale_price' => $price, 'reorder_level' => 5, 'is_active' => true] + $extra);
        $b = new MedicineBatch(['medicine_id' => $m->id, 'batch_no' => 'B' . $m->id, 'expiry_date' => now()->addYear()->toDateString(), 'purchase_price' => $price / 2, 'sale_price' => $price, 'initial_quantity' => $qty, 'quantity' => $qty]);
        $b->branch_id = 1; $b->save();
        $m->update(['total_stock' => $qty]);
        return $m;
    }

    private function cart(Medicine $m, int $qty = 1): array
    {
        return ['items' => [['id' => $m->id, 'quantity' => $qty, 'sale_unit' => 'unit']], 'paid' => $qty * (float) $m->sale_price, 'payment_method' => 'cash'];
    }

    public function test_dashboard_has_all_new_sections(): void
    {
        $this->as('admin');
        $r = $this->getJson('/api/dashboard')->assertOk();
        foreach (['cards', 'pending', 'chart', 'chart30', 'hourly', 'payments_today', 'payments_month', 'top', 'top_categories', 'top_customers', 'recent', 'recent_purchases', 'expiring_list', 'low'] as $k) $r->assertJsonStructure([$k]);
        foreach (['today_net', 'change_pct', 'month_net_profit', 'expiring_value', 'expired_value', 'dead_stock', 'dead_stock_value', 'stock_sale_value', 'out_of_stock', 'margin_pct'] as $k) $this->assertArrayHasKey($k, $r->json('cards'));
        $this->assertCount(7, $r->json('chart.values'));
        $this->assertCount(30, $r->json('chart30.values'));
        $this->assertCount(24, $r->json('hourly.values'));
    }

    public function test_dashboard_numbers_after_a_sale(): void
    {
        $this->as('admin');
        $m = $this->med('TestMed', 'testgeneric', 100, 10);
        $this->postJson('/api/pos/checkout', $this->cart($m, 3))->assertCreated();
        $c = $this->getJson('/api/dashboard')->assertOk()->json('cards');
        $this->assertEquals(300, $c['today_sales']);
        $this->assertEquals(300, $c['today_net']);
        $this->assertEquals(150, $c['today_profit']);   // price 100 - cost 50 = 50 x 3
        $this->assertEquals(1, $c['today_invoices']);
        $this->assertEquals(100, $this->getJson('/api/dashboard')->json('payments_today.cash') / 3);
    }

    public function test_allergy_interaction_and_duplicate_warnings(): void
    {
        $this->as('admin');
        $warfarin = $this->med('Warfarin', 'Warfarin');
        $brufen = Medicine::where('name', 'Brufen')->first();
        $augmentin = Medicine::where('name', 'Augmentin')->first();
        $panadol = Medicine::where('name', 'Panadol')->first();
        $calpol = $this->med('Calpol', 'Paracetamol');
        $cust = Customer::create(['name' => 'Allergic Person', 'phone' => '0300111', 'allergies' => 'Penicillin, peanuts']);

        $r = $this->postJson('/api/pos/safety-check', ['medicine_ids' => [$augmentin->id], 'customer_id' => $cust->id])->assertOk();
        $this->assertSame('danger', $r->json('level'));
        $this->assertNotEmpty($r->json('allergies'));

        $r = $this->postJson('/api/pos/safety-check', ['medicine_ids' => [$warfarin->id, $brufen->id]])->assertOk();
        $this->assertSame('danger', $r->json('level'));
        $this->assertSame('major', $r->json('interactions.0.severity'));

        $r = $this->postJson('/api/pos/safety-check', ['medicine_ids' => [$panadol->id, $calpol->id]])->assertOk();
        $this->assertSame('warning', $r->json('level'));
        $this->assertNotEmpty($r->json('duplicates'));

        $r = $this->postJson('/api/pos/safety-check', ['medicine_ids' => [$panadol->id]])->assertOk();
        $this->assertSame('none', $r->json('level'));
    }

    public function test_checkout_blocks_danger_then_allows_override(): void
    {
        $admin = $this->as('admin');
        $cust = Customer::create(['name' => 'Pen Allergic', 'phone' => '0300222', 'allergies' => 'penicillin']);
        $aug = Medicine::where('name', 'Augmentin')->first();
        $payload = $this->cart($aug) + ['customer_id' => $cust->id];

        $this->postJson('/api/pos/checkout', $payload)->assertStatus(422)->assertJson(['safety_warning' => true]);
        $r = $this->postJson('/api/pos/checkout', $payload + ['safety_override' => true, 'safety_override_note' => 'Doctor confirmed tolerance'])->assertCreated();
        $this->assertDatabaseHas('sales', ['id' => $r->json('data.id'), 'approved_by' => $admin->id, 'safety_override_note' => 'Doctor confirmed tolerance']);
    }

    public function test_cashier_needs_manager_pin_to_override_safety_and_discount(): void
    {
        $manager = User::factory()->create(['role' => 'branch_manager', 'is_active' => true, 'branch_id' => 1]);
        $manager->setManagerPin('4321'); $manager->save();
        $cashier = $this->as('cashier');
        CashierSession::create(['user_id' => $cashier->id, 'branch_id' => 1, 'opened_at' => now(), 'opening_cash' => 0, 'expected_cash' => 0]);
        $m = $this->med('DiscMed', 'discgeneric', 100, 20);

        // 10% is the limit; 30% needs a PIN
        $p = $this->cart($m, 2) + ['discount' => 60];
        $p['paid'] = 140;
        $this->postJson('/api/pos/checkout', $p)->assertStatus(422)->assertJson(['requires_manager_pin' => true]);
        $this->postJson('/api/pos/checkout', $p + ['manager_pin' => '0000'])->assertStatus(422);
        $ok = $this->postJson('/api/pos/checkout', $p + ['manager_pin' => '4321'])->assertCreated();
        $this->assertDatabaseHas('sales', ['id' => $ok->json('data.id'), 'approved_by' => $manager->id]);

        // safety override by cashier also needs a PIN
        $cust = Customer::create(['name' => 'X', 'phone' => '0300333', 'allergies' => 'penicillin']);
        $aug = Medicine::where('name', 'Augmentin')->first();
        $sp = $this->cart($aug) + ['customer_id' => $cust->id, 'safety_override' => true];
        $this->postJson('/api/pos/checkout', $sp)->assertStatus(422)->assertJson(['requires_manager_pin' => true]);
        $this->postJson('/api/pos/checkout', $sp + ['manager_pin' => '4321'])->assertCreated();
    }

    public function test_substitutes_and_global_search(): void
    {
        $this->as('admin');
        $panadol = Medicine::where('name', 'Panadol')->first();
        $this->med('Calpol', 'Paracetamol', 20, 10);
        $r = $this->getJson("/api/medicines/{$panadol->id}/substitutes")->assertOk();
        $this->assertSame('Calpol', $r->json('data.0.name'));
        $this->assertTrue($r->json('data.0.in_stock'));
        $this->assertLessThan(0, $r->json('data.0.price_diff'));

        $s = $this->getJson('/api/search?q=pana')->assertOk()->json('data');
        $this->assertSame('Medicine', $s[0]['type']);
        $this->getJson('/api/search?q=a')->assertOk()->assertJson(['data' => []]);
    }

    public function test_fbr_local_and_remote_numbers(): void
    {
        $this->as('admin');
        $m = $this->med('FbrMed', 'fbrgeneric', 10, 50);
        $a = $this->postJson('/api/pos/checkout', $this->cart($m))->assertCreated()->json('data.id');
        $this->assertDatabaseHas('sales', ['id' => $a, 'fbr_status' => 'not_required']);

        Setting::put(['fbr_enabled' => '1', 'fbr_pos_id' => 'P77']);
        $b = $this->postJson('/api/pos/checkout', $this->cart($m))->assertCreated()->json('data.id');
        $sale = \App\Models\Sale::find($b);
        $this->assertSame('local', $sale->fbr_status);
        $this->assertStringStartsWith('P77-', $sale->fbr_invoice_no);

        Setting::put(['fbr_api_url' => 'https://fbr.test/api', 'fbr_api_token' => 'tok']);
        Http::fake(['fbr.test/*' => Http::sequence()->push(['InvoiceNumber' => 'FBR-123456', 'Code' => '100'], 200)->push('down', 500)->push(['InvoiceNumber' => 'FBR-999'], 200)]);
        $c = $this->postJson('/api/pos/checkout', $this->cart($m))->assertCreated()->json('data.id');
        $this->assertDatabaseHas('sales', ['id' => $c, 'fbr_status' => 'synced', 'fbr_invoice_no' => 'FBR-123456']);

        $d = $this->postJson('/api/pos/checkout', $this->cart($m))->assertCreated()->json('data.id');
        $this->assertDatabaseHas('sales', ['id' => $d, 'fbr_status' => 'pending']);
        $this->postJson("/api/sales/{$d}/fbr-sync")->assertOk()->assertJson(['data' => ['fbr_status' => 'synced']]);
    }

    public function test_void_needs_permission_or_pin(): void
    {
        $manager = User::factory()->create(['role' => 'branch_manager', 'is_active' => true, 'branch_id' => 1]);
        $manager->setManagerPin('9999'); $manager->save();
        $this->as('admin');
        $m = $this->med('VoidMed', 'voidgeneric', 10, 50);
        $id = $this->postJson('/api/pos/checkout', ['items' => [['id' => $m->id, 'quantity' => 1, 'sale_unit' => 'unit']], 'paid' => 0, 'payment_method' => 'credit', 'customer_name' => 'Z', 'customer_phone' => '0311999'])->assertCreated()->json('data.id');

        $pharm = $this->as('pharmacist');
        $this->deleteJson("/api/sales/{$id}")->assertStatus(403)->assertJson(['requires_manager_pin' => true]);
        $this->deleteJson("/api/sales/{$id}", ['manager_pin' => '9999'])->assertOk();
        $this->assertDatabaseMissing('sales', ['id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sale_voided']);
    }

    public function test_controlled_register_report(): void
    {
        $this->as('admin');
        $m = $this->med('Morphex', 'morphine', 100, 30, ['requires_controlled_tracking' => true, 'drug_class' => 'narcotic']);
        $this->postJson('/api/pos/checkout', $this->cart($m, 4))->assertCreated();
        $r = $this->getJson('/api/reports/controlled-register')->assertOk();
        $row = collect($r->json('summary'))->firstWhere('medicine_id', $m->id);
        $this->assertSame(4, $row['dispensed']);
        $this->assertSame(-4, $row['closing']);   // no receipt rows were recorded in this test
        $this->assertCount(1, $r->json('ledger'));

        $this->as('cashier');
        $this->getJson('/api/reports/controlled-register')->assertStatus(403);
    }

    public function test_user_pin_permissions_and_secret_hiding(): void
    {
        $this->as('admin');
        $r = $this->postJson('/api/users', ['name' => 'Mgr', 'email' => 'm@x.test', 'password' => 'secret12', 'role' => 'branch_manager', 'branch_id' => 1, 'manager_pin' => '1234', 'permissions' => ['reports.profit' => false, 'bogus' => true]])->assertCreated();
        $u = User::find($r->json('data.id'));
        $this->assertTrue($u->hasPermission('sales.void'));
        $this->assertFalse($u->hasPermission('reports.profit'));
        $this->assertArrayNotHasKey('bogus', (array) $u->permissions);
        $this->assertNotSame('1234', $u->manager_pin);
        $this->assertTrue($r->json('data.has_pin'));
        $this->getJson('/api/permission-catalog')->assertOk()->assertJsonStructure(['labels', 'roles']);

        $this->putJson('/api/settings', ['store_name' => 'S', 'currency' => 'Rs.', 'default_tax' => 0, 'max_discount_percent' => 10, 'expiry_alert_days' => 60, 'return_days' => 7, 'cashier_price_edit' => '0',
            'loyalty_earn_per_100' => 1, 'loyalty_point_value' => 1, 'fbr_enabled' => '1', 'fbr_pos_id' => '12', 'fbr_api_token' => 'TOPSECRET', 'receipt_width' => '58'])->assertOk();
        $json = $this->getJson('/api/settings')->assertOk()->json();
        $this->assertArrayNotHasKey('fbr_api_token', $json);
        $this->assertSame('1', $json['fbr_api_token_set']);
        $this->assertSame('58', $json['receipt_width']);
        $this->assertSame('TOPSECRET', setting('fbr_api_token'));
        // empty token keeps the saved one
        $this->putJson('/api/settings', ['store_name' => 'S', 'currency' => 'Rs.', 'default_tax' => 0, 'max_discount_percent' => 10, 'expiry_alert_days' => 60, 'return_days' => 7, 'cashier_price_edit' => '0', 'loyalty_earn_per_100' => 1, 'loyalty_point_value' => 1, 'fbr_api_token' => ''])->assertOk();
        $this->assertSame('TOPSECRET', setting('fbr_api_token'));
    }

    public function test_customer_and_medicine_new_fields(): void
    {
        $this->as('admin');
        $c = $this->postJson('/api/customers', ['name' => 'New Pt', 'phone' => '0300555', 'allergies' => 'sulfa', 'cnic' => '35202-1234567-1', 'gender' => 'male', 'date_of_birth' => '1990-05-01', 'email' => 'a@b.co'])->assertCreated();
        $this->assertSame('sulfa', $c->json('data.allergies'));
        $this->postJson('/api/customers', ['name' => 'Bad', 'cnic' => '123'])->assertStatus(422);

        $m = $this->postJson('/api/medicines', ['name' => 'Tramal', 'generic_name' => 'Tramadol', 'unit' => 'strip', 'pack_size' => 1, 'purchase_price' => 10, 'sale_price' => 15, 'reorder_level' => 5, 'drug_class' => 'narcotic', 'dosage_form' => 'capsule', 'storage_condition' => 'room'])->assertCreated();
        $this->assertTrue($m->json('data.requires_controlled_tracking'));
        $this->assertSame('narcotic', $m->json('data.drug_class'));
    }
}
