<?php

declare(strict_types=1);

namespace App\Domains\Identity\Enums;

/**
 * the system role names (System Roles) within each tenant.
 * used instead of string literals everywhere (Seeders, fix scripts,
 * Policies, front-end via API) to prevent silent typos.
 */

enum RoleName: string
{
    case TenantAdmin = 'Tenant Admin';
    case Proctor = 'Proctor';
    case TechnicalEvaluator = 'Technical Evaluator';
    case Candidate = 'Candidate';
}