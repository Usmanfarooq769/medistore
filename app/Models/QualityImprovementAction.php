<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class QualityImprovementAction extends Model
{
    protected $fillable=['incident_id','owner_id','action_type','title','description','status','due_date','completed_at','verified_by','verified_at'];
    protected $casts=['due_date'=>'date','completed_at'=>'datetime','verified_at'=>'datetime'];
    public function incident(){ return $this->belongsTo(MedicationSafetyIncident::class, 'incident_id'); }
    public function owner(){ return $this->belongsTo(User::class, 'owner_id'); }
}
