<?php

namespace App\Support\Access;

use App\Models\User;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

// Use for reads of sensitive records (welfare cases, documents). Checks the policy, requires a stated reason,
// and writes an audit event for both allowed and denied attempts. Never logs the record contents.
class SensitiveAccess
{
    public function __construct(private readonly GateContract $gate, private readonly AuditLogger $audit)
    {
    }

    public function authorizeRead(User $user, string $ability, Model $record, string $reason): bool
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reason is required for sensitive reads.');
        }

        $allowed = $this->gate->forUser($user)->allows($ability, $record);
        $this->audit->record(
            'access.sensitive_read',
            $user,
            $record,
            $allowed ? 'success' : 'denied',
            $reason,
            ['ability' => $ability],
        );

        return $allowed;
    }
}
