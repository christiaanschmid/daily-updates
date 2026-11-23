<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-canvas font-sans text-ink antialiased">
        <header class="border-b border-line-faint">
            <nav aria-label="{{ __('Main') }}" class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-6 py-4">
                <a href="{{ route('home') }}" class="flex min-h-11 items-center gap-2.5 text-ink-strong">
                    <x-app-logo-icon class="size-8" aria-hidden="true" />
                    <span class="text-[17px] font-semibold tracking-tight">{{ config('app.name') }}</span>
                </a>

                <div class="flex items-center gap-2">
                    <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center rounded-lg px-3.5 text-[15px] font-medium text-ink-soft hover:bg-hover">
                        {{ __('Log in') }}
                    </a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="inline-flex min-h-11 items-center rounded-lg bg-accent px-4.5 text-[15px] font-medium text-accent-foreground hover:opacity-90">
                            {{ __('Get started') }}
                        </a>
                    @endif
                </div>
            </nav>
        </header>

        <main>
            {{-- Hero --}}
            <section class="mx-auto flex max-w-6xl flex-wrap items-center gap-14 px-6 pt-16 pb-16 md:pt-22 md:pb-18">
                <div class="flex min-w-0 flex-[1_1_400px] flex-col gap-6">
                    <p class="font-mono text-[13px] tracking-[0.06em] text-ink-muted uppercase">{{ __('A work log for your standup') }}</p>
                    <h1 class="text-[clamp(2.5rem,5vw,3.75rem)] leading-[1.05] font-bold tracking-[-0.035em] text-ink-strong">
                        {{ __('Know what you did yesterday.') }}
                    </h1>
                    <p class="max-w-lg text-[19px] leading-relaxed text-ink-muted">
                        {{ __('Jot down what you get done as the day goes. Next morning, open Huddle and your update is ready to read out or paste into chat.') }}
                    </p>
                    <div class="flex flex-wrap gap-3 pt-2">
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex min-h-12 items-center rounded-[10px] bg-accent px-5.5 font-medium text-accent-foreground hover:opacity-90">
                                {{ __('Start your log') }}
                            </a>
                        @endif
                        <a href="{{ route('login') }}" class="inline-flex min-h-12 items-center rounded-[10px] border border-line-strong bg-canvas px-5.5 font-medium text-ink hover:bg-hover">
                            {{ __('Log in') }}
                        </a>
                    </div>
                </div>

                {{-- A picture of today's log, drawn in HTML so it follows the theme. --}}
                <div role="img" aria-label="{{ __("Today's log in Daily Updates, with open todos and the Done section") }}" class="min-w-0 flex-[1_1_460px] rounded-2xl border border-line bg-surface p-3 shadow-[0_24px_48px_-24px_var(--color-shadow)]">
                    <div aria-hidden="true" class="flex flex-col gap-5.5 rounded-[10px] border border-line bg-canvas p-7">
                        <div class="flex flex-wrap items-baseline justify-between gap-3">
                            <span class="text-[22px] font-semibold tracking-tight text-ink-strong">{{ __('Monday, November 23') }}</span>
                            <span class="font-mono text-xs text-ink-muted">{{ __('Saved') }}</span>
                        </div>
                        <div class="flex flex-col gap-2.5">
                            <span class="text-xs font-semibold tracking-[0.06em] text-section-up-next uppercase">{{ __('Todos') }}</span>
                            @foreach ([__('Review the onboarding PR'), __('Draft Q1 roadmap notes')] as $todo)
                                <div class="flex items-center gap-2.5 text-[15px]">
                                    <span class="size-4 flex-none rounded border-[1.5px] border-line-hover"></span>
                                    {{ $todo }}
                                </div>
                            @endforeach
                        </div>
                        <div class="h-px bg-line-faint"></div>
                        <div class="flex flex-col gap-2.5">
                            <span class="text-xs font-semibold tracking-[0.06em] text-section-done uppercase">{{ __('Done') }}</span>
                            <ul class="flex list-disc flex-col gap-2 ps-5 text-[15px] leading-normal marker:text-zinc-500">
                                <li>{{ __('Fixed the sync issue between desktop and cloud') }}</li>
                                <li>{{ __('Paired on the') }} <code class="rounded bg-raised px-1.5 py-0.5 font-mono text-[13px]">checkout</code> {{ __('webhook tests') }}</li>
                                <li><strong class="font-semibold">{{ __('Shipped') }}</strong> {{ __('the custom sidebar') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Features --}}
            <section aria-labelledby="features-title" class="border-y border-line-faint bg-surface">
                <div class="mx-auto flex max-w-6xl flex-col gap-10 px-6 py-20">
                    <h2 id="features-title" class="max-w-xl text-[32px] leading-tight font-semibold tracking-[-0.025em] text-ink-strong">
                        {{ __('Three small habits. No more blank stares at standup.') }}
                    </h2>
                    <div class="grid grid-cols-[repeat(auto-fit,minmax(260px,1fr))] gap-5">
                        @foreach ([
                            ['icon' => 'pencil-square', 'title' => __('A log for every workday'), 'body' => __('Write as you go with Markdown shortcuts. It saves itself a couple of seconds after you stop typing.')],
                            ['icon' => 'check-circle', 'title' => __('Todos that count'), 'body' => __("Keep one running list at the top of today. Check one off and it lands in that day's completed work.")],
                            ['icon' => 'user-group', 'title' => __('Huddle, ready to go'), 'body' => __("Yesterday's log and finished todos on one page, with a single button to copy it all as plain text.")],
                        ] as $feature)
                            <div class="flex flex-col gap-3 rounded-[14px] border border-line bg-canvas p-7">
                                <flux:icon :icon="$feature['icon']" variant="outline" class="size-6 text-accent-content" />
                                <h3 class="text-[19px] font-semibold text-ink-strong">{{ $feature['title'] }}</h3>
                                <p class="text-[15px] leading-relaxed text-ink-muted">{{ $feature['body'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Huddle --}}
            <section aria-labelledby="huddle-title" class="mx-auto flex max-w-6xl flex-wrap-reverse items-center gap-14 px-6 py-22">
                <div role="img" aria-label="{{ __("The Huddle page showing yesterday's update with a Copy for huddle button") }}" class="flex min-w-0 flex-[1_1_440px] flex-col gap-4.5 rounded-2xl bg-zinc-900 p-7 dark:border dark:border-line dark:bg-surface">
                    <div aria-hidden="true" class="flex flex-wrap items-center justify-between gap-3">
                        <span class="text-[13px] font-semibold tracking-[0.06em] text-[#b9c6ff] uppercase">{{ __('Yesterday') }}</span>
                        <span class="inline-flex min-h-9 items-center gap-2 rounded-lg bg-white px-3.5 text-sm font-medium text-zinc-950">
                            <flux:icon.clipboard-document variant="micro" class="size-4" />
                            {{ __('Copy for huddle') }}
                        </span>
                    </div>
                    <pre aria-hidden="true" class="font-mono text-sm leading-[1.75] whitespace-pre-wrap text-zinc-100">- {{ __('Fixed the sync issue between desktop and cloud') }}
- {{ __('Paired on the checkout webhook tests') }}
- {{ __('Shipped the custom sidebar') }}
- {{ __('Reviewed the onboarding PR') }}</pre>
                </div>
                <div class="flex min-w-0 flex-[1_1_360px] flex-col gap-4.5">
                    <h2 id="huddle-title" class="text-[32px] leading-tight font-semibold tracking-[-0.025em] text-ink-strong">{{ __('Your update writes itself.') }}</h2>
                    <p class="text-[17px] leading-relaxed text-ink-muted">
                        {{ __('By the time the meeting starts, the work is already written down. Read it out, or paste it into Slack and get on with your day.') }}
                    </p>
                    <p class="text-[17px] leading-relaxed text-ink-muted">
                        {{ __('Looking for something from last month? History keeps every past log, searchable, newest first.') }}
                    </p>
                </div>
            </section>

            {{-- Closing call to action --}}
            <section aria-labelledby="cta-title" class="px-6 pb-24">
                <div class="mx-auto flex max-w-[1072px] flex-col items-center gap-5 rounded-[20px] bg-zinc-800 px-8 py-16 text-center dark:border dark:border-line dark:bg-surface">
                    <h2 id="cta-title" class="text-[clamp(1.75rem,3.5vw,2.5rem)] font-bold tracking-[-0.03em] text-white">{{ __("Start tomorrow's update today.") }}</h2>
                    <p class="max-w-md text-[17px] leading-relaxed text-zinc-200">{{ __('Also available as a Mac app that syncs with your account.') }}</p>
                    <div class="flex flex-wrap justify-center gap-3 pt-2">
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex min-h-12 items-center rounded-[10px] bg-accent px-6 font-medium text-accent-foreground hover:opacity-90">
                                {{ __('Create your account') }}
                            </a>
                        @endif
                        <a href="{{ route('login') }}" class="inline-flex min-h-12 items-center rounded-[10px] border border-zinc-600 px-6 font-medium text-white hover:bg-white/10">
                            {{ __('Log in') }}
                        </a>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-line-faint">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-6 py-7 text-sm text-ink-muted">
                <span>&copy; {{ now()->year }} {{ config('app.name') }}</span>
                <div class="flex gap-5">
                    <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center hover:text-ink">{{ __('Log in') }}</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="inline-flex min-h-11 items-center hover:text-ink">{{ __('Sign up') }}</a>
                    @endif
                </div>
            </div>
        </footer>

        @fluxScripts
    </body>
</html>
