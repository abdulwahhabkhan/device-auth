<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Enums;

/**
 * Why the browser is asked to sign a challenge. Each purpose keeps its own
 * challenge in the session, so ceremonies can't be replayed across flows.
 */
enum CeremonyPurpose: string
{
    case Register = 'register';
    case Verify = 'verify';
    case Confirm = 'confirm';
}
