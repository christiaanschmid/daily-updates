<?php

namespace App\Providers;

use App\Events\SyncNowRequested;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Menu;
use Native\Desktop\Facades\Settings;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     */
    public function boot(): void
    {
        // Earlier versions showed the hosted site in the window after "Move to cloud".
        // The window now always shows this Mac's own app, which syncs with the cloud.
        if (Settings::get('cloud_url') !== null) {
            Settings::forget('cloud_url');
        }

        Window::open()
            ->title(config('app.name'))
            ->width(1100)
            ->height(800)
            ->minWidth(640)
            ->minHeight(480)
            // No title bar: the app's own toolbar holds the window buttons, like a Mac app.
            ->titleBarHiddenInset()
            ->trafficLightPosition(18, 18)
            ->backgroundColor('#0b0f1a')
            // Links to other sites open in the default browser, never in this window.
            ->preventLeaveDomain()
            ->suppressNewWindows()
            ->rememberState();

        Menu::create(
            Menu::make(
                Menu::about(),
                Menu::separator(),
                Menu::route('profile.edit', __('Settings…'), 'CmdOrCtrl+,'),
                Menu::separator(),
                Menu::hide(),
                Menu::separator(),
                Menu::quit(),
            )->label(config('app.name')),
            Menu::make(
                Menu::label(__('Sync Now'))->event(SyncNowRequested::class),
                Menu::route('plan.edit', __('Cloud Sync…')),
                Menu::separator(),
                Menu::close(),
            )->label(__('File')),
            Menu::edit(),
            Menu::view(),
            Menu::make(
                Menu::route('today', __('Today'), 'CmdOrCtrl+1'),
                Menu::route('huddle', __('Huddle'), 'CmdOrCtrl+2'),
                Menu::route('dashboard', __('History'), 'CmdOrCtrl+3'),
            )->label(__('Go')),
            Menu::window(),
        );
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
