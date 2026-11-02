<?php

namespace Tests\Unit;

use App\Enums\RequestStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RequestStatusTest extends TestCase
{
    /**
     * @return array<string, array{RequestStatus, RequestStatus}>
     */
    public static function validTransitions(): array
    {
        return [
            'draft to submitted' => [RequestStatus::Draft, RequestStatus::Submitted],
            'submitted to under review' => [RequestStatus::Submitted, RequestStatus::UnderReview],
            'under review to approved' => [RequestStatus::UnderReview, RequestStatus::Approved],
            'under review to rejected' => [RequestStatus::UnderReview, RequestStatus::Rejected],
            'approved to in progress' => [RequestStatus::Approved, RequestStatus::InProgress],
            'in progress to completed' => [RequestStatus::InProgress, RequestStatus::Completed],
        ];
    }

    #[DataProvider('validTransitions')]
    public function test_allows_defined_transitions(RequestStatus $from, RequestStatus $to): void
    {
        $this->assertTrue($from->canTransitionTo($to));
    }

    public function test_rejects_every_transition_outside_the_graph(): void
    {
        $allowed = array_map(fn (array $pair) => $pair[0]->value.'>'.$pair[1]->value, self::validTransitions());

        foreach (RequestStatus::cases() as $from) {
            foreach (RequestStatus::cases() as $to) {
                $expected = in_array($from->value.'>'.$to->value, $allowed, true);

                $this->assertSame($expected, $from->canTransitionTo($to), "{$from->value} -> {$to->value}");
            }
        }
    }

    public function test_rejected_and_completed_are_terminal(): void
    {
        $terminal = array_values(array_filter(RequestStatus::cases(), fn (RequestStatus $status) => $status->isTerminal()));

        $this->assertSame([RequestStatus::Rejected, RequestStatus::Completed], $terminal);
    }
}
