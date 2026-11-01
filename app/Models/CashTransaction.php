<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CashTransaction extends Model
{
    protected $fillable=['cashier_session_id','user_id','type','category','amount','note'];
    protected $casts=['amount'=>'decimal:2'];
}
