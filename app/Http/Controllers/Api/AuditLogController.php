<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        return AuditLog::with('user:id,name')->latest()->paginate($request->integer('per_page', 30));
    }
}
