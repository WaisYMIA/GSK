<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalDecision extends Model
{
    protected $fillable = ['approval_request_id', 'decided_by', 'decider_staff_profile_id', 'decision', 'reason'];

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }
}
