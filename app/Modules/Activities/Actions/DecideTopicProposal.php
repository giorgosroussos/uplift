<?php

namespace App\Modules\Activities\Actions;

use App\Modules\Activities\Enums\TopicProposalStatus;
use App\Modules\Activities\Events\TopicProposalDecided;
use App\Modules\Activities\Exceptions\InvalidTopicProposalTransition;
use App\Modules\Activities\Models\TopicProposal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * UC-027 step 2: the owning teacher approves a pending proposal, with an
 * optional comment, or rejects it with a comment (required). A proposal
 * is decided once (pending -> approved | rejected); deciding it again is
 * refused and nothing changes. After the commit TopicProposalDecided lets
 * the Notifications module notify the student or the group (step 3).
 *
 * Who decides is checked by the caller (TopicProposalPolicy::decide,
 * OWN-009).
 */
class DecideTopicProposal
{
    public const int MAX_COMMENT = 5000;

    /**
     * @param  array<string, mixed>  $input  {decision: approve|reject, comment?: string}
     *
     * @throws ValidationException when the input is invalid or the proposal is already decided
     */
    public function handle(TopicProposal $proposal, array $input): TopicProposal
    {
        /** @var array{decision: string, comment?: string|null} $data */
        $data = Validator::make($input, [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'comment' => ['nullable', 'string', 'max:'.self::MAX_COMMENT, 'required_if:decision,reject'],
        ], [
            'comment.required_if' => (string) __('activities.proposals.decide.errors.comment_required'),
        ], [
            'decision' => (string) __('activities.proposals.attributes.decision'),
            'comment' => (string) __('activities.proposals.attributes.comment'),
        ])->validate();

        $to = $data['decision'] === 'approve' ? TopicProposalStatus::Approved : TopicProposalStatus::Rejected;
        $comment = trim((string) ($data['comment'] ?? ''));

        return DB::transaction(function () use ($proposal, $to, $comment): TopicProposal {
            $locked = TopicProposal::query()->lockForUpdate()->findOrFail($proposal->id);

            try {
                $locked->transitionTo($to, $comment === '' ? null : $comment);
            } catch (InvalidTopicProposalTransition) {
                throw ValidationException::withMessages([
                    'proposal' => (string) __('activities.proposals.decide.errors.already_decided'),
                ]);
            }

            TopicProposalDecided::dispatch($locked->organization_id, $locked->id, $locked->status->value);

            return $locked;
        });
    }
}
