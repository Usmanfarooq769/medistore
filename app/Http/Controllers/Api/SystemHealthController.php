<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class SystemHealthController extends Controller
{
    public function overview()
    {
        $latest=DB::table('system_health_snapshots')->orderByDesc('checked_at')->first();
        $backup=DB::table('backup_runs')->where('status','success')->orderByDesc('completed_at')->first();
        return response()->json([
            'latest_snapshot'=>$latest ? ['id'=>$latest->id,'overall_status'=>$latest->overall_status,'checks'=>json_decode($latest->checks,true),'checked_at'=>$latest->checked_at] : null,
            'latest_successful_backup'=>$backup ? ['completed_at'=>$backup->completed_at,'size_bytes'=>$backup->size_bytes,'disk'=>$backup->disk] : null,
            'failed_jobs'=>Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
            'releases'=>DB::table('deployment_releases')->orderByDesc('id')->limit(10)->get(),
            'recovery_drills'=>DB::table('disaster_recovery_drills')->orderByDesc('id')->limit(10)->get(),
        ]);
    }

    public function runChecks(Request $request)
    {
        $checks=[];
        try { DB::select('SELECT 1'); $checks['database']=['status'=>'ok','message'=>'Database connection successful.']; }
        catch(Throwable $e){ $checks['database']=['status'=>'critical','message'=>'Database connection failed.']; }
        try { $key='health-check-'.Str::random(12); Cache::put($key,'ok',10); $ok=Cache::get($key)==='ok'; Cache::forget($key); $checks['cache']=['status'=>$ok?'ok':'warning','message'=>$ok?'Cache read/write successful.':'Cache read/write failed.']; }
        catch(Throwable $e){ $checks['cache']=['status'=>'warning','message'=>'Cache service unavailable.']; }
        try { $path=storage_path('app/.health-'.Str::random(12)); file_put_contents($path,'ok'); $ok=file_get_contents($path)==='ok'; @unlink($path); $checks['storage']=['status'=>$ok?'ok':'critical','message'=>$ok?'Storage write/read successful.':'Storage write/read failed.']; }
        catch(Throwable $e){ $checks['storage']=['status'=>'critical','message'=>'Storage is not writable.']; }
        $failed=Schema::hasTable('failed_jobs')?DB::table('failed_jobs')->count():0;
        $checks['queue']=['status'=>$failed>0?'warning':'ok','message'=>$failed>0?"{$failed} failed queue job(s) require review.":'No failed queue jobs.'];
        $backup=DB::table('backup_runs')->where('status','success')->orderByDesc('completed_at')->first();
        $backupFresh=$backup && $backup->completed_at && now()->diffInHours($backup->completed_at)<=36;
        $checks['backup']=['status'=>$backupFresh?'ok':'warning','message'=>$backupFresh?'A recent successful backup exists.':'No successful backup within the last 36 hours.'];
        $configProblems = [];
        if (config('app.debug')) $configProblems[] = 'APP_DEBUG is enabled';
        if (! config('app.key')) $configProblems[] = 'APP_KEY is missing';
        $checks['configuration']=['status'=>count($configProblems)?'warning':'ok','message'=>count($configProblems)?implode('; ', $configProblems).'.':'Application key is configured and debug mode is disabled.'];
        $free=@disk_free_space(base_path());
        $critical=collect($checks)->contains(fn($c)=>$c['status']==='critical');
        $warning=collect($checks)->contains(fn($c)=>$c['status']==='warning');
        $status=$critical?'critical':($warning?'warning':'healthy');
        $id=DB::table('system_health_snapshots')->insertGetId(['overall_status'=>$status,'checks'=>json_encode($checks),'free_disk_bytes'=>$free===false?null:(int)$free,'failed_jobs'=>$failed,'recorded_by'=>$request->user()->id,'checked_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'System readiness checks completed.','snapshot'=>DB::table('system_health_snapshots')->find($id)],201);
    }

    public function releases()
    {
        return response()->json(DB::table('deployment_releases')->orderByDesc('id')->paginate(25));
    }

    public function storeRelease(Request $request)
    {
        $data=$request->validate(['version'=>'required|string|max:60','environment'=>['required',Rule::in(['local','staging','production'])],'release_notes'=>'nullable|string|max:5000','status'=>['required',Rule::in(['prepared','deployed'])]]);
        $id=DB::table('deployment_releases')->insertGetId($data+['deployed_by'=>$request->user()->id,'deployed_at'=>$data['status']==='deployed'?now():null,'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Release record saved. This records deployment activity; it does not deploy application files.','release'=>DB::table('deployment_releases')->find($id)],201);
    }

    public function recoveryDrills()
    {
        return response()->json(DB::table('disaster_recovery_drills')->orderByDesc('id')->paginate(25));
    }

    public function storeRecoveryDrill(Request $request)
    {
        $data=$request->validate(['drill_reference'=>'required|string|max:50','backup_run_id'=>'nullable|integer|exists:backup_runs,id','environment'=>['required',Rule::in(['staging','isolated_test'])],'result'=>['required',Rule::in(['planned','passed','failed'])],'started_at'=>'nullable|date','completed_at'=>'nullable|date|after_or_equal:started_at','recovery_minutes'=>'nullable|integer|min:0|max:100000','findings'=>'nullable|string|max:5000','follow_up_actions'=>'nullable|string|max:5000']);
        $id=DB::table('disaster_recovery_drills')->insertGetId($data+['tested_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Recovery drill record saved. Run restoration only in an isolated test environment.','drill'=>DB::table('disaster_recovery_drills')->find($id)],201);
    }

    public function securityEvents(Request $request)
    {
        return response()->json(DB::table('security_events')->when($request->filled('event_type'),fn($q)=>$q->where('event_type',$request->string('event_type')))->orderByDesc('id')->paginate(50));
    }
}

