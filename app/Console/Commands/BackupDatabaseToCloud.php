<?php
namespace App\Console\Commands;
use App\Models\BackupRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Services\TenantContext;
use Symfony\Component\Process\Process;

class BackupDatabaseToCloud extends Command
{
 protected $signature='store:backup-cloud'; protected $description='Create an encrypted database backup and upload it to configured cloud object storage.';
 public function handle(): int
 {
  $tenantId=app(TenantContext::class)->id();$tmp=storage_path('app/tenant-tmp/'.($tenantId ? $tenantId.'/' : 'single/').'backup-'.now()->format('Ymd-His').'.sql');$dir=dirname($tmp);if(!is_dir($dir))mkdir($dir,0700,true);
  try{
   $connection=config('database.default');$cfg=config('database.connections.'.$connection);
   if($connection==='sqlite'){$source=$cfg['database'];$source=str_starts_with($source,'/')?$source:base_path($source);if(!is_file($source))throw new \RuntimeException('SQLite database file not found.');try{\Illuminate\Support\Facades\DB::connection()->getPdo()->exec('PRAGMA wal_checkpoint(FULL)');}catch(\Throwable $ignored){}if(!copy($source,$tmp))throw new \RuntimeException('Could not copy SQLite database.');@chmod($tmp,0600);}
   elseif(($cfg['driver'] ?? $connection)==='mysql'){$args=['mysqldump','--single-transaction','--quick','--skip-lock-tables','-h',(string)$cfg['host'],'-P',(string)$cfg['port'],'-u',(string)$cfg['username'],(string)$cfg['database']];$process=new Process($args,base_path(),['MYSQL_PWD'=>(string)$cfg['password']]);$process->setTimeout(900);$process->run();if(!$process->isSuccessful())throw new \RuntimeException('mysqldump failed: '.$process->getErrorOutput());file_put_contents($tmp,$process->getOutput());@chmod($tmp,0600);}
   else throw new \RuntimeException('Cloud backup currently supports MySQL and SQLite connections.');
   $plain=file_get_contents($tmp);$key=hash('sha256',(string)config('app.key'),true);$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($plain,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);if($cipher===false)throw new \RuntimeException('Database backup encryption failed.');
   $payload="MEDISTORE-ENC-1\0".$iv.$tag.$cipher;$disk=env('BACKUP_DISK','s3');$path=($tenantId ? 'tenants/'.$tenantId.'/' : '').'medistore-backups/'.now()->format('Y/m').'/'.basename($tmp).'.enc';
   $uploaded=Storage::disk($disk)->put($path,$payload,['visibility'=>'private']);if(!$uploaded)throw new \RuntimeException('Cloud storage did not confirm the backup upload. Check S3 credentials, bucket and adapter.');$size=strlen($payload);
   BackupRun::create(['disk'=>$disk,'path'=>$path,'size_bytes'=>$size,'status'=>'success','message'=>'Encrypted database backup uploaded.','completed_at'=>now()]);$this->info('Encrypted backup uploaded: '.$path);return self::SUCCESS;
  }catch(\Throwable $e){BackupRun::create(['disk'=>env('BACKUP_DISK','s3'),'status'=>'failed','message'=>mb_substr($e->getMessage(),0,6000),'completed_at'=>now()]);$this->error($e->getMessage());try{$emails=collect(preg_split('/[,;]+/',(string)setting('daily_report_email','')))->merge(User::where('role','admin')->pluck('email'))->filter(fn($v)=>filter_var(trim((string)$v),FILTER_VALIDATE_EMAIL))->unique();if($emails->isNotEmpty())Mail::raw('MediStore cloud database backup FAILED at '.now().'\n\n'.$e->getMessage(),fn($m)=>$m->to($emails->all())->subject('MediStore backup failure'));}catch(\Throwable $mailError){}return self::FAILURE;}finally{if(is_file($tmp))@unlink($tmp);}
 }
}
