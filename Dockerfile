FROM php:8.1-cli

# Install common tools (curl used by the client and for debugging)
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl ca-certificates git unzip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Copy project into image. Using a bind-mount in docker-compose keeps image small
COPY . /app

CMD ["php", "/app/api-poller.php"]
