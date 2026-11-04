<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ProductRoadmapMilestone;
use App\Models\ProductRoadmapTask;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductRoadmapController extends Controller
{
    public function index()
    {
        $milestones = ProductRoadmapMilestone::with('tasks')->orderBy('milestone_number')->get();
        $total = max(1, $milestones->count());
        $completed = $milestones->where('status','completed')->count();
        $overall = round($milestones->sum('progress_percent') / $total, 1);
        return response()->json([
            'summary'=>[
                'total_milestones'=>$milestones->count(), 'completed_milestones'=>$completed,
                'remaining_milestones'=>$milestones->count() - $completed,
                'overall_progress'=>$overall,
                'active_milestone'=>$milestones->firstWhere('status','in_progress'),
            ],
            'milestones'=>$milestones,
        ]);
    }

    public function updateMilestone(Request $request, ProductRoadmapMilestone $milestone)
    {
        $data = $request->validate([
            'status'=>['required', Rule::in(['planned','in_progress','blocked','completed'])],
            'progress_percent'=>['nullable','integer','min:0','max:100'],
            'target_date'=>['nullable','date'],
        ]);
        $old = $milestone->only(array_keys($data));
        $progress = $data['progress_percent'] ?? $milestone->progress_percent;
        if ($data['status'] === 'completed') $progress = 100;
        if ($data['status'] === 'planned') $progress = 0;
        $milestone->update(array_merge($data, ['progress_percent'=>$progress,'completed_at'=>$data['status']==='completed' ? now() : null]));
        AuditLog::create(['user_id'=>auth()->id(),'action'=>'product_roadmap.milestone_updated','subject_type'=>ProductRoadmapMilestone::class,'subject_id'=>$milestone->id,'old_values'=>$old,'new_values'=>$milestone->fresh()->toArray(),'ip_address'=>$request->ip()]);
        return response()->json($milestone->fresh()->load('tasks'));
    }

    public function updateTask(Request $request, ProductRoadmapTask $task)
    {
        $data = $request->validate([
            'status'=>['required', Rule::in(['planned','in_progress','blocked','completed'])],
            'owner_id'=>['nullable','exists:users,id'], 'due_date'=>['nullable','date'],
        ]);
        $task->update($data + ['completed_at'=>$data['status']==='completed' ? now() : null]);
        $milestone = $task->milestone;
        $tasks = $milestone->tasks()->get();
        if ($tasks->isNotEmpty()) {
            $percent = (int) round($tasks->where('status','completed')->count() / $tasks->count() * 100);
            $milestone->update(['progress_percent'=>$percent, 'status'=>$percent===100 ? 'completed' : ($percent > 0 ? 'in_progress' : 'planned'), 'completed_at'=>$percent===100 ? now() : null]);
        }
        AuditLog::create(['user_id'=>auth()->id(),'action'=>'product_roadmap.task_updated','subject_type'=>ProductRoadmapTask::class,'subject_id'=>$task->id,'new_values'=>$task->fresh()->toArray(),'ip_address'=>$request->ip()]);
        return response()->json(['task'=>$task->fresh(),'milestone'=>$milestone->fresh()]);
    }
}
