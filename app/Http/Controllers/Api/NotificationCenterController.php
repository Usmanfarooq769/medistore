<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationCenterController extends Controller
{
    public function index(Request $request)
    {
        $query=DB::table('user_notifications')->where('user_id',$request->user()->id)->orderByDesc('id');
        return response()->json(['unread_count'=>(clone $query)->whereNull('read_at')->count(),'notifications'=>$query->paginate(25)]);
    }


    public function storeAnnouncement(Request $request)
    {
        $data=$request->validate(['title'=>'required|string|max:180','body'=>'nullable|string|max:3000','type'=>'nullable|string|max:60','branch_id'=>'nullable|integer|exists:branches,id','user_id'=>'nullable|integer|exists:users,id','data'=>'nullable|array']);
        $users=DB::table('users')->where('is_active',true)->when($request->filled('user_id'),fn($q)=>$q->where('id',$data['user_id']))->when(!$request->filled('user_id') && $request->filled('branch_id'),fn($q)=>$q->where('branch_id',$data['branch_id']))->pluck('id');
        if($users->isEmpty()) return response()->json(['message'=>'No active users matched this announcement.'],422);
        $rows=$users->map(fn($id)=>['user_id'=>$id,'branch_id'=>$data['branch_id']??null,'type'=>$data['type']??'announcement','title'=>$data['title'],'body'=>$data['body']??null,'data'=>isset($data['data'])?json_encode($data['data']):null,'created_at'=>now(),'updated_at'=>now()])->all();
        foreach(array_chunk($rows,500) as $chunk) DB::table('user_notifications')->insert($chunk);
        return response()->json(['message'=>'Announcement sent to '.$users->count().' user(s).','recipients'=>$users->count()],201);
    }

    public function markRead(Request $request, int $notification)
    {
        $updated=DB::table('user_notifications')->where('id',$notification)->where('user_id',$request->user()->id)->whereNull('read_at')->update(['read_at'=>now(),'updated_at'=>now()]);
        abort_unless($updated,404);
        return response()->json(['message'=>'Notification marked as read.']);
    }

    public function markAllRead(Request $request)
    {
        $count=DB::table('user_notifications')->where('user_id',$request->user()->id)->whereNull('read_at')->update(['read_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Notifications marked as read.','updated'=>$count]);
    }
}
