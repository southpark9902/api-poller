# API Poller

## Overview
The API Poller is a PHP command line application designed to periodically query a RESTful API for measurement data from VEMS running a DMS API endpoint and storing latest results in the files. 

The application is structured to be modern, well-organized, and easy to extend, with a focus on minimal dependencies.

## Installation

1. Clone the repository:
   ```
   git clone <repository-url>
   cd api-poller
   ```

TODO

## Usage

Production / systemd
- See `SYSTEMD_INSTALL.md` for an example of how to run the poller under systemd and how to provide `APP_ENV` and other variables using an `EnvironmentFile`.

Environment detection behavior
- If `APP_ENV` is already provided by the environment (getenv/$_ENV/$_SERVER), that value is respected.
- Otherwise, if a `.env.local` file exists in the project root, the autoloader sets `APP_ENV=local` and loads `.env.local`.
- Otherwise it sets `APP_ENV=production` and attempts to load `.env`.

TODO

## Configuration

TODO

## Testing

TODO

## Development

1. Copy `.env.example` -> `.env` or`.env.local` and edit values as needed.
2. Run the script locally:

TODO

Key points
- The autoloader (`autoload.php`) detects the runtime environment and loads env files.
- Use `.env.local` during development. Copy `.env.local.example` -> `.env.local` and set `APP_ENV=local` (or let the autoloader detect the presence of `.env.local`).
- On production, set `APP_ENV=production` via systemd `EnvironmentFile` or other host environment; the autoloader will then load `.env`.

Security
- Do not commit `.env.local` or `.env` to source control. Use the provided `.env.example` as a template.

## Docker (local testing)

I included a small Docker setup so you can run the poller and a dummy API locally.

- `docker-compose.yml` – launches two services:
  - `api` – a simple PHP+Apache container serving `dummy-api/index.php` on port 8080.
  - `app` – builds the PHP CLI image and runs `api-poller.php` against the `api` service.

To run the setup (once Docker is installed):

```powershell
# build and start both services
docker compose up --build

# or run detached
docker compose up --build -d

# tail logs
docker compose logs -f
```

The `app` service has `APP_ENV=local` and `API_BASE=http://api:80/api/` set in the compose file so the poller will talk to the dummy API.

### Installing Docker on Windows 10

Short answer: Docker Desktop (recommended) or a Docker Engine inside WSL2 are the supported routes on Windows 10, and installing them requires administrator privileges.

Recommended: Docker Desktop for Windows

1. Prereqs & notes
   - Windows 10 (Home or Pro). Docker Desktop supports Home via WSL2.
   - You must enable either WSL2 or Hyper-V / Virtual Machine Platform features. These steps require an administrator account.

2. Steps (summary)
   - Enable WSL and VirtualMachinePlatform (admin PowerShell):

```powershell
# Run in an elevated PowerShell (Run as Administrator)
dism.exe /online /enable-feature /featurename:VirtualMachinePlatform /all /norestart
dism.exe /online /enable-feature /featurename:WindowsSubsystemForLinux /all /norestart
```

   - Install the WSL2 Linux kernel update package (download from Microsoft) and set WSL2 as default:

```powershell
# then (non-admin after install) set default WSL version
wsl --set-default-version 2
```

   - Install a Linux distro from the Microsoft Store (e.g., Ubuntu).
   - Download and install Docker Desktop for Windows from https://www.docker.com/get-started and follow the installer (requires admin).
   - In Docker Desktop settings, enable the WSL integration for your distro.

3. After install
   - Open a new terminal and run `docker --version` and `docker compose version` to verify.

### Non-admin options

- Installing Docker Desktop or enabling WSL2/Hyper-V requires admin rights. There is currently no supported, secure way to install the Docker Engine on Windows 10 permanently without administrative privileges.
- Alternatives if you lack admin:
  - Use a remote Docker host (cloud VM, another machine) and point `DOCKER_HOST` from your machine to that host.
  - Use online development environments (GitHub Codespaces, Gitpod) which provide Docker and a dev container.
  - Run the dummy API using built-in PHP on your host (e.g., `php -S localhost:8080 -t dummy-api`) and run `php api-poller.php` locally — no Docker required.

If you want, I can:
- add a short PowerShell script to the repo that runs the dummy API using `php -S` for quick local testing without Docker, or
- generate the Windows Terminal / VS Code profile entries to make the Docker commands easier to run.