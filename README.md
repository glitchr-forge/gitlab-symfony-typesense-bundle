# glitchr/typesense-bundle

Symfony integration for [Typesense](https://typesense.org/), structured like
Doctrine's own DBAL/ORM split:

- `DBAL/` — connections, low-level client wiring, multi-server support.
- `ORM/Mapping/` — collections, metadata (derived from real Doctrine
  `ClassMetadata`, including single-table-inheritance sub-collections).
- `ORM/Query/` — a fluent `Request`/`Query` builder and `Response` wrapper.
- `ORM/Transformer/` — entity ↔ Typesense document conversion.
- `EventListener/TypesenseIndexer` — keeps collections in sync with Doctrine
  entity lifecycle events automatically.

See [`docs/getting-started.md`](docs/getting-started.md) for a minimal,
working example, including a multi-server connection setup.

## Running the tests

Two suites:

- **`tests/Unit`** — no external dependencies, pure PHP + mocks. Runs
  anywhere PHP + this bundle's dependencies are installed.
- **`tests/Integration`** — exercises the real `typesense/typesense-php`
  client against a real Typesense server (skips itself if none is
  reachable).

### Locally, against the bundle's own `composer install`

```bash
composer install
composer test-unit          # fast, no server needed
composer test-integration    # needs TYPESENSE_URL / TYPESENSE_KEY pointing at a real server
composer test                # both suites
composer test-coverage       # HTML + text coverage report in var/coverage
```

### Full pipeline via Docker (spins up a real Typesense server too)

```bash
docker compose -f docker-compose.test.yml run --rm test
```

This builds `Dockerfile.test` (a bare `php:8.4-cli` image, `composer install`
as its own root package — no host Symfony app needed) and starts a real,
ephemeral (`tmpfs`) `typesense/typesense` server alongside it, wired via
`TYPESENSE_URL`/`TYPESENSE_KEY`. Use this exact setup as the base for a
`.gitlab-ci.yml`/GitHub Actions job.

### Inside a host application (dev workflow)

If this bundle is installed as a dependency (`vendor/glitchr/typesense-bundle`),
`tests/bootstrap.php` self-registers its test namespace against the host
app's own autoloader (which never picks up a dependency's `autoload-dev`):

```bash
docker exec -w /path/to/app/vendor/glitchr/typesense-bundle <web-container> \
    php ../../symfony/phpunit-bridge/bin/simple-phpunit
```
