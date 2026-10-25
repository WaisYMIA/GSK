<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

// Append-only at application level: model-level update and delete are refused.
// This does NOT stop raw SQL or query-builder writes. See docs/AUDIT_PROTECTION.md for the database-level controls
// that must be applied by the operator, and do not describe the audit trail as tamper-proof.
class AuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_user_id', 'actor_staff_profile_id', 'action', 'outcome', 'reason', 'subject_type', 'subject_id',
        'scope_type', 'scope_id', 'request_id', 'ip_address', 'metadata', 'created_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Audit events are append-only and cannot be updated.');
        });
        static::deleting(function () {
            throw new LogicException('Audit events are append-only and cannot be deleted.');
        });
    }
}
