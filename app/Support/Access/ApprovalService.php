<?php

namespace App\Support\Access;

use App\Models\ApprovalDecision;
use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

// Independent approval. Independence is judged on the staff profile (the real person), never only the account.
// If independence cannot be established the request is blocked and nothing can approve it (fail closed).
class ApprovalService
{
    public function __construct(private readonly AccessService $access, private readonly AuditLogger $audit)
    {
    }

    public function request(User $requester, string $actionType, ?Model $subject, string $reason, array $payload = []): ApprovalRequest
    {
        $permissionKey = $this->permissionKeyFor($actionType);
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reason is required.');
        }

        $blockReason = null;
        if (! config('gsk.approvals.enabled')) {
            $blockReason = 'approvals_disabled';
        } elseif ($requester->staff_profile_id === null) {
            $blockReason = 'requester_has_no_staff_profile';
        } elseif (! $this->independentApproverExists($permissionKey, $requester)) {
            $blockReason = 'no_independent_approver';
        }

        $request = ApprovalRequest::query()->create([
            'action_type' => $actionType,
            'subject_type' => $subject !== null ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'requested_by' => $requester->id,
            'requester_staff_profile_id' => $requester->staff_profile_id,
            'status' => $blockReason === null ? 'pending' : 'blocked',
            'reason' => $reason,
            'payload' => $payload,
            'expires_at' => now()->addHours((int) config('gsk.approvals.expiry_hours', 72)),
        ]);

        $this->audit->record(
            'approval.requested',
            $requester,
            $request,
            $blockReason === null ? 'success' : 'blocked',
            $reason,
            ['action_type' => $actionType, 'block_reason' => $blockReason],
        );

        return $request;
    }

    public function approve(User $approver, ApprovalRequest $request, string $reason): ApprovalRequest
    {
        return $this->decide($approver, $request, 'approve', $reason);
    }

    public function reject(User $approver, ApprovalRequest $request, string $reason): ApprovalRequest
    {
        return $this->decide($approver, $request, 'reject', $reason);
    }

    public function independentApproverExists(string $permissionKey, User $requester): bool
    {
        if ($requester->staff_profile_id === null) {
            return false;
        }

        $candidates = User::query()
            ->where('status', 'active')
            ->whereNotNull('staff_profile_id')
            ->where('staff_profile_id', '!=', $requester->staff_profile_id)
            ->whereHas('staffProfile', function ($q) {
                $q->where('status', 'active');
            })
            ->cursor();

        foreach ($candidates as $candidate) {
            if ($this->access->can($candidate, $permissionKey)) {
                return true;
            }
        }

        return false;
    }

    private function decide(User $approver, ApprovalRequest $request, string $decision, string $reason): ApprovalRequest
    {
        try {
            $this->assertCanDecide($approver, $request);
        } catch (AccessDenied $e) {
            $this->audit->record('approval.decision_denied', $approver, $request, 'denied', $reason, ['reason_code' => $e->reasonCode]);
            throw $e;
        }

        return DB::transaction(function () use ($approver, $request, $decision, $reason) {
            ApprovalDecision::query()->create([
                'approval_request_id' => $request->id,
                'decided_by' => $approver->id,
                'decider_staff_profile_id' => $approver->staff_profile_id,
                'decision' => $decision,
                'reason' => $reason,
            ]);
            $request->forceFill([
                'status' => $decision === 'approve' ? 'approved' : 'rejected',
                'decided_at' => now(),
            ])->save();
            $this->audit->record($decision === 'approve' ? 'approval.approved' : 'approval.rejected', $approver, $request, 'success', $reason, ['action_type' => $request->action_type]);

            return $request->refresh();
        });
    }

    private function assertCanDecide(User $approver, ApprovalRequest $request): void
    {
        if (! config('gsk.approvals.enabled')) {
            throw new AccessDenied('approvals_disabled');
        }
        if ($request->status !== 'pending') {
            throw new AccessDenied('request_not_pending');
        }
        if ($request->expires_at !== null && $request->expires_at->isPast()) {
            $request->forceFill(['status' => 'expired'])->save();
            throw new AccessDenied('request_expired');
        }
        if (! $approver->isActive()) {
            throw new AccessDenied('approver_inactive');
        }
        if ($approver->staff_profile_id === null || $request->requester_staff_profile_id === null) {
            throw new AccessDenied('independence_not_established');
        }
        if ($approver->staffProfile === null || ! $approver->staffProfile->isActive()) {
            throw new AccessDenied('approver_profile_inactive');
        }
        if ($approver->id === $request->requested_by || $approver->staff_profile_id === $request->requester_staff_profile_id) {
            throw new AccessDenied('same_person');
        }
        if (! $this->access->can($approver, $this->permissionKeyFor($request->action_type))) {
            throw new AccessDenied('approver_not_authorised');
        }
        $already = ApprovalDecision::query()
            ->where('approval_request_id', $request->id)
            ->where('decider_staff_profile_id', $approver->staff_profile_id)
            ->exists();
        if ($already) {
            throw new AccessDenied('already_decided');
        }
    }

    private function permissionKeyFor(string $actionType): string
    {
        // Read the whole map: action types contain dots, which config()'s dot notation cannot address.
        $key = ((array) config('gsk.approvals.actions', []))[$actionType] ?? null;
        if (! is_string($key) || $key === '') {
            throw new InvalidArgumentException("Unknown approval action type: {$actionType}");
        }

        return $key;
    }
}
