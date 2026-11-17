<?php

namespace App\Providers;

use App\Events\UseLocalDataRequested;
use App\Support\CloudMode;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Menu;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     */
    public function boot(): void
    {
        // After "Move to cloud", the window shows the cloud account instead of this Mac's logs.
        $cloudUrl = app(CloudMode::class)->url();

        $window = Window::open()
            ->title(config('app.name'))
            ->width(1100)
            ->height(800)
            ->minWidth(640)
            ->minHeight(480)
            ->rememberState();

        if ($cloudUrl !== null) {
            $window->url($cloudUrl);

            // The standard menus, plus a way back to the logs kept on this Mac.
            Menu::create(
                Menu::app(),
                Menu::edit(),
                Menu::view(),
                Menu::make(
                    Menu::label(__('Use This Mac\'s Data Instead'))->event(UseLocalDataRequested::class),
                )->label(__('Cloud')),
                Menu::window(),
            );
        }
    }

    /**
     * Return an array of php.ini directives to be set.
     *
     * @return array<string, string>
     */
    public function phpIni(): array
    {
        return [];
    }
}
