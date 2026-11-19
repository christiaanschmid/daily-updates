{{--
    Overrides Flux's toast: a compact pill at the bottom center, like a Mac or iOS confirmation.
    One line, a small icon for the variant, no close button; it fades away on its own.
    Keep the template, data-flux-toast-dialog and the slot/template names: Flux's script fills them in.
--}}
@props([
    'position' => 'bottom center',
])

<ui-toast x-data x-on:toast-show.document="$el.showToast($event.detail)" popover="manual" position="{{ $position }}" class="du-toast" wire:ignore>
    <template>
        <div {{ $attributes->only(['class'])->class('max-w-[min(28rem,calc(100vw-2rem))]') }} data-variant="" data-flux-toast-dialog role="status" aria-live="polite">
            <div class="flex items-center gap-2 rounded-full border border-white/10 bg-zinc-900 py-2 ps-3.5 pe-4 text-sm font-medium text-white shadow-[0_8px_24px_rgb(15_20_36/0.25)] dark:border-white/10 dark:bg-zinc-700 dark:shadow-[0_8px_24px_rgb(0_0_0/0.5)]">
                {{-- Success --}}
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" class="hidden size-4 shrink-0 text-teal-300 [[data-flux-toast-dialog][data-variant=success]_&]:block">
                    <path fill-rule="evenodd" d="M12.416 3.376a.75.75 0 0 1 .208 1.04l-5 7.5a.75.75 0 0 1-1.154.114l-3-3a.75.75 0 0 1 1.06-1.06l2.353 2.353 4.493-6.74a.75.75 0 0 1 1.04-.207Z" clip-rule="evenodd" />
                </svg>

                {{-- Warning --}}
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" class="hidden size-4 shrink-0 text-amber-300 [[data-flux-toast-dialog][data-variant=warning]_&]:block">
                    <path fill-rule="evenodd" d="M6.701 2.25c.577-1 2.02-1 2.598 0l5.196 9a1.5 1.5 0 0 1-1.299 2.25H2.804a1.5 1.5 0 0 1-1.3-2.25l5.197-9ZM8 4a.75.75 0 0 1 .75.75v3a.75.75 0 1 1-1.5 0v-3A.75.75 0 0 1 8 4Zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                </svg>

                {{-- Info --}}
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" class="hidden size-4 shrink-0 text-[#b9c6ff] [[data-flux-toast-dialog][data-variant=info]_&]:block">
                    <path fill-rule="evenodd" d="M15 8A7 7 0 1 1 1 8a7 7 0 0 1 14 0ZM9 5a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM6.75 8a.75.75 0 0 0 0 1.5h.75v1.75a.75.75 0 0 0 1.5 0v-2.5A.75.75 0 0 0 8.25 8h-1.5Z" clip-rule="evenodd" />
                </svg>

                {{-- Danger --}}
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" class="hidden size-4 shrink-0 text-rose-300 [[data-flux-toast-dialog][data-variant=danger]_&]:block">
                    <path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14ZM8 4a.75.75 0 0 1 .75.75v3a.75.75 0 0 1-1.5 0v-3A.75.75 0 0 1 8 4Zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                </svg>

                <div class="min-w-0">
                    {{-- A heading, when given, leads the line in bold; the text follows it. --}}
                    <span class="font-semibold [&:not(:empty)]:me-1"><slot name="heading"></slot></span><span class="text-white/90"><slot name="text"></slot></span>

                    <template name="link">
                        <a class="ms-1.5 underline underline-offset-4 decoration-white/40 hover:decoration-white"><slot name="text"></slot></a>
                    </template>
                </div>

                <template name="action">
                    <div class="ms-1 flex items-center" data-flux-toast-action>
                        <button type="button" data-flux-toast-action-button class="relative -my-1 rounded-full bg-white/15 px-2.5 py-1 text-[13px] font-semibold text-white hover:bg-white/25 data-loading:pointer-events-none [&[data-loading]_[data-flux-toast-action-label]]:opacity-0 [&[data-loading]_[data-flux-loading-indicator]]:opacity-100">
                            <span class="transition-opacity" data-flux-toast-action-label></span>
                            <span class="absolute inset-0 flex items-center justify-center opacity-0 transition-opacity" data-flux-loading-indicator>
                                <svg class="size-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4Z"></path>
                                </svg>
                            </span>
                        </button>

                        <a data-flux-toast-action-link class="-my-1 rounded-full bg-white/15 px-2.5 py-1 text-[13px] font-semibold text-white hover:bg-white/25">
                            <span data-flux-toast-action-label></span>
                        </a>
                    </div>
                </template>
            </div>
        </div>
    </template>
</ui-toast>
