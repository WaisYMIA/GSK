<?php

namespace App\Http\Controllers\Web\Staff;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Support\Access\AccessService;
use App\Support\Access\AuditLogger;
use Illuminate\Http\Request;

class ContactMessageController extends Controller
{
    private const PERMISSION = 'contact.messages.view';

    public function index(Request $request, AccessService $access)
    {
        abort_unless($access->can($request->user(), self::PERMISSION), 403);

        return view('staff.contact.index', ['messages' => ContactMessage::query()->orderByDesc('id')->paginate(25)]);
    }

    public function show(Request $request, int $id, AccessService $access, AuditLogger $audit)
    {
        abort_unless($access->can($request->user(), self::PERMISSION), 403);
        $message = ContactMessage::query()->findOrFail($id);
        $audit->record('contact.message_read', $request->user(), $message);
        if ($message->status === 'new') {
            $message->forceFill(['status' => 'read'])->save();
        }

        return view('staff.contact.show', ['message' => $message]);
    }
}
