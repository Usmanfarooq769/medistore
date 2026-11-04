<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ControlledDocument extends Model
{
    protected $fillable=['document_code','title','category','current_version','status','effective_date','review_due_date','uploaded_by','approved_by','approved_at'];
    protected $casts=['effective_date'=>'date','review_due_date'=>'date','approved_at'=>'datetime'];
    public function revisions(){ return $this->hasMany(ControlledDocumentRevision::class, 'document_id')->orderByDesc('version'); }
    public function uploader(){ return $this->belongsTo(User::class, 'uploaded_by'); }
    public function approver(){ return $this->belongsTo(User::class, 'approved_by'); }
}
