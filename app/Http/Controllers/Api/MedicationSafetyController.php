<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MedicationSafetyIncident;
use App\Models\QualityImprovementAction;
use App\Services\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MedicationSafetyController extends Controller
{
    public function overview()
    {
        $branchId = BranchContext::id();
        $query = MedicationSafetyIncident::where('branch_id', $branchId);
        return response()->json([
            'total' => (clone $query)->count(),
            'open' => (clone $query)->whereNotIn('status', ['closed'])->count(),
            'serious' => (clone $query)->whereIn('severity', ['high','critical'])->where('status', '!=', 'closed')->count(),
            'overdue_actions' => QualityImprovementAction::whereHas('incident', fn($q) => $q->where('branch_id', $branchId))
                ->whereNotIn('status', ['completed','verified'])->whereDate('due_date', '<', today())->count(),
            'incidents_by_type' => (clone $query)->select('incident_type', DB::raw('COUNT(*) as total'))->groupBy('incident_type')->get(),
        ]);
    }

    public function index(Request $request)
    {
        $query = MedicationSafetyIncident::with(['medicine:id,name,strength','batch:id,batch_no,expiry_date','reporter:id,name','actions'])
            ->where('branch_id', BranchContext::id())->latest('occurred_at');
        if ($request->filled('status')) $query->where('status', (string) $request->input('status'));
        if ($request->filled('incident_type')) $query->where('incident_type', (string) $request->input('incident_type'));
        return $query->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'incident_type' => ['required', Rule::in(['medication_error','adverse_reaction','near_miss','product_quality','cold_chain','other'])],
            'severity' => ['required', Rule::in(['low','moderate','high','critical'])],
            'occurred_at' => ['required','date'], 'description' => ['required','string','max:10000'],
            'medicine_id' => ['nullable','exists:medicines,id'], 'batch_id' => ['nullable','exists:medicine_batches,id'],
            'customer_id' => ['nullable','exists:customers,id'], 'immediate_action' => ['nullable','string','max:5000'],
            'clinical_outcome' => ['nullable','string','max:5000'],
        ]);
        if (!empty($data['batch_id'])) {
            $batchQuery = \App\Models\MedicineBatch::whereKey($data['batch_id'])->where('branch_id', BranchContext::id());
            if (!empty($data['medicine_id'])) $batchQuery->where('medicine_id', $data['medicine_id']);
            abort_unless($batchQuery->exists(), 422, 'The selected batch is not available in the active branch or does not belong to this medicine.');
        }
        $incident = DB::transaction(function () use ($data) {
            $incident = MedicationSafetyIncident::create($data + [
                'branch_id' => BranchContext::id(), 'reported_by' => auth()->id(), 'status' => 'reported',
            ]);
            $incident->update(['incident_number' => 'MSI-' . now()->format('ym') . '-' . str_pad((string)$incident->id, 6, '0', STR_PAD_LEFT)]);
            $this->audit('reported', $incident, null, $incident->toArray());
            return $incident;
        });
        return response()->json($incident->load(['medicine','batch','reporter']), 201);
    }

    public function show(MedicationSafetyIncident $incident)
    {
        $this->assertBranch($incident);
        return $incident->load(['medicine','batch','customer','reporter','reviewer','actions.owner']);
    }

    public function review(Request $request, MedicationSafetyIncident $incident)
    {
        $this->assertBranch($incident);
        abort_if($incident->status === 'closed', 422, 'Closed incidents cannot be reviewed.');
        $data = $request->validate([
            'root_cause' => ['nullable','string','max:10000'],
            'immediate_action' => ['nullable','string','max:5000'],
            'clinical_outcome' => ['nullable','string','max:5000'],
            'status' => ['nullable', Rule::in(['under_review','action_required'])],
        ]);
        $old = $incident->only(array_keys($data));
        $incident->fill($data);
        $incident->reviewed_by = auth()->id();
        $incident->reviewed_at = now();
        $incident->status = $data['status'] ?? 'under_review';
        $incident->save();
        $this->audit('reviewed', $incident, $old, $incident->only(array_keys($data)));
        return response()->json($incident->fresh()->load('actions'));
    }

    public function storeAction(Request $request, MedicationSafetyIncident $incident)
    {
        $this->assertBranch($incident);
        abort_if($incident->status === 'closed', 422, 'Cannot add actions to a closed incident.');
        $data = $request->validate([
            'action_type' => ['required', Rule::in(['corrective','preventive'])],
            'title' => ['required','string','max:255'], 'description' => ['nullable','string','max:5000'],
            'owner_id' => ['nullable','exists:users,id'], 'due_date' => ['nullable','date'],
        ]);
        $action = $incident->actions()->create($data + ['status'=>'open']);
        $incident->update(['status'=>'action_required']);
        $this->audit('action_created', $incident, null, $action->toArray());
        return response()->json($action, 201);
    }

    public function updateAction(Request $request, QualityImprovementAction $action)
    {
        $this->assertBranch($action->incident);
        $data = $request->validate([
            'status' => ['required', Rule::in(['open','in_progress','completed','verified'])],
            'description' => ['nullable','string','max:5000'], 'due_date' => ['nullable','date'],
        ]);
        $old = $action->only(array_keys($data));
        $action->fill($data);
        if ($action->status === 'completed') $action->completed_at = now();
        if ($action->status === 'verified') { $action->verified_by = auth()->id(); $action->verified_at = now(); $action->completed_at ??= now(); }
        $action->save();
        $this->audit('action_updated', $action->incident, $old, $action->toArray());
        return response()->json($action);
    }

    public function close(Request $request, MedicationSafetyIncident $incident)
    {
        $this->assertBranch($incident);
        $data = $request->validate(['closure_summary'=>['required','string','max:10000']]);
        $pending = $incident->actions()->whereNotIn('status', ['verified'])->exists();
        abort_if($pending, 422, 'Verify or complete all corrective actions before closing this incident.');
        $incident->update(['status'=>'closed','closure_summary'=>$data['closure_summary'],'closed_by'=>auth()->id(),'closed_at'=>now()]);
        $this->audit('closed', $incident, null, ['closure_summary'=>$data['closure_summary']]);
        return response()->json($incident->fresh());
    }

    private function assertBranch(?MedicationSafetyIncident $incident): void
    {
        abort_unless($incident && (int) $incident->branch_id === BranchContext::id(), 404);
    }

    private function audit(string $action, MedicationSafetyIncident $incident, ?array $old, ?array $new): void
    {
        AuditLog::create(['user_id'=>auth()->id(),'action'=>'medication_safety.' . $action,'subject_type'=>MedicationSafetyIncident::class,'subject_id'=>$incident->id,'old_values'=>$old,'new_values'=>$new,'ip_address'=>request()->ip()]);
    }
}
