<?php

namespace App\Enums;

enum RequestCategory: string
{
    case Access = 'access';
    case Infrastructure = 'infrastructure';
    case CustomerEscalation = 'customer_escalation';
    case Data = 'data';
    case Compliance = 'compliance';
    case Billing = 'billing';
    case Incident = 'incident';
    case General = 'general';
}
