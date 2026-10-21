<?php

namespace App\Modules\Activities\Enums;

/**
 * The five activity types of ENT-Activity.type. Each is implemented by an
 * ActivityType that its module registers with Support\ActivityTypes
 * (ARCH-004); the wizard offers only registered types.
 */
enum ActivityKind: string
{
    case Quiz = 'quiz';
    case Poll = 'poll';
    case TopicSelection = 'topic_selection';
    case TopicProposal = 'topic_proposal';
    case SlotBooking = 'slot_booking';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $kind): string => $kind->value, self::cases());
    }
}
