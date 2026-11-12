<x-layouts::app.header :title="$title ?? null">
    <main class="mx-auto w-full max-w-5xl px-6 pt-12 pb-16">
        {{ $slot }}
    </main>
</x-layouts::app.header>
