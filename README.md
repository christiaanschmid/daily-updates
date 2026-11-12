# Daily Updates

A small app for keeping a log of your workday and sharing it at the next morning's huddle.

Write down what you get done as the day goes, keep a running todo list at the top of it, and the next morning open the **Huddle** page to see (and copy) yesterday's log, ready to read out or paste into chat.

![Today's log in Daily Updates, with open todos, the Done section and completed todos](docs/screenshot.png)

## Features

- **A log for each workday.** Each day has a **Done** section with a rich text editor. Markdown shortcuts work as you type (`` `code` ``, `**bold**`, `- ` for a bullet), and everything saves automatically a couple of seconds after you stop typing.
- **Todos.** One running list at the top of today's log. Check a todo off and it shows up under **Completed todos** for that day. Click a todo to edit it, and use inline Markdown in todos too.
- **Huddle.** Shows the most recent workday's log under *Yesterday*, along with the todos completed that day, with a **Copy for huddle** button that produces plain text.
- **History.** Every past log, newest first, with search and highlighted matches.
- **Copy as text.** Any day's log can be copied as plain-text bullets.

## Requirements

- PHP 8.3 or newer, with the SQLite extension
- [Composer](https://getcomposer.org)
- [Node.js](https://nodejs.org) and npm

Data is stored in a local SQLite database, so there's no database server to set up.

The [desktop app](#desktop-app) also needs macOS 12 or newer and Node.js 22 or newer.

## Getting started

```bash
git clone https://github.com/christiaanschmid/daily-updates.git
cd daily-updates
composer setup
```

`composer setup` installs the PHP and JavaScript dependencies, creates your `.env` file, generates an app key, creates the SQLite database, and builds the front-end assets.

Then set your timezone in `.env`, so "today" changes over at your own midnight:

```dotenv
APP_TIMEZONE=America/Phoenix
```

Start the app:

```bash
composer run dev
```

Open the URL it prints (by default http://localhost:8000), register an account, and start logging. If you use [Laravel Herd](https://herd.laravel.com), the app is served at `http://daily-updates.test` without running a server yourself; run `npm run dev` for live asset reloading, or `npm run build` once.

## Desktop app

Daily Updates also runs as a Mac app, built with [NativePHP](https://nativephp.com/docs/desktop). It's the same app in its own window, with no server to run and no login: it signs you in automatically as its one local user.

### Running it

After `composer setup`, run these once:

```bash
php artisan native:install --no-interaction
```

```bash
node vendor/nativephp/desktop/resources/electron/node_modules/electron/install.js
```

```bash
php artisan native:migrate
```

The first installs NativePHP's Electron shell and a PHP binary for the app. The second downloads Electron itself: NativePHP looks for Electron before starting it, and Electron 45 no longer downloads itself when installed, so this step is needed on every fresh clone. The third creates the desktop app's database.

Then start the app:

```bash
composer native:dev
```

This opens the app in its own window and runs Vite, so front-end changes reload as you work. Changes to PHP code need a restart: press `Ctrl+C` and run it again.

### Where your data is stored

The desktop app keeps its data apart from the browser version:

- **While developing:** `database/nativephp.sqlite`
- **The built app:** `~/Library/Application Support/daily-updates/database/database.sqlite`

### Building the app

```bash
php artisan native:build mac arm64
```

Use `x64` instead of `arm64` for Intel Macs. The build runs `npm run build` first, then writes `Daily Updates.app`, a `.dmg` and a `.zip` to `nativephp/electron/dist/`.

The build is not code signed, so it opens on the Mac that built it, but other Macs will say the app is damaged. To share it, you need an [Apple Developer Program](https://developer.apple.com/programs/) membership and these settings in `.env` when you build: `NATIVEPHP_APPLE_ID`, `NATIVEPHP_APPLE_ID_PASS` (an app-specific password) and `NATIVEPHP_APPLE_TEAM_ID`. See NativePHP's [building guide](https://nativephp.com/docs/desktop/2/publishing/building).

Your `.env` file is copied into the app, where anyone who has it can read it, so keep secrets out of it. NativePHP removes a few known keys, listed under `cleanup_env_keys` in `config/nativephp.php`.

### App icon

The app icon is the web icon fitted to the size macOS expects. Its source is `resources/icons/desktop-icon.svg`. After changing it, regenerate `public/icon.png` and `public/icon.icns` with:

```bash
resources/icons/render-desktop-icon.sh
```

## Running the tests

```bash
php artisan test
```

## Built with

[Laravel](https://laravel.com), [Livewire](https://livewire.laravel.com), [Flux](https://fluxui.dev) (free edition), [Tiptap](https://tiptap.dev) and [Tailwind CSS](https://tailwindcss.com).

## License

Daily Updates is open source software released under the [MIT license](LICENSE).
