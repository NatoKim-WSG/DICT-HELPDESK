<?php

namespace Tests\Unit;

use App\Models\Ticket;
use PHPUnit\Framework\TestCase;

class TicketPriorityPresentationTest extends TestCase
{
    public function test_severity_badges_follow_urgency_order(): void
    {
        $severityOne = new Ticket(['priority' => 'severity_1']);
        $severityTwo = new Ticket(['priority' => 'severity_2']);
        $severityThree = new Ticket(['priority' => 'severity_3']);

        $this->assertSame('bg-red-100 text-red-800', $severityOne->priority_badge_class);
        $this->assertSame('bg-amber-100 text-amber-800', $severityTwo->priority_badge_class);
        $this->assertSame('bg-emerald-100 text-emerald-800', $severityThree->priority_badge_class);
    }
}
