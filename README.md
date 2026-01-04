# SeedDMS Docker Image

[![Docker Pulls](https://img.shields.io/docker/pulls/andy008/seeddms)](https://hub.docker.com/r/andy008/seeddms)
[![Docker Image Size](https://img.shields.io/docker/image-size/andy008/seeddms/latest?style=flat)](https://hub.docker.com/r/andy008/seeddms)
[![Docker Image Version](https://img.shields.io/docker/v/andy008/seeddms/latest)](https://hub.docker.com/r/andy008/seeddms)

Multi-architecture Docker image for [SeedDMS](https://www.seeddms.org/), a document management system. This image ([`andy008/seeddms`](https://hub.docker.com/r/andy008/seeddms)) provides **native support for both `linux/amd64` and `linux/arm64` architectures**, making it ideal for ARM-based systems like Apple Silicon Macs, Raspberry Pi, and other ARM64 servers.

## Why This Image?

While other SeedDMS Docker images may only support `linux/amd64`, this image is built with **multi-architecture support** using Docker Buildx, ensuring optimal performance on ARM64 systems without emulation overhead.

## Quick Start

### Basic Usage

```bash
docker run -d \
  --name seeddms \
  -p 8080:80 \
  -v seeddms-data:/var/seeddms/seeddms60x/data \
  -v seeddms-conf:/var/seeddms/seeddms60x/conf \
  andy008/seeddms:latest
```

Then open your browser and navigate to `http://localhost:8080` to start the SeedDMS installation wizard.

### With Docker Compose

```yaml
services:
  seeddms:
    image: andy008/seeddms:latest
    ports:
      - "8080:80"
    volumes:
      - seeddms-data:/var/seeddms/seeddms60x/data
      - seeddms-conf:/var/seeddms/seeddms60x/conf

volumes:
  seeddms-data:
  seeddms-conf:
```

## Architecture Support

This image supports the following architectures:
- ✅ **linux/amd64** (Intel/AMD 64-bit)
- ✅ **linux/arm64** (ARM 64-bit, including Apple Silicon)

The correct image for your platform is automatically selected when you pull the image.

## Volumes

The following volumes are exposed for data persistence:

- `/var/seeddms/seeddms60x/data` - Document storage and database files
- `/var/seeddms/seeddms60x/conf` - Configuration files

**Important:** Make sure to mount these volumes to persist your data across container restarts.


## Database Configuration

SeedDMS requires a database. You can use MySQL/MariaDB or PostgreSQL. Here's an example with MariaDB:

```yaml
version: '3.8'

services:
  db:
    image: mariadb:latest
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_DATABASE: seeddms
      MYSQL_USER: seeddms
      MYSQL_PASSWORD: seeddms
    volumes:
      - db-data:/var/lib/mysql

  seeddms:
    image: andy008/seeddms:latest
    ports:
      - "8080:80"
    volumes:
      - seeddms-data:/var/seeddms/seeddms60x/data
      - seeddms-conf:/var/seeddms/seeddms60x/conf
    depends_on:
      - db

volumes:
  db-data:
  seeddms-data:
  seeddms-conf:
```

## Image Details

- **Base Image:** `andy008/php4seeddms`
- **Web Server:** Apache 2.4
- **PHP:** PHP 8.x (configured for production)
- **SeedDMS Version:** Automatically updated from latest SourceForge release
- **Port:** 80 (HTTP)

## Features

### Built-in Scheduler

This image includes a **lightweight scheduler** that automatically runs SeedDMS scheduled tasks.

**How it works:**
- Managed by **s6-overlay** process supervisor for reliability
- Automatically executes scheduled tasks configured in the SeedDMS admin interface
- Runs alongside Apache with automatic restart on failure
- No additional dependencies or disk space required
- Configurable interval via environment variable

**Configuration:**

The scheduler interval can be customized using the `SEEDDMS_SCHEDULER_INTERVAL` environment variable (in seconds). Default is 300 seconds (5 minutes).

```yaml
services:
  seeddms:
    image: andy008/seeddms:latest
    environment:
      - SEEDDMS_SCHEDULER_INTERVAL=600  # Run every 10 minutes
```

Or with `docker run`:
```bash
docker run -e SEEDDMS_SCHEDULER_INTERVAL=600 andy008/seeddms:latest
```

**Required Setup:**

The scheduler requires a SeedDMS user named `cli_scheduler` to exist. This is a SeedDMS user (not a system user) that must be created through the SeedDMS admin interface:

1. Log in to SeedDMS as an administrator
2. Go to **Admin → Users and Groups**
3. Click **Add User**
4. Create a user with the exact login name: `cli_scheduler`
5. Set a password (this user won't be used for web login, but a password is required)
6. Assign appropriate permissions (will be used to execute tasks)

**Important:** The scheduler will fail with an error if this user doesn't exist. You'll see errors like:
```
Execution of tasks failed because of missing user 'cli_scheduler'. Will exit now.
```

**Testing the scheduler:**
```bash
# List all scheduled tasks
docker exec seeddms /var/seeddms/seeddms60x/seeddms/utils/seeddms-schedulercli --mode=list

# Run tasks immediately (dry-run)
docker exec seeddms /var/seeddms/seeddms60x/seeddms/utils/seeddms-schedulercli --mode=dryrun
```

**Note:** Scheduler output is logged to Apache's error log. View with:
```bash
docker logs seeddms 2>&1 | grep -i scheduler
```

### LLM Document Classifier

This image includes a custom extension that automatically classifies PDF documents using Large Language Models (LLMs).

**Features:**
- Automatic document naming based on content
- Category assignment from configured SeedDMS categories
- Keyword extraction for improved searchability
- Support for OpenAI, Azure OpenAI, and Ollama

See the [extension documentation](ext/llmclassifier/README.md) for configuration details.

## Automatic Updates

This repository includes automated workflows that:
- Check daily for new SeedDMS releases from SourceForge
- Create pull requests when updates are available
- Build and push multi-architecture images automatically

## Building from Source

To build the image locally:

```bash
docker buildx build --platform linux/amd64,linux/arm64 -t andy008/seeddms:latest .
```

Or for a single architecture:

```bash
docker build -t andy008/seeddms:latest .
```

## Troubleshooting

### Scheduler Errors: Missing `cli_scheduler` User

If you see errors like:
```
Execution of tasks failed because of missing user 'cli_scheduler'. Will exit now.
```

This means the required scheduler user hasn't been created yet. See the **Built-in Scheduler** section above for instructions on creating the `cli_scheduler` user in SeedDMS.

### Permission Issues

If you encounter permission issues with the data or conf directories, ensure the volumes are properly mounted and have correct permissions:

```bash
docker exec seeddms chown -R www-data:www-data /var/seeddms/seeddms60x/data
docker exec seeddms chown -R www-data:www-data /var/seeddms/seeddms60x/conf
```

### ARM64 Performance

On ARM64 systems, make sure you're using the native ARM64 image. You can verify this by checking the image architecture:

```bash
docker inspect andy008/seeddms:latest | grep Architecture
```

## License

SeedDMS is licensed under the GPL v2. See the [SeedDMS website](https://www.seeddms.org/) for more information.

## Links

- [SeedDMS Official Website](https://www.seeddms.org/)
- [Docker Hub Repository](https://hub.docker.com/r/andy008/seeddms)
- [GitHub Repository](https://github.com/andkrause/docker-seeddms)

## Support

This project is actively maintained, but **no support is provided**. 

For SeedDMS-specific issues, please refer to the [SeedDMS community forums](https://www.seeddms.org/forum/).

