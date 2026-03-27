# Architecture

## Request Flow

- `public/index.php`
  - loads shared bootstrap (`bootstrap.php`)
  - fetches supervisord process + version data for each configured server
  - logs fetch duration per server
  - renders the dashboard HTML
- `public/control.php`
  - loads shared bootstrap (`bootstrap.php`)
  - validates action/server/worker input
  - dispatches action via a centralized action map
  - logs each RPC call
  - redirects back to `/`
- `public/healthz.php`
  - verifies app boot + config parse
- `public/readyz.php`
  - performs a bounded sample `getSupervisorVersion` call to one configured server

## Responsibilities

- `bootstrap.php`
  - autoload
  - dotenv load + required env checks
  - config/helper includes
  - default values (`refresh`, `timeout`, metadata)
  - request ID and structured logging helpers
- `config/config.inc`
  - static defaults
  - `SERVERS` JSON parsing
- `lib/functions.inc`
  - HTML escaping + control URL generation
  - supervisor XML-RPC transport (`call_supervisor`)
  - control action map / dispatch
  - config validation helpers

## Logging Format

Logs are JSON lines written via PHP `error_log()` with fields such as:

- `ts`
- `event`
- `request_id`
- `server`
- `action`
- `duration_ms`

Example events:

- `dashboard.server_fetch`
- `control.rpc_call`
- `control.dispatch_failed`
- `readiness.sample_check`
