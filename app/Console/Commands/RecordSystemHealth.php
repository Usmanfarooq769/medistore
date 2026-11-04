<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class RecordSystemHealth extends Command
{
    protected $signature = 'system:health-check';
    protected $description = 'Run non-destructive application readiness checks and save a health snapshot.';

    public function handle(): int
    {
        $checks=[];
        try { DB::select('SELECT 1'); $checks['database']=['status'=>'ok','message'=>'Database reachable.']; }
        catch(Throwable $e){ $checks['database']=['status'=>'critical','message'=>'Database connection failed.']; }
        try { $key='health:'.uniqid(); Cache::put($key,'ok',5); $ok=Cache::get($key)==='ok'; Cache::forget($key); $checks['cache']=['status'=>$ok?'ok':'warning','message'=>$ok?'Cache read/write successful.':'Cache unavailable.']; }
        catch(Throwable $e){ $checks['cache']=['status'=>'warning','message'=>'Cache unavailable.']; }
        try { $file=storage_path('app/.health-'.uniqid()); file_put_contents($file,'ok'); $ok=file_get_contents($file)==='ok'; @unlink($file); $checks['storage']=['status'=>$ok?'ok':'critical','message'=>$ok?'Storage writable.':'Storage write failed.']; }
        catch(Throwable $e){ $checks['storage']=['status'=>'critical','message'=>'Storage write failed.']; }
        $failed=Schema::hasTable('failed_jobs')?DB::table('failed_jobs')->count():0;
        $checks['queue']=['status'=>$failed?'warning':'ok','message'=>$failed?"{$failed} failed jobs need attention.":'No failed jobs.'];
        $backup=DB::table('backup_runs')->where('status','success')->orderByDesc('completed_at')->first();
        $fresh=$backup && $backup->completed_at && now()->diffInHours($backup->completed_at)<=36;
        $checks['backup']=['status'=>$fresh?'ok':'warning','message'=>$fresh?'Recent successful backup found.':'No successful backup in the last 36 hours.'];
        $critical=collect($checks)->contains(fn($v)=>$v['status']==='critical'); $warning=collect($checks)->contains(fn($v)=>$v['status']==='warning');
        $status=$critical?'critical':($warning?'warning':'healthy');
        DB::table('system_health_snapshots')->insert(['overall_status'=>$status,'checks'=>json_encode($checks),'free_disk_bytes'=>($d=@disk_free_space(base_path()))===false?null:(int)$d,'failed_jobs'=>$failed,'recorded_by'=>null,'checked_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        $this->line("System status: {$status}");
        foreach($checks as $name=>$check) $this->line(strtoupper($name).': '.$check['status'].' — '.$check['message']);
        return $critical?self::FAILURE:self::SUCCESS;
    }
}
