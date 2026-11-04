<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProductRoadmapMilestone extends Model
{
    protected $fillable=['milestone_number','milestone_code','title','description','status','progress_percent','target_date','completed_at'];
    protected $casts=['target_date'=>'date','completed_at'=>'datetime'];
    public function tasks(){ return $this->hasMany(ProductRoadmapTask::class, 'milestone_id')->orderBy('sort_order')->orderBy('id'); }
}
