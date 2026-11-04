<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ControlledDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DocumentGovernanceController extends Controller
{
    public function index(Request $request)
    {
        $query = ControlledDocument::with(['uploader:id,name','approver:id,name','revisions'])
            ->latest();
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('search')) $query->where(fn($q) => $q->where('title','like','%'.$request->search.'%')->orWhere('document_code','like','%'.$request->search.'%'));
        return $query->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'document_code'=>['required','string','max:60','unique:controlled_documents,document_code'],
            'title'=>['required','string','max:255'],
            'category'=>['required', Rule::in(['standard_operating_procedure','policy','work_instruction','form','emergency_plan','other'])],
            'change_summary'=>['nullable','string','max:5000'],
            'document_file'=>['required','file','mimes:pdf,doc,docx','max:15360'],
        ]);
        $path = $request->file('document_file')->store('controlled-documents', 'local');
        $document = DB::transaction(function () use ($data, $path) {
            $document = ControlledDocument::create([
                'document_code'=>$data['document_code'], 'title'=>$data['title'], 'category'=>$data['category'],
                'current_version'=>1, 'status'=>'pending_approval', 'uploaded_by'=>auth()->id(),
            ]);
            $document->revisions()->create(['version'=>1,'file_path'=>$path,'change_summary'=>$data['change_summary'] ?? null,'created_by'=>auth()->id()]);
            AuditLog::create(['user_id'=>auth()->id(),'action'=>'document.created','subject_type'=>ControlledDocument::class,'subject_id'=>$document->id,'new_values'=>$document->toArray(),'ip_address'=>request()->ip()]);
            return $document;
        });
        return response()->json($document->load('revisions'), 201);
    }

    public function revise(Request $request, ControlledDocument $document)
    {
        abort_if($document->status === 'retired', 422, 'Retired documents cannot be revised.');
        $data = $request->validate([
            'change_summary'=>['required','string','max:5000'],
            'document_file'=>['required','file','mimes:pdf,doc,docx','max:15360'],
        ]);
        $path = $request->file('document_file')->store('controlled-documents', 'local');
        DB::transaction(function () use ($document, $data, $path) {
            $version = ((int)$document->revisions()->max('version')) + 1;
            $document->revisions()->create(['version'=>$version,'file_path'=>$path,'change_summary'=>$data['change_summary'],'created_by'=>auth()->id()]);
            $document->update(['current_version'=>$version,'status'=>'pending_approval','approved_by'=>null,'approved_at'=>null]);
            AuditLog::create(['user_id'=>auth()->id(),'action'=>'document.revised','subject_type'=>ControlledDocument::class,'subject_id'=>$document->id,'new_values'=>['version'=>$version,'change_summary'=>$data['change_summary']],'ip_address'=>request()->ip()]);
        });
        return response()->json($document->fresh()->load('revisions'));
    }

    public function approve(Request $request, ControlledDocument $document)
    {
        abort_unless($document->status === 'pending_approval', 422, 'Only documents awaiting approval can be approved.');
        $data = $request->validate(['effective_date'=>['required','date'],'review_due_date'=>['required','date','after_or_equal:effective_date']]);
        DB::transaction(function () use ($document, $data) {
            $document->update($data + ['status'=>'approved','approved_by'=>auth()->id(),'approved_at'=>now()]);
            $document->revisions()->where('version',$document->current_version)->update(['approved_by'=>auth()->id(),'approved_at'=>now()]);
            AuditLog::create(['user_id'=>auth()->id(),'action'=>'document.approved','subject_type'=>ControlledDocument::class,'subject_id'=>$document->id,'new_values'=>$data,'ip_address'=>request()->ip()]);
        });
        return response()->json($document->fresh()->load('revisions'));
    }

    public function retire(ControlledDocument $document)
    {
        abort_if($document->status === 'retired', 422, 'Document is already retired.');
        $document->update(['status'=>'retired']);
        AuditLog::create(['user_id'=>auth()->id(),'action'=>'document.retired','subject_type'=>ControlledDocument::class,'subject_id'=>$document->id,'new_values'=>['status'=>'retired'],'ip_address'=>request()->ip()]);
        return response()->json($document);
    }

    public function download(ControlledDocument $document, int $revision)
    {
        $record = $document->revisions()->where('version',$revision)->firstOrFail();
        abort_unless($record->approved_at || auth()->user()->role === 'admin', 403, 'This document revision has not been approved.');
        abort_unless(Storage::disk('local')->exists($record->file_path), 404);
        return Storage::disk('local')->download($record->file_path, $document->document_code . '-v' . $revision . '.' . pathinfo($record->file_path, PATHINFO_EXTENSION));
    }
}
