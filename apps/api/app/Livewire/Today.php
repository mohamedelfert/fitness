<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Engagement\Services\GamificationCalculator;

/**
 * Member web "Today" hero (E1.11) — the authenticated landing. Reuses the shipped
 * GamificationCalculator (FR-ENG-003) for XP/level/streak; badges are still WIP so they're
 * intentionally not shown here yet.
 */
#[Layout('layouts.app')]
class Today extends Component
{
    public function render()
    {
        $person = auth('web')->user();

        return view('livewire.today', [
            'person' => $person,
            'stats' => app(GamificationCalculator::class)->for($person),
        ]);
    }
}
