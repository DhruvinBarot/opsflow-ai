<?php

namespace App\Enums;

enum AuditAction: string
{
    case RequestCreated = 'request_created';
    case RequestUpdated = 'request_updated';
    case RequestSubmitted = 'request_submitted';
    case RequestAssigned = 'request_assigned';
    case RequestApproved = 'request_approved';
    case RequestRejected = 'request_rejected';
    case StatusChanged = 'status_changed';
    case RequestCompleted = 'request_completed';
    case CommentAdded = 'comment_added';
    case AiAnalysisCompleted = 'ai_analysis_completed';
    case AiAnalysisFailed = 'ai_analysis_failed';

    /**
     * The audit action recorded for a workflow transition into the given status.
     */
    public static function forTransitionTo(RequestStatus $status): self
    {
        return match ($status) {
            RequestStatus::Submitted => self::RequestSubmitted,
            RequestStatus::Approved => self::RequestApproved,
            RequestStatus::Rejected => self::RequestRejected,
            RequestStatus::Completed => self::RequestCompleted,
            default => self::StatusChanged,
        };
    }
}
