<?php

declare(strict_types=1);

namespace App\Support;

enum DelistedReason: string
{
    case PageRemoved = 'page_removed';
    case PasswordProtected = 'password_protected';
    case NoDownloads = 'no_downloads';
    case Blocked = 'blocked';
}
