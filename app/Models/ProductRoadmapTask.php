<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProductRoadmapTask extends Model
{
    protected $fillable=['milestone_id','title','description','status','sort_order','owner_id','due_date','completed_at'];
    protected $casts=['due_date'=>'date','completed_at'=>'datetime'];
    public function milestone(){ return $this->belongsTo(ProductRoadmapMilestone::class, 'milestone_id'); }
}
