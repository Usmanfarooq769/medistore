<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ComplianceAudit extends Model
{
    protected $fillable=['audit_number','branch_id','title','audit_scope','audit_date','status','created_by','closed_by','closed_at','summary'];
    protected $casts=['audit_date'=>'date','closed_at'=>'datetime'];
    public function items(){ return $this->hasMany(ComplianceAuditItem::class, 'audit_id'); }
    public function branch(){ return $this->belongsTo(Branch::class); }
}
