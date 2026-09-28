<?php

namespace App\Domain\Tickets;

enum TicketStatus: string
{
    case New = 'new';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case OnHold = 'on_hold';
    case Reopened = 'reopened';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::New => $target === self::Assigned,
            self::Assigned => in_array($target, [self::New, self::InProgress], true),
            self::InProgress => $target === self::Resolved,
            self::Resolved => $target === self::Closed,
            self::Closed => false,
            self::OnHold, self::Reopened => false,
        };
    }
}
