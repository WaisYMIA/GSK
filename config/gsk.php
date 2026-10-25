<?php

return [
    // Approvals that need an independent person. Fail closed: disabled unless explicitly enabled.
    // Do not enable in production until GSK has appointed real independent approvers (open decision).
    'approvals' => [
        'enabled' => (bool) env('GSK_APPROVALS_ENABLED', false),
        'expiry_hours' => 72,
        // action type => permission key an approver must hold
        'actions' => [
            'role.privileged_grant' => 'roles.approve_privileged_grant',
            'mfa.reset_privileged' => 'roles.approve_privileged_grant',
            'content.sensitive_publish' => 'content.sensitive_claims.approve',
        ],
    ],

    // Break-glass is time-limited, reasoned and reviewed. It can only grant the keys listed here.
    // Welfare and candidate-document permissions are intentionally NOT allowed by default.
    'break_glass' => [
        'max_minutes' => 120,
        'min_reason_length' => 20,
        'allowed_permissions' => ['audit.view', 'settings.manage', 'users.suspend', 'users.revoke_sessions'],
        'alert_recipients' => [], // unresolved: GSK must name recipients. Empty means alerts are recorded as undelivered.
    ],

    'content' => [
        'sensitive_areas' => ['none', 'privacy', 'safeguarding', 'donation', 'legal', 'employment'],
        'page_kinds' => ['page', 'policy', 'programme'],
        'max_blocks' => 200,
        'max_images_per_post' => 3,
        // Slugs that would clash with application routes. A page cannot use them.
        'reserved_slugs' => ['home', 'login', 'logout', 'register', 'forgot-password', 'reset-password', 'two-factor-challenge',
            'confirm-password', 'email', 'user', 'account', 'staff', 'api', 'up', 'storage', 'sitemap', 'robots', 'contact',
            'jobs', 'programmes', 'news', 'admin', 'vendor', 'build', 'assets'],
    ],

    'media' => [
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        'max_kilobytes' => 2048,
        'max_dimension' => 2000,
        'max_pixels' => 25000000,
        // Uploads are re-encoded with PHP's GD extension (this also drops photo metadata such as GPS).
        // If GD is missing the upload is refused. There is no unprocessed fallback.
        'require_processing' => true,
    ],

    'contact' => [
        // Shared mailbox that gets a copy of every contact notice. UNCONFIRMED address: the owner wrote "info@gsk.co,ke".
        'pool_email' => env('GSK_CONTACT_POOL_EMAIL'),
        // False means notices carry no message text, only a link to sign in. Decision needed from GSK.
        'include_message_body' => (bool) env('GSK_CONTACT_EMAIL_INCLUDES_MESSAGE', false),
    ],

    'invitations' => [
        'expiry_hours' => 72,
        'min_password_length' => 12,
    ],

    // Routes that are intentionally reachable without authentication. Every other route must require auth.
    'public_routes' => [
        '/',
        'up',
        'api/v1/health',
        'api/v1/welfare-cases',
        'storage/{path}',
        // Public site (Task 2). Each entry is a deliberate decision.
        'programmes',
        'programmes/{slug}',
        'jobs',
        'jobs/{slug}',
        'news',
        'news/{slug}',
        'contact',
        'sitemap.xml',
        'robots.txt',
        '{slug}',
        // Guest-facing authentication routes (Fortify and the invitation page).
        'login',
        'logout',
        'forgot-password',
        'reset-password',
        'reset-password/{token}',
        'two-factor-challenge',
        'staff/invitation/{token}',
    ],
];
