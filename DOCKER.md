# Docker Setup for YOURLS

This guide will help you run YOURLS using Docker and Docker Compose.

## Prerequisites

- Docker (version 20.10 or later)
- Docker Compose (version 2.0 or later)

## Quick Start

1. **Copy the environment file:**
   ```bash
   cp .env.example .env
   ```

2. **Edit the `.env` file** with your configuration:
   - Set `YOURLS_SITE` to your domain (e.g., `https://short.example.com`)
   - Change `YOURLS_DB_PASS` and `MYSQL_ROOT_PASSWORD` to secure passwords
   - Generate a secure `YOURLS_COOKIEKEY` (you can use: `openssl rand -hex 32`)
   - Set your admin username and password in `YOURLS_USER` and `YOURLS_PASS`

3. **Build and start the containers:**
   ```bash
   docker-compose up -d
   ```

4. **Access YOURLS:**
   - Open your browser and go to `http://localhost:8080/admin/install.php`
   - Complete the installation process
   - After installation, access the admin panel at `http://localhost:8080/admin/`

## Configuration

### Environment Variables

The following environment variables can be configured in `.env`:

| Variable | Description | Default |
|----------|-------------|---------|
| `YOURLS_SITE` | Your YOURLS installation URL | `http://localhost:8080` |
| `YOURLS_PORT` | Port to expose the web server | `8080` |
| `YOURLS_DB_HOST` | Database hostname | `db` |
| `YOURLS_DB_USER` | Database username | `yourls` |
| `YOURLS_DB_PASS` | Database password | `yourls_password` |
| `YOURLS_DB_NAME` | Database name | `yourls` |
| `YOURLS_DB_PREFIX` | Database table prefix | `yourls_` |
| `MYSQL_ROOT_PASSWORD` | MySQL root password | `root_password` |
| `MYSQL_PORT` | MySQL port | `3306` |
| `YOURLS_USER` | Admin username | `admin` |
| `YOURLS_PASS` | Admin password (will be hashed) | `admin` |
| `YOURLS_COOKIEKEY` | Cookie encryption key | (random) |

### Persistent Data

- **Database**: Stored in Docker volume `yourls-db-data`
- **User files**: The `user/` directory is mounted as a volume, so your config, plugins, and pages persist

## Useful Commands

### View logs
```bash
docker-compose logs -f
```

### Stop containers
```bash
docker-compose down
```

### Stop and remove volumes (⚠️ deletes database)
```bash
docker-compose down -v
```

### Rebuild containers
```bash
docker-compose build --no-cache
docker-compose up -d
```

### Access database
```bash
docker-compose exec db mysql -u yourls -p yourls
```

### Access web container shell
```bash
docker-compose exec web bash
```

## Production Deployment

For production use:

1. **Use HTTPS**: Set up a reverse proxy (nginx/traefik) with SSL certificates
2. **Change default passwords**: Update all default passwords in `.env`
3. **Generate secure cookie key**: Use `openssl rand -hex 32` for `YOURLS_COOKIEKEY`
4. **Set proper domain**: Update `YOURLS_SITE` to your actual domain
5. **Backup database**: Regularly backup the `yourls-db-data` volume
6. **Update regularly**: Keep Docker images updated for security patches

## Troubleshooting

### Database connection errors
- Ensure the database container is healthy: `docker-compose ps`
- Check database logs: `docker-compose logs db`
- Verify environment variables are set correctly

### Permission issues
- The entrypoint script sets proper permissions automatically
- If issues persist, check: `docker-compose exec web ls -la /var/www/html/user`

### Port already in use
- Change `YOURLS_PORT` in `.env` to a different port
- Or stop the service using port 8080

## Notes

- The `config.php` file is automatically generated from environment variables on first run
- You can manually edit `user/config.php` if needed, but it will be overwritten if you remove the container
- Plugins should be placed in `user/plugins/` directory
- Custom pages should be placed in `user/pages/` directory
