<div class="min-h-screen">
    <header class="border-b border-border-subtle">
        <div class="max-w-3xl mx-auto px-4 py-4 flex items-center justify-between">
            <span class="font-semibold">Fitness OS</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-text-secondary hover:text-text-primary transition">Sign out</button>
            </form>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 py-8 space-y-6">
        <div>
            <p class="text-text-secondary text-sm">Today</p>
            <h1 class="text-3xl font-semibold">Hi, {{ $person->display_name }}</h1>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div class="bg-bg-surface border border-border-subtle rounded-xl p-5">
                <p class="text-text-muted text-xs uppercase tracking-wide">Level</p>
                <p class="text-3xl font-semibold text-brand-primary">{{ $stats['level'] }}</p>
            </div>
            <div class="bg-bg-surface border border-border-subtle rounded-xl p-5">
                <p class="text-text-muted text-xs uppercase tracking-wide">XP</p>
                <p class="text-3xl font-semibold">{{ $stats['xp'] }}</p>
            </div>
            <div class="bg-bg-surface border border-border-subtle rounded-xl p-5">
                <p class="text-text-muted text-xs uppercase tracking-wide">Streak</p>
                <p class="text-3xl font-semibold">{{ $stats['streak_days'] }}<span class="text-base text-text-muted"> d</span></p>
            </div>
        </div>

        <div class="bg-bg-surface border border-border-subtle rounded-xl p-5">
            <div class="flex items-center justify-between text-sm mb-2">
                <span class="text-text-secondary">Progress to level {{ $stats['level'] + 1 }}</span>
                <span class="text-text-muted">{{ $stats['xp_into_level'] }} / {{ $stats['xp_for_next_level'] }} XP</span>
            </div>
            <div class="h-2 rounded-full bg-bg-base overflow-hidden">
                <div class="h-full bg-brand-primary"
                     style="width: {{ (int) round(100 * $stats['xp_into_level'] / max(1, $stats['xp_for_next_level'])) }}%"></div>
            </div>
        </div>
    </main>
</div>
