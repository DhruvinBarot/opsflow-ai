<?php

namespace App\Enums;

/**
 * Teams the analyzer may suggest. A closed list, so model output can never invent a team.
 */
enum Team: string
{
    case Platform = 'platform';
    case Security = 'security';
    case Identity = 'identity';
    case Data = 'data';
    case Compliance = 'compliance';
    case Finance = 'finance';
    case CustomerSuccess = 'customer_success';
    case Operations = 'operations';

    public static function forCategory(RequestCategory $category): self
    {
        return match ($category) {
            RequestCategory::Infrastructure, RequestCategory::Incident => self::Platform,
            RequestCategory::Access => self::Identity,
            RequestCategory::Data => self::Data,
            RequestCategory::Compliance => self::Compliance,
            RequestCategory::Billing => self::Finance,
            RequestCategory::CustomerEscalation => self::CustomerSuccess,
            RequestCategory::General => self::Operations,
        };
    }
}
