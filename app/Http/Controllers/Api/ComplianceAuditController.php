<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ComplianceAudit;
use App\Services\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ComplianceAuditController extends Controller
{
    public function index(Request $request)
    {
        $query = ComplianceAudit::with(['branch:id,name','items'])->latest('audit_date');
        if (auth()->user()->role !== 'admin') $query->where('branch_id', BranchContext::id());
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        return $query->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'=>['required','string','max:255'], 'audit_scope'=>['nullable','string','max:100'],
            'audit_date'=>['required','date'], 'summary'=>['nullable','string','max:10000'],
            'items'=>['required','array','min:1','max:100'],
            'items.*.item_code'=>['nullable','string','max:40'],
            'items.*.requirement'=>['required','string','max:1000'],
        ]);
        $audit = DB::transaction(function () use ($data) {
            $audit = ComplianceAudit::create([
                'branch_id'=>BranchContext::id(), 'title'=>$data['title'], 'audit_scope'=>$data['audit_scope'] ?? null,
                'audit_date'=>$data['audit_date'], 'summary'=>$data['summary'] ?? null, 'created_by'=>auth()->id(), 'status'=>'in_progress',
            ]);
            $audit->update(['audit_number'=>'AUD-' . now()->format('ym') . '-' . str_pad((string)$audit->id, 6, '0', STR_PAD_LEFT)]);
            foreach ($data['items'] as $item) $audit->items()->create($item + ['result'=>'pending']);
            AuditLog::create(['user_id'=>auth()->id(),'action'=>'compliance_audit.created','subject_type'=>ComplianceAudit::class,'subject_id'=>$audit->id,'new_values'=>$audit->toArray(),'ip_address'=>request()->ip()]);
            return $audit;
        });
        return response()->json($audit->load('items'), 201);
    }

    public function updateItem(Request $request, ComplianceAudit $audit, int $item)
    {
        $this->assertBranch($audit);
        abort_if($audit->status === 'closed', 422, 'Closed audits cannot be edited.');
        $record = $audit->items()->findOrFail($item);
        $data = $request->validate([
            'result'=>['required', Rule::in(['pending','compliant','non_compliant','not_applicable'])],
            'evidence_notes'=>['nullable','string','max:5000'],
            'corrective_action'=>['nullable','string','max:5000'],
        ]);
        if ($data['result'] === 'non_compliant' && empty($data['corrective_action'])) {
            return response()->json(['message'=>'A corrective action is required for a non-compliant finding.','errors'=>['corrective_action'=>['A corrective action is required.']]], 422);
        }
        $record->update($data + ['reviewed_by'=>auth()->id(),'reviewed_at'=>now()]);
        AuditLog::create(['user_id'=>auth()->id(),'action'=>'compliance_audit.item_updated','subject_type'=>ComplianceAudit::class,'subject_id'=>$audit->id,'new_values'=>$record->toArray(),'ip_address'=>$request->ip()]);
        return response()->json($record);
    }

    public function close(Request $request, ComplianceAudit $audit)
    {
        $this->assertBranch($audit);
        abort_if($audit->status === 'closed', 422, 'Audit is already closed.');
        $data = $request->validate(['summary'=>['required','string','max:10000']]);
        $pending = $audit->items()->where('result','pending')->exists();
        abort_if($pending, 422, 'Complete every checklist item before closing the audit.');
        $audit->update(['status'=>'closed','summary'=>$data['summary'],'closed_by'=>auth()->id(),'closed_at'=>now()]);
        AuditLog::create(['user_id'=>auth()->id(),'action'=>'compliance_audit.closed','subject_type'=>ComplianceAudit::class,'subject_id'=>$audit->id,'new_values'=>$data,'ip_address'=>$request->ip()]);
        return response()->json($audit->fresh()->load('items'));
    }

    private function assertBranch(ComplianceAudit $audit): void
    {
        if (auth()->user()->role !== 'admin') abort_unless($audit->branch_id === BranchContext::id(), 404);
    }
}
