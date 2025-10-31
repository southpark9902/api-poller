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