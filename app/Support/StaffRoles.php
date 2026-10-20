<?php

namespace App\Support;

final class StaffRoles
{
    public const REGISTRARS = ['registrar', 'registrar_manager', 'registrar_officer'];

    public const CASHIERS = ['cashier', 'cashier_manager', 'cashier_officer'];

    public const AUDITED = ['admin', ...self::REGISTRARS, ...self::CASHIERS];

    public const STAFF = [...self::AUDITED, 'finance', 'developer', 'processing', 'team uhs'];
}
