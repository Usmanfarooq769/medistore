<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use App\Models\AuditLog;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once app_path('helpers.php');
    }

    private static function auditSafe(array $values): array
    {
        unset($values['password'], $values['remember_token'], $values['api_token']);
        return array_intersect_key($values, array_flip(['name','generic_name','strength','barcode','pack_size','stock_units_are_base','purchase_price','sale_price','total_stock','reorder_level','is_active','balance','phone','email','reference_no','invoice_no','total','paid','due','payment_status','role','value']));
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Append-only audit trail for important master and transaction changes.
        $tracked = [
            \App\Models\Medicine::class, \App\Models\Customer::class,
            \App\Models\Supplier::class, \App\Models\Purchase::class,
            \App\Models\Sale::class, \App\Models\Setting::class,
        ];
        Model::created(function (Model $model) use ($tracked) {
            if (! in_array($model::class, $tracked, true)) return;
            AuditLog::create(['user_id' => auth()->id(), 'action' => 'created', 'subject_type' => $model::class,
                'subject_id' => $model->getKey(), 'new_values' => self::auditSafe($model->getAttributes()), 'ip_address' => request()?->ip()]);
        });
        Model::updated(function (Model $model) use ($tracked) {
            if (! in_array($model::class, $tracked, true)) return;
            $changes = collect($model->getChanges())->except(['updated_at'])->all();
            if (! $changes) return;
            $previous = $model->getPrevious();
            $old = collect($previous)->only(array_keys($changes))->all();
            AuditLog::create(['user_id' => auth()->id(), 'action' => 'updated', 'subject_type' => $model::class,
                'subject_id' => $model->getKey(), 'old_values' => self::auditSafe($old), 'new_values' => self::auditSafe($changes), 'ip_address' => request()?->ip()]);
        });
        Model::deleted(function (Model $model) use ($tracked) {
            if (! in_array($model::class, $tracked, true)) return;
            AuditLog::create(['user_id' => auth()->id(), 'action' => 'deleted', 'subject_type' => $model::class,
                'subject_id' => $model->getKey(), 'old_values' => self::auditSafe($model->getOriginal()), 'ip_address' => request()?->ip()]);
        });
    }
}
