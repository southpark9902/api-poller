# Run the poller periodically (systemd timer — every 10 minutes)

This project provides a small CLI poller. The repository includes `scripts/run_poll.sh` which runs the poller with the Python CLI. The recommended way to run the poller every 10 minutes on a Linux host is to use a pair of systemd unit files: a `.service` which defines the command to run and a `.timer` which schedules the service.

The examples below assume you deploy the repo to `/opt/api-poller` and that the poller is started with:

```
python3 -m src.api_poller.main
```

## What we will create

- A dedicated service user (`api-poller`)
- A systemd service unit: `/etc/systemd/system/api-poller.service`
- A systemd timer unit: `/etc/systemd/system/api-poller.timer`
- A lock file to prevent overlapping runs (`/var/lock/api-poller.lock`)

## Prerequisites

- Python 3 and any project dependencies installed on the target host (see `requirements.txt`).
- The repository copied to the target path (example `/opt/api-poller`).
- The systemd host (most modern Linux distros) and a user with sudo to install units.

## Step-by-step deployment (example)

1. Copy the project to the server (adjust source path):

```bash
sudo mkdir -p /opt/api-poller
sudo cp -r ./* /opt/api-poller/
```

2. Create a dedicated, non-privileged user to run the job (optional but recommended):

```bash
sudo useradd --system --no-create-home --shell /usr/sbin/nologin api-poller
```

3. Install Python dependencies (recommended inside a virtualenv) and set ownership:

```bash
# as root or a deploy user
cd /opt/api-poller
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
deactivate

sudo chown -R api-poller:api-poller /opt/api-poller
sudo chmod +x /opt/api-poller/scripts/run_poll.sh
```

4. Create a lock file and ensure the service user can write it:

```bash
sudo mkdir -p /var/lock
sudo touch /var/lock/api-poller.lock
sudo chown api-poller:api-poller /var/lock/api-poller.lock
sudo chmod 0644 /var/lock/api-poller.lock
```

## Service unit example (`/etc/systemd/system/api-poller.service`)

```ini
[Unit]
Description=API Poller (one-shot)
After=network.target

[Service]
Type=oneshot
User=api-poller
WorkingDirectory=/opt/api-poller
# Use flock to prevent overlapping runs; adjust paths as needed
ExecStart=/usr/bin/flock -n /var/lock/api-poller.lock /bin/bash -lc '/opt/api-poller/venv/bin/python -m src.api_poller.main'
TimeoutStartSec=120
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

## Timer unit example (`/etc/systemd/system/api-poller.timer`)

```ini
[Unit]
Description=Run API poller every 10 minutes

[Timer]
OnBootSec=1min
OnUnitActiveSec=10min
Persistent=true

[Install]
WantedBy=timers.target
```

## Install and enable the timer

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now api-poller.timer

# Check timer status
systemctl list-timers --all | grep api-poller

# Monitor the service logs
journalctl -u api-poller.service -f
```

## Manual runs and control

- Run the service immediately:

```bash
sudo systemctl start api-poller.service
```

- Stop the timer to pause the schedule:

```bash
sudo systemctl stop api-poller.timer
```

## Notes and recommendations

- Use explicit timeouts and retries inside the poller (so a single slow request won't stall a run). See `src/api_poller/client.py` for client configuration.
- Prevent overlapping runs: we use `flock` above but you can also implement a PID/lockfile inside the script.
- Use `journalctl -u api-poller.service` to inspect output. If you prefer file logs, write to a log directory owned by `api-poller` (for example `/opt/api-poller/logs`).
- If you need to alter environment variables for the service, add an `EnvironmentFile=/etc/default/api-poller` line to the `[Service]` section and create that file with `KEY=VALUE` pairs.

### Setting environment variables (APP_ENV) with systemd

If you deploy the PHP poller as a systemd service you can provide environment variables in a few ways. The recommended approach is an EnvironmentFile so secrets or environment-specific values are kept outside the unit file.

Create `/etc/default/api-poller` (or `/etc/sysconfig/api-poller` on some distros) with lines like:

```
# /etc/default/api-poller
APP_ENV=production
API_BASE=https://device.example.com/api/
STORAGE_DIR=/var/lib/api-poller/storage
```

Then reference it in the service unit:

```ini
[Service]
User=api-poller
WorkingDirectory=/opt/api-poller
EnvironmentFile=/etc/default/api-poller
ExecStart=/usr/bin/php /opt/api-poller/api-poller.php
```

Notes:
- If `APP_ENV` is set to `local`, the autoloader will load `.env.local` when present. On production servers, set `APP_ENV=production` (or omit it) so `.env` is used instead.
- Avoid storing secrets in the repository. Keep `/etc/default/api-poller` readable only by root and the service user as appropriate.

## Troubleshooting

- If the service doesn't start, run:

```bash
sudo systemctl status api-poller.service
sudo journalctl -u api-poller.service --since "5 minutes ago"
```

- If timer isn't active after enabling, ensure you reloaded systemd (`systemctl daemon-reload`) and enabled the timer.

That's it — the timer will trigger the service approximately every 10 minutes and `flock` prevents overlaps so a slow run won't start another concurrent invocation.
