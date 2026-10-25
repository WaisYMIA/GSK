<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\ContactMessageReceived;
use App\Support\Access\AccessService;
use App\Support\Access\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ContactController extends Controller
{
    public function create()
    {
        return view('public.contact');
    }

    public function store(Request $request, AccessService $access, AuditLogger $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'topic' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ]);
        $done = redirect()->route('contact')->with('status', 'Thank you. Your message has been received.');

        // Honeypot: a field real visitors never see. Bots that fill it get the same answer and nothing is stored.
        if ($request->filled('website')) {
            return $done;
        }

        $message = ContactMessage::query()->create($data);
        $audit->record('contact.received', null, $message);

        try {
            $this->notify($message, $access);
        } catch (Throwable $e) {
            // The message is safely stored. A mail failure must not lose it or break the page. No content is logged.
            Log::warning('Contact notification could not be sent.', ['message_id' => $message->id]);
        }

        return $done;
    }

    private function notify(ContactMessage $message, AccessService $access): void
    {
        $body = config('gsk.contact.include_message_body')
            ? ['name' => $message->name, 'email' => $message->email, 'topic' => $message->topic, 'message' => $message->message]
            : null;
        $notification = new ContactMessageReceived($message->id, $body);

        $recipients = User::query()->where('status', 'active')->where('account_type', 'staff')->get()
            ->filter(fn (User $u) => $access->can($u, 'contact.messages.view'));
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, $notification);
        }
        $pool = config('gsk.contact.pool_email');
        if (is_string($pool) && $pool !== '') {
            Notification::route('mail', $pool)->notify($notification);
        }
    }
}
