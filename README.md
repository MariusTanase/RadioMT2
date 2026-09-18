# RadioMT (Laravel port)

A Laravel 13 port of the [RadioMT](https://radio-mt.vercel.app/) React SPA: a
library of 20 online radio stations with a custom player, four selectable
themes, and a generated background image. Station data lives in the
database (the `radios` table, seeded from `database/seeders/RadioSeeder.php`)
rather than a hardcoded JS array as in the original. The frontend is Alpine.js
+ Tailwind, built with Vite; tsParticles renders the star field.

## PHP version caveat (read this first)

Laravel 13's dependencies require **PHP >= 8.4.1**. If your `php` on PATH
resolves to 8.4.0, Composer's `platform_check.php` will fail with a fatal
error that gives no useful hint about the real cause.

This repo ships a project-root `php.ini` (gitignored — it points
`extension_dir` at a specific local PHP install) as a workaround. Until you
upgrade PHP, invoke it explicitly everywhere:

```bash
php -c php.ini -v
```

or, on this machine, pointing directly at a PHP >= 8.4.1 binary:

```bash
"/path/to/php-8.4.12/php.exe" -c php.ini -v
```

**Once your system PHP is upgraded past 8.4.1, both the custom `php.ini` and
the `-c php.ini` flag become unnecessary.** That is the real fix; the ini file
is a stopgap.

## Two broken artisan wrappers

`php artisan test` and `php artisan serve` both re-exec a *child* PHP
process resolved from PATH, which drops the `-c php.ini` flag even if you
passed it to the outer command. On a system where plain `php` is 8.4.0 (or
otherwise misconfigured for this app), this produces confusing failures:

- `php artisan test` → `PDOException: could not find driver` (SQLite
  extension not loaded in the child process).
- `php artisan serve` → the dev server starts but every request 500s.

**Use these working alternatives instead:**

```bash
# Run the test suite
php -c php.ini vendor/bin/pest

# Serve the app
php -c php.ini -S 127.0.0.1:8123 -t public
```

(Substitute the full path to your PHP >= 8.4.1 binary for `php` if it isn't
first on PATH.)

## Setup

```bash
composer install
cp .env.example .env
php -c php.ini artisan key:generate
php -c php.ini artisan migrate
php -c php.ini artisan db:seed --class=RadioSeeder

npm install
npm run build   # production assets
# or
npm run dev     # Vite dev server with HMR
```

Then serve the app with the built-in server (see above) and visit it in a
browser.

## Unsplash background images

The `/api/background` endpoint fetches a random photo from Unsplash for one
of eight fixed categories (mountain, beach, sky, forest, cozy, japan, cat,
dog). To enable it:

1. Create a free account at [unsplash.com/developers](https://unsplash.com/developers)
   and register an application to get an **Access Key**.
2. Add it to `.env`:

   ```
   UNSPLASH_ACCESS_KEY=your-access-key-here
   ```

**The app works fine without a key.** If `UNSPLASH_ACCESS_KEY` is empty, the
background endpoint falls back to the bundled local background image
(`public/images/background.jpg`) instead of calling Unsplash.

## Fixed bugs

This port deliberately fixes four defects present in the original React app
rather than reproducing them:

1. The spacebar handler fired on *any* key, not just Space.
2. The volume slider always displayed 0 regardless of the actual volume.
3. A hardcoded station briefly autoplayed before the random startup pick
   replaced it.
4. The play/pause state was optimistic — it could show "playing" even when
   playback had failed or was blocked.

See `docs/superpowers/specs/2026-09-17-radiomt-laravel-port-design.md` for
the full write-up of each bug and its fix.

## Testing and linting

```bash
php -c php.ini vendor/bin/pest      # test suite
php -c php.ini vendor/bin/pint --test   # style check (no --test to auto-fix)
```

### PHP HTTPS and the CA bundle

The project-root `php.ini` must point cURL at a certificate bundle, or every
outbound HTTPS request fails with `cURL error 60: SSL certificate problem:
unable to get local issuer certificate` — which breaks `radios:import` and the
Unsplash background endpoint:

```ini
curl.cainfo = "C:/laragon/etc/ssl/cacert.pem"
openssl.cafile = "C:/laragon/etc/ssl/cacert.pem"
```

`php.ini` is gitignored because its paths are machine-specific, so this is easy
to lose when setting the project up on another machine.
