<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class CandidateProfile extends Model { protected $fillable=['user_id','phone','county','skills_summary','profile_status']; public function user(){return $this->belongsTo(User::class);} }
