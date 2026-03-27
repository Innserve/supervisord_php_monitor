# Supervisord Upgrade and Hardening Guide

This app controls supervisord processes remotely over the supervisord XML-RPC HTTP interface.
Treat it as an admin tool.

## 1. Safe Upgrade Path (Supervisord Hosts)

Use a staged upgrade process per host/group, not a fleet-wide in-place upgrade.

### Pre-upgrade checklist

- Confirm current version:
  - `supervisord --version`
  - `supervisorctl version`
- Back up config:
  - `/etc/supervisor/supervisord.conf` (or distro equivalent)
  - included `conf.d/*.conf`
- Confirm app connectivity to each host from this dashboard before change.
- Schedule a maintenance window for process restarts.

### Recommended rollout order

1. One non-critical host (canary)
2. One host per environment / role
3. Remaining hosts in batches
4. Final validation in dashboard and `supervisorctl status`

### Debian / Ubuntu (APT) example

```bash
sudo apt update
apt-cache policy supervisor
sudo apt install --only-upgrade supervisor
sudo supervisord --version
sudo supervisorctl reread
sudo supervisorctl update
sudo systemctl restart supervisor
sudo supervisorctl status
```

### RHEL / Rocky / Alma (DNF/YUM) example

```bash
sudo dnf check-update supervisor
sudo dnf upgrade supervisor
sudo supervisord --version
sudo supervisorctl reread
sudo supervisorctl update
sudo systemctl restart supervisord
sudo supervisorctl status
```

### Post-upgrade validation

- Dashboard loads all hosts without `Failed to load process list`
- `getSupervisorVersion` values look correct
- Start/Stop/Restart actions work on canary process
- Supervisord HTTP auth still required (see below)

## 2. Enable Supervisord HTTP Passwords (Required)

If this dashboard can reach supervisord, protect the supervisord endpoint with credentials.

### supervisord config (`inet_http_server`)

Edit each host’s supervisord config and enable the HTTP interface with auth:

```ini
[inet_http_server]
port=0.0.0.0:9001
username=supervisor_api
password=REPLACE_WITH_STRONG_RANDOM_PASSWORD
```

Notes:

- Prefer binding to a private IP or localhost + SSH tunnel/VPN instead of `0.0.0.0`.
- If you must bind broadly, enforce firewall allowlists.
- Restart supervisord after config changes.

### Restart and verify

```bash
sudo systemctl restart supervisor   # or supervisord service name for your distro
curl -u supervisor_api:YOUR_PASSWORD http://127.0.0.1:9001/RPC2
```

Expected behavior:

- Endpoint responds (XML-RPC endpoint may return method error for plain GET/curl; that is fine)
- Requests without credentials are rejected

### Dashboard `.env` (`SERVERS`) example with passwords

```dotenv
SERVERS={"worker-a":{"url":"http://10.0.0.21","port":"9001","username":"supervisor_api","password":"strong-password-a"},"worker-b":{"url":"http://10.0.0.22","port":"9001","username":"supervisor_api","password":"strong-password-b"}}
```

The app now validates that `username` and `password` are provided together.

## 3. Protect the Dashboard Itself (Required)

This app can stop production processes. Do not expose it directly to the public internet.

Minimum controls:

- VPN and/or internal network only
- Reverse proxy auth (Basic Auth, SSO, or both)
- HTTPS termination
- IP allowlist if possible

### Nginx Basic Auth example

Create password file:

```bash
sudo apt install apache2-utils   # package name may vary
sudo htpasswd -c /etc/nginx/.htpasswd supervisor-dashboard
```

Nginx location:

```nginx
location / {
    auth_basic "Supervisord Monitor";
    auth_basic_user_file /etc/nginx/.htpasswd;
    proxy_pass http://127.0.0.1:8080;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

### Apache Basic Auth example

```apache
<Location "/">
    AuthType Basic
    AuthName "Supervisord Monitor"
    AuthUserFile /etc/apache2/.htpasswd-supervisord-monitor
    Require valid-user
</Location>
```

Create the password file:

```bash
sudo htpasswd -c /etc/apache2/.htpasswd-supervisord-monitor supervisor-dashboard
```

## 4. Firewall Rules (Strongly Recommended)

Restrict supervisord RPC port (`9001` by default) so only the dashboard host can connect.

UFW example:

```bash
sudo ufw allow from 10.0.0.10 to any port 9001 proto tcp
sudo ufw deny 9001/tcp
```

Replace `10.0.0.10` with the IP of the host running this dashboard.

## 5. App Upgrade Path (This Repository)

Use the following sequence to reduce risk:

1. Update app code
2. Run `composer install`
3. Validate app config locally:
   - `composer check`
4. Deploy app behind auth/proxy
5. Smoke test:
   - `/healthz.php`
   - `/readyz.php`
   - dashboard page load
   - one canary start/stop/restart action

## 6. Operational Checks to Run Regularly

- Confirm `/healthz.php` returns `200`
- Confirm `/readyz.php` returns `200` within timeout budget
- Review web/PHP logs for `control.dispatch_failed` and RPC errors
- Rotate supervisord HTTP passwords periodically
- Remove decommissioned servers from `SERVERS` JSON
