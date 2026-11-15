<?php

declare(strict_types=1);

return [

    // Ceremony failures (JSON "message").
    'challenge_expired' => 'The request expired. Try again.',
    'unknown_key' => 'This key is not registered for this application.',
    'not_approved' => 'This device is not approved.',
    'too_many_pending' => 'You already have requests waiting for approval. Ask your administrator to review them first.',
    'key_not_created' => 'The device key could not be created: :reason',
    'not_verified' => 'The device could not be verified: :reason',
    'confirm_same_device' => 'Confirm with the same device you signed in on.',
    'platform_mismatch' => 'This key was approved for a different device.',

    // Middleware.
    'verify_required' => 'Verify this device to continue.',
    'confirm_required' => 'Confirm this device to continue.',

    // Review.
    'already_approved' => 'This device is already approved.',
    'only_pending_rejectable' => 'Only pending requests can be rejected.',
    'only_active_revocable' => 'Only approved devices can be revoked.',
    'cannot_revoke_current' => 'You can’t revoke the device you’re using. Revoke it from another approved device.',
    'reject_reason_required' => 'Give a reason. The person who asked will see it.',
    'approved' => ':name approved. It can be used right away.',
    'rejected' => ':name rejected.',
    'revoked' => ':name revoked. It will be signed out on its next request.',
    'approved_from_cli' => 'Approved from the command line.',
    'revoked_from_cli' => 'Revoked from the command line.',

    'status' => [
        'pending' => 'Waiting for approval',
        'active' => 'Approved',
        'rejected' => 'Rejected',
        'revoked' => 'Revoked',
    ],

    // Notifications.
    'mail' => [
        'request_subject' => 'Device access request :reference: :name',
        'request_intro' => ':user asked for access from a new device.',
        'request_someone' => 'A user',
        'request_device' => 'Device: :name (:description)',
        'request_unknown_device' => 'unknown device',
        'request_ip' => 'IP address: :ip',
        'request_reason' => 'Reason: :reason',
        'request_action' => 'Review request',
        'request_summary' => 'New device access request: :name',

        'approved_subject' => 'Device approved: :name',
        'approved_line' => 'Your device ":name" (:reference) was approved.',
        'approved_next' => 'Open the application on that device and choose Verify device to continue.',
        'approved_action' => 'Open application',
        'reviewed_subject' => 'Device :status: :name',
        'reviewed_line' => 'Your device ":name" (:reference) is :status.',
        'reviewed_reason' => 'Reason: :reason',
        'reviewed_contact' => 'Contact your administrator if you need access from this device.',
        'reviewed_summary' => 'Device :name is :status.',
    ],

];
