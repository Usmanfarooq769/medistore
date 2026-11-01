<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JournalEntry extends Model { protected $guarded=[]; protected $casts=['entry_date'=>'date','posted_at'=>'datetime']; public function lines(){return $this->hasMany(JournalEntryLine::class);} }
