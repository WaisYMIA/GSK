<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller; use App\Models\WelfareCase; use Illuminate\Http\Request; use Illuminate\Support\Str; use Illuminate\Support\Facades\Hash;
class WelfareCaseController extends Controller {
 public function store(Request $request) {
  $data=$request->validate(['is_anonymous'=>'required|boolean','category'=>'required|string|in:employment_abuse,contract,harassment,trafficking_risk,health_safety,other','severity'=>'required|string|in:standard,high,urgent','description'=>'required|string|min:20|max:10000','contact_details'=>'nullable|string|max:2000']);
  $token=Str::random(64); $case=WelfareCase::create(['public_reference'=>(string) Str::uuid(),'reporter_user_id'=>($data['is_anonymous'] ? null : $request->user()?->id),'is_anonymous'=>$data['is_anonymous'],'category'=>$data['category'],'severity'=>$data['severity'],'status'=>'received','description'=>$data['description'],'encrypted_contact_details'=>$data['contact_details']??null,'followup_token_hash'=>Hash::make($token)]);
  $case->events()->create(['actor_user_id'=>$request->user()?->id,'event_type'=>'case_received','note'=>'Initial report received']);
  return response()->json(['reference'=>$case->public_reference,'followup_credential'=>$token,'message'=>'Save the follow-up credential securely. It cannot be recovered if lost.'],201);
 }
}
