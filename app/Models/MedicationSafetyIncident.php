<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicationSafetyIncident extends Model
{
    protected $fillable = ['incident_number','branch_id','medicine_id','batch_id','customer_id','reported_by','reviewed_by','closed_by','incident_type','severity','status','occurred_at','description','immediate_action','root_cause','clinical_outcome','closure_summary','reviewed_at','closed_at'];
    protected $casts = ['occurred_at'=>'datetime','reviewed_at'=>'datetime','closed_at'=>'datetime'];
    public function branch(){ return $this->belongsTo(Branch::class); }
    public function medicine(){ return $this->belongsTo(Medicine::class); }
    public function batch(){ return $this->belongsTo(MedicineBatch::class, 'batch_id'); }
    public function customer(){ return $this->belongsTo(Customer::class); }
    public function reporter(){ return $this->belongsTo(User::class, 'reported_by'); }
    public function reviewer(){ return $this->belongsTo(User::class, 'reviewed_by'); }
    public function actions(){ return $this->hasMany(QualityImprovementAction::class, 'incident_id'); }
}
