<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class Donation extends Model { protected $fillable=['donor_user_id','donor_name','donor_email','amount_minor','currency','frequency','provider','provider_reference','status','mandate_reference','consented_at','cancelled_at']; protected function casts():array{return ['consented_at'=>'datetime','cancelled_at'=>'datetime'];} }
