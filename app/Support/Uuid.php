<?php
declare(strict_types=1);

namespace LeadFlow\Support;

use Ramsey\Uuid\Uuid as RamseyUuid;

final class Uuid
{
    public static function v4(): string
    {
        return RamseyUuid::uuid4()->toString();
    }

    public static function leadId(): string
    {
        return 'LD-' . strtoupper(bin2hex(random_bytes(6)));
    }
}
