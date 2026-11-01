<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Prescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; use Illuminate\Support\Facades\Storage;

class PrescriptionController extends Controller
{
 public function index(Request $r){return Prescription::with(['customer:id,name,phone','items.medicine:id,name,strength'])->when($r->status,fn($q,$v)=>$q->where('status',$v))->when($r->search,fn($q,$v)=>$q->where('prescription_no','like',"%{$v}%"))->latest()->paginate(20);}
 public function store(Request $r){$d=$r->validate(['customer_id'=>'nullable|exists:customers,id','doctor_name'=>'required|string|max:150','doctor_license'=>'nullable|string|max:100','prescription_date'=>'required|date','notes'=>'nullable|string|max:2000','file'=>'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240','items'=>'nullable|array','items.*.medicine_id'=>'nullable|exists:medicines,id','items.*.medicine_text'=>'nullable|string|max:180','items.*.quantity'=>'required_with:items|numeric|min:0.01','items.*.dosage'=>'nullable|string|max:120','items.*.duration'=>'nullable|string|max:100','items.*.instructions'=>'nullable|string|max:1000']);$p=DB::transaction(function()use($r,$d){$path=$r->file('file')?->store('prescriptions','local');$p=Prescription::create(['prescription_no'=>'RX-'.now()->format('Ymd').'-'.strtoupper(str()->random(5)),'customer_id'=>$d['customer_id']??null,'doctor_name'=>$d['doctor_name'],'doctor_license'=>$d['doctor_license']??null,'prescription_date'=>$d['prescription_date'],'file_path'=>$path,'status'=>'pending','notes'=>$d['notes']??null,'created_by'=>auth()->id()]);foreach($d['items']??[] as $i)$p->items()->create($i);return $p;});return response()->json(['message'=>'Prescription uploaded for review.','data'=>$p->load('items')],201);}
 public function file(Prescription $prescription){abort_unless($prescription->file_path && Storage::disk('local')->exists($prescription->file_path),404);return Storage::disk('local')->download($prescription->file_path);}
 public function find(string $prescriptionNo){$p=Prescription::where('prescription_no',$prescriptionNo)->firstOrFail();return response()->json(['id'=>$p->id,'prescription_no'=>$p->prescription_no,'status'=>$p->status,'customer_id'=>$p->customer_id]);}
 public function review(Prescription $prescription,Request $r){$d=$r->validate(['decision'=>'required|in:approve,reject','notes'=>'nullable|string|max:1000']);$prescription=DB::transaction(function()use($prescription,$d){$p=Prescription::lockForUpdate()->findOrFail($prescription->id);abort_unless($p->status==='pending',422,'Only pending prescriptions can be reviewed.');$p->update(['status'=>$d['decision']==='approve'?'approved':'rejected','reviewed_by'=>auth()->id(),'reviewed_at'=>now(),'notes'=>trim(($p->notes??'')."\n".($d['notes']??''))]);return $p;});return response()->json(['message'=>'Prescription '.$prescription->status.'.','data'=>$prescription->fresh('items')]);}
}
