# Repository Guidelines

## Project Structure & Module Organization

This is the Moodle admin-tool plugin `tool_monitoring`, maintained exclusively
for Moodle 4.5. PHP classes use Moodle autoloading under `classes/`; built-in
metrics are in `classes/local/metrics/` and external services in
`classes/external/`. Root files include `index.php`, `settings.php`, and
`version.php`. Database definitions, capabilities, events, and upgrade steps
belong in `db/`. Language strings are in `lang/en/`, templates in `templates/`,
and AMD source/build output in `amd/src/` and `amd/build/`. The Prometheus
sub-plugin is in `exporter/prometheus/`. Tests live in `tests/` and
`exporter/prometheus/tests/`.

## Build, Test, and Development Commands

Use a Moodle 4.5 development installation and `moodle-plugin-ci`, matching CI:

```sh
moodle-plugin-ci phpcs --max-warnings 0
moodle-plugin-ci phpunit --fail-on-warning
moodle-plugin-ci behat --profile chrome --scss-deprecations
```

Run the focused relevant check first. The pull-request matrix validates PHP
8.1–8.3 with MariaDB and PostgreSQL, including linting, validation, Mustache,
Grunt, PHPUnit, and Behat.

## Coding Style & Naming Conventions

Follow Moodle standards: four-space PHP indentation, lower-case underscore
class and file names, and namespaces rooted at `tool_monitoring`. Moodle PHP
entry files need `defined('MOODLE_INTERNAL') || die();`. Use names such as
`users_online`, test files ending in `*_test.php`, and language IDs such as
`metric:users_online_desc`. Edit `amd/src/*.js`, then rebuild the paired file
in `amd/build/`; do not edit generated bundles directly.

## Testing, Commits, and Pull Requests

Use `advanced_testcase`, name test methods `test_<behavior>()`, and add Behat
scenarios for administrator UI flows. Keep all coverage; do not remove tests
to make CI pass. Commit subjects follow Conventional Commit style, for example
`fix: handle Moodle 4.5 navigation`. Target work at `develop`, state the tests
run in the PR, and include screenshots for UI changes. A verified internal PR
from `develop` to `main` is merged automatically by CI.

## Security

Never commit credentials. Configure `prometheus_token` before exposing the
exporter and send it in `Authorization: Bearer <token>` rather than a query
parameter.
