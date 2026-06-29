<div class="min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-sm bg-bg-surface border border-border-subtle rounded-xl p-8">
        <h1 class="text-2xl font-semibold mb-1">Welcome back</h1>
        <p class="text-text-secondary text-sm mb-6">Sign in to continue</p>

        <form wire:submit="authenticate" class="space-y-4">
            <div>
                <label for="email" class="block text-sm text-text-secondary mb-1">Email</label>
                <input id="email" type="email" wire:model="email" autocomplete="email"
                       class="w-full rounded-md bg-bg-base border border-border-subtle px-3 py-2 focus:border-brand-primary focus:outline-none">
                @error('email') <p class="text-feedback-danger text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="block text-sm text-text-secondary mb-1">Password</label>
                <input id="password" type="password" wire:model="password" autocomplete="current-password"
                       class="w-full rounded-md bg-bg-base border border-border-subtle px-3 py-2 focus:border-brand-primary focus:outline-none">
                @error('password') <p class="text-feedback-danger text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-text-secondary">
                <input type="checkbox" wire:model="remember" class="accent-brand-primary"> Remember me
            </label>

            <button type="submit"
                    class="w-full rounded-md bg-brand-primary text-bg-base font-medium py-2 hover:bg-brand-primary-press transition">
                Sign in
            </button>
        </form>
    </div>
</div>
