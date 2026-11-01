<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AccountingAccount extends Model { protected $guarded=[]; public function journalLines(){return $this->hasMany(JournalEntryLine::class,'account_id');} }
