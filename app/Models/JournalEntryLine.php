<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JournalEntryLine extends Model { protected $guarded=[]; protected $casts=['debit'=>'decimal:2','credit'=>'decimal:2']; public function account(){return $this->belongsTo(AccountingAccount::class,'account_id');} public function journal(){return $this->belongsTo(JournalEntry::class,'journal_entry_id');} }
