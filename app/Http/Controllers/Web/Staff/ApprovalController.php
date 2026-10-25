<?php

namespace App\Http\Controllers\Web\Staff;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Support\Access\AccessDenied;
use App\Support\Access\AccessService;
use App\Support\Access\ApprovalService;
use Illuminate\Http\Request;

// Inbox for independent approvers. The approval service enforces independence. This screen only lists and forwards.
class ApprovalController extends Controller
{
    public function index(Request $request, AccessService $access)
    {
        $user = $request->user();
        abort_unless($user->isStaff(), 403);
        // Read the whole map: action types contain dots, which config()'s dot notation cannot address.
        $actions = (array) config('gsk.approvals.actions', []);
        $pending = ApprovalRequest::query()->where('status', 'pending')->orderBy('id')->with('requester')->get()
            ->filter(function (ApprovalRequest $r) use ($user, $access, $actions) {
                $key = $actions[$r->action_type] ?? null;

                return is_string($key) && $access->can($user, $key)
                    && $r->requested_by !== $user->id
                    && $r->requester_staff_profile_id !== $user->staff_profile_id;
            });

        return view('staff.approvals.index', ['requests' => $pending]);
    }

    public function decide(Request $request, int $id, string $decision, ApprovalService $approvals)
    {
        abort_unless(in_array($decision, ['approve', 'reject'], true), 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);
        $approval = ApprovalRequest::query()->findOrFail($id);
        try {
            $decision === 'approve'
                ? $approvals->approve($request->user(), $approval, $data['reason'])
                : $approvals->reject($request->user(), $approval, $data['reason']);
        } catch (AccessDenied $e) {
            return back()->withErrors(['approval' => 'This request cannot be decided by you (' . $e->reasonCode . ').']);
        }

        return redirect()->route('staff.approvals.index')->with('status', $decision === 'approve' ? 'Approved.' : 'Rejected.');
    }
}
