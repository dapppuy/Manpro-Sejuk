# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

@AGENTS.md

`AGENTS.md` holds the Laravel Boost guidelines (PHP style, Pest, Pint, Livewire, artisan conventions) and is imported above. Domain skills live in `.claude/skills/` (Livewire, Flux UI, Fortify, Tailwind, testing, Laravel best practices) — activate the relevant one before working in that area.

## Commands

```bash
composer setup          # first-time install: deps, .env, key, migrate, npm build
composer dev            # runs `php artisan dev` (server, queue, logs, Vite together)
npm run build           # production assets (Vite+ via `vp build`)
composer lint           # pint --parallel (fix)
composer types:check    # phpstan (larastan) level 7 over app/, config/, database/, routes/, bootstrap/app.php
composer test           # config:clear + pint --test + phpstan + php artisan test  (this is what CI runs)
php artisan test --compact tests/Feature/Settings/ProfileUpdateTest.php
php artisan test --compact --filter="profile information can be updated"
```

CI (`.github/workflows/tests.yml`) runs `composer setup` then `composer ci:check` on PHP 8.5 / Node 22, so lint and type errors fail the build, not just test failures.

## Architecture

Built from the official Laravel Livewire starter kit: Laravel 13, Livewire 4, Flux UI 2, Fortify (headless auth), Tailwind 4, Pest 5. SQLite by default; sessions, cache, and queue all use the database driver.

- **Livewire 4 single-file page components.** Full-page screens are Blade files under `resources/views/pages/`, referenced with the `pages::` namespace (e.g. `pages::settings.profile`). Files prefixed with `⚡` (e.g. `⚡profile.blade.php`) are single-file components containing an anonymous `new class extends Component` PHP block plus the template. Routes bind them with `Route::livewire(...)` in `routes/settings.php`. There are no class-based components in `app/Livewire/` apart from the `Logout` action.
- **Auth via Fortify, views via Livewire/Blade.** Fortify registers all auth routes/controllers; `FortifyServiceProvider` points each Fortify view at a `pages::auth.*` Blade file and wires `app/Actions/Fortify/*` (user creation, password reset). Login and two-factor rate limiters are defined there too. Enabled features are set in `config/fortify.php` (currently registration and password reset).
- **Shared validation rules** live as traits in `app/Concerns/` (`PasswordValidationRules`, `ProfileValidationRules`) and are reused by both Fortify actions and Livewire settings components — extend these rather than duplicating rules.
- **Layouts**: `resources/views/layouts/app.blade.php` (sidebar/header variants in `layouts/app/`) for authenticated pages; `layouts/auth.blade.php` (card/simple/split variants) for guest auth pages. Flux component overrides are in `resources/views/flux/`.
- **Production defaults** (`AppServiceProvider::configureDefaults`): dates are `CarbonImmutable`, destructive DB commands are prohibited in production, and strict password rules apply only in production.
- **Routes**: `routes/web.php` (home + `auth,verified` dashboard) and `routes/settings.php` (profile, appearance, security — security additionally requires `password.confirm`).

## Testing

Pest feature tests in `tests/Feature/` automatically use `Tests\TestCase` with `RefreshDatabase` (configured in `tests/Pest.php`). Use `UserFactory` for test users.
