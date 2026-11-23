<x-layouts::app.header :title="$title ?? null">
    <main class="mx-auto w-full max-w-5xl px-4 pt-8 pb-16 sm:px-6 sm:pt-12">
        {{ $slot }}
    </main>
</x-layouts::app.header>
