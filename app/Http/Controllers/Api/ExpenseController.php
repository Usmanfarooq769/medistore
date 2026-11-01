<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller; use App\Models\Expense; use App\Services\BranchContext; use App\Services\JournalPostingService; use Illuminate\Http\Request; use Illuminate\Support\Facades\DB;
class ExpenseController extends Controller
{
 public function index(Request $r){return Expense::with('branch:id,name')->where('branch_id',BranchContext::id())->when($r->from,fn($q,$v)=>$q->whereDate('expense_date','>=',$v))->when($r->to,fn($q,$v)=>$q->whereDate('expense_date','<=',$v))->when($r->category,fn($q,$v)=>$q->where('category',$v))->latest('expense_date')->paginate(30);}
 public function store(Request $r){$d=$r->validate(['category'=>'required|string|max:100','description'=>'required|string|max:255','amount'=>'required|numeric|gt:0','expense_date'=>'required|date','payment_method'=>'required|in:cash,card,online,bank','reference'=>'nullable|string|max:100']);$e=DB::transaction(function()use($d){$expense=Expense::create($d+['branch_id'=>BranchContext::id(),'created_by'=>auth()->id(),'status'=>'approved']);app(JournalPostingService::class)->postExpense($expense);return $expense;});return response()->json(['message'=>'Expense saved.','data'=>$e],201);}
 public function update(Request $r,Expense $expense){abort_unless((int)$expense->branch_id===(int)BranchContext::id(),404);return response()->json(['message'=>'Posted expenses are immutable for accounting accuracy. Delete the expense (which creates a reversing journal) and record a corrected expense.'],422);}
 public function destroy(Expense $expense){abort_unless((int)$expense->branch_id===(int)BranchContext::id(),404);DB::transaction(function()use($expense){app(JournalPostingService::class)->reverseSource(Expense::class,$expense->id,(int)$expense->branch_id,'Expense deleted');$expense->delete();});return response()->json(['message'=>'Expense deleted.']);}
}
