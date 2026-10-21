<?php

namespace App\Modules\Activities\Support;

use App\Modules\Activities\Enums\SubmissionStatus;
use DateTimeInterface;

/**
 * One row of the submissions overview (UC-028), produced by an
 * ActivityType. Points are exact decimal strings (DATA-003); converting
 * them to the organisation's grading scale is up to the type's own pages.
 */
final readonly class Submission
{
    public function __construct(
        public string $id,
        public string $participant,
        public DateTimeInterface $submittedAt,
        public SubmissionStatus $status,
        public ?string $earnedPoints = null,
        public ?string $maxPoints = null,
        public ?string $url = null,
    ) {}

    /**
     * @return array{id: string, participant: string, submitted_at: string, status: string, earned_points: string|null, max_points: string|null, url: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'participant' => $this->participant,
            'submitted_at' => $this->submittedAt->format(DateTimeInterface::ATOM),
            'status' => $this->status->value,
            'earned_points' => $this->earnedPoints,
            'max_points' => $this->maxPoints,
            'url' => $this->url,
        ];
    }
}
