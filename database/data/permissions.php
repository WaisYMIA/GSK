<?php

// Permission catalogue: 21 Task 1 keys plus 11 Task 2 keys (32). Versioned reference data, seeded idempotently.
// Role-to-permission mappings are NOT defined here and require separate GSK approval.
// Deferred modules (training, reviews) are intentionally absent.
return [
    ['key' => 'organisations.view', 'module' => 'organisations', 'description' => 'View organisations within scope'],
    ['key' => 'organisations.members.manage', 'module' => 'organisations', 'description' => 'Manage organisation members'],
    ['key' => 'partners.scopes.manage', 'module' => 'organisations', 'description' => 'Set partner organisation, programme and function scope'],
    ['key' => 'reports.view', 'module' => 'reporting', 'description' => 'View reports within scope'],
    ['key' => 'reports.export', 'module' => 'reporting', 'description' => 'Export reports (disabled by default, audited)'],
    ['key' => 'welfare.cases.view_assigned', 'module' => 'welfare', 'description' => 'View welfare cases assigned to the user (case scope)'],
    ['key' => 'welfare.cases.view_all_authorised', 'module' => 'welfare', 'description' => 'Safeguarding oversight of all authorised cases (every read audited)'],
    ['key' => 'users.view', 'module' => 'identity', 'description' => 'View user accounts'],
    ['key' => 'users.manage', 'module' => 'identity', 'description' => 'Manage user accounts and record staff identity checks'],
    ['key' => 'users.invite', 'module' => 'identity', 'description' => 'Invite a staff account'],
    ['key' => 'users.suspend', 'module' => 'identity', 'description' => 'Suspend and reactivate accounts'],
    ['key' => 'users.revoke_sessions', 'module' => 'identity', 'description' => 'Revoke a user\'s sessions'],
    ['key' => 'users.reset_mfa', 'module' => 'identity', 'description' => 'Reset another user\'s MFA (audited; privileged accounts need independent approval)'],
    ['key' => 'roles.view', 'module' => 'identity', 'description' => 'View roles'],
    ['key' => 'roles.manage', 'module' => 'identity', 'description' => 'Define roles and role permissions'],
    ['key' => 'roles.assign', 'module' => 'identity', 'description' => 'Assign and revoke roles within delegated scope'],
    ['key' => 'roles.approve_privileged_grant', 'module' => 'identity', 'description' => 'Independently approve a privileged role grant'],
    ['key' => 'access.break_glass.activate', 'module' => 'access', 'description' => 'Activate time-limited emergency access'],
    ['key' => 'access.break_glass.review', 'module' => 'access', 'description' => 'Review break-glass use afterwards'],
    ['key' => 'audit.view', 'module' => 'audit', 'description' => 'View audit events within scope'],
    ['key' => 'settings.manage', 'module' => 'settings', 'description' => 'Manage settings (secrets never shown after entry)'],
    ['key' => 'content.pages.view', 'module' => 'content', 'description' => 'View CMS pages in the staff area'],
    ['key' => 'content.pages.create', 'module' => 'content', 'description' => 'Create CMS pages'],
    ['key' => 'content.pages.edit', 'module' => 'content', 'description' => 'Edit CMS pages (creates a new draft version)'],
    ['key' => 'content.pages.publish', 'module' => 'content', 'description' => 'Publish and archive CMS pages (never your own draft)'],
    ['key' => 'content.posts.view', 'module' => 'content', 'description' => 'View news posts in the staff area'],
    ['key' => 'content.posts.create', 'module' => 'content', 'description' => 'Create news posts'],
    ['key' => 'content.posts.edit', 'module' => 'content', 'description' => 'Edit news posts (creates a new draft version)'],
    ['key' => 'content.posts.publish', 'module' => 'content', 'description' => 'Publish and archive news posts (never your own draft)'],
    ['key' => 'content.posts.manage_media', 'module' => 'content', 'description' => 'Upload and retire media'],
    ['key' => 'content.sensitive_claims.approve', 'module' => 'content', 'description' => 'Independently approve privacy, safeguarding, donation and legal content'],
    ['key' => 'contact.messages.view', 'module' => 'content', 'description' => 'View public contact messages (staff only)'],
];
