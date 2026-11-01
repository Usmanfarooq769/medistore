<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Expense extends Model { protected $guarded=[]; protected $casts=['expense_date'=>'date','amount'=>'decimal:2']; public function branch(){return $this->belongsTo(Branch::class);} }
