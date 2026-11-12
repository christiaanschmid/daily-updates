<flux:dropdown position="bottom" align="end" {{ $attributes }}>
    <button
        type="button"
        class="grid size-9 cursor-pointer place-items-center rounded-full border border-zinc-700 bg-zinc-800 text-xs font-semibold text-zinc-100 transition-colors hover:border-zinc-600"
        aria-label="{{ __('Account menu') }}"
        data-test="sidebar-menu-button"
    >
        {{ auth()->user()->initials() }}
    </button>

    <flux:menu>
        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
            <flux:avatar
                :name="auth()->user()->name"
                :initials="auth()->user()->initials()"
            />
            <div class="grid flex-1 text-start text-sm leading-tight">
                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                @unlessdesktop
                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                @enddesktop
            </div>
        </div>
        <flux:menu.separator />
        <flux:menu.radio.group>
            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                {{ __('Settings') }}
            </flux:menu.item>
            @unlessdesktop
                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item
                        as="button"
                        type="submit"
                        icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer"
                        data-test="logout-button"
                    >
                        {{ __('Log out') }}
                    </flux:menu.item>
                </form>
            @enddesktop
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
