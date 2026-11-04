<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ComplianceAuditItem extends Model
{
    protected $fillable=['audit_id','item_code','requirement','result','evidence_notes','corrective_action','reviewed_by','reviewed_at'];
    protected $casts=['reviewed_at'=>'datetime'];
    public function audit(){ return $this->belongsTo(ComplianceAudit::class, 'audit_id'); }
}
