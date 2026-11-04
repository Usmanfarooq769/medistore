<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ControlledDocumentRevision extends Model
{
    protected $hidden=['file_path'];
    protected $fillable=['document_id','version','file_path','change_summary','created_by','approved_by','approved_at'];
    protected $casts=['approved_at'=>'datetime'];
    public function document(){ return $this->belongsTo(ControlledDocument::class, 'document_id'); }
}
