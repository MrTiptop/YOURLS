# Colima Setup for YOURLS

Since you're using Colima on macOS, here's how to get everything set up:

## Prerequisites

Colima requires the Docker CLI to be installed separately:

```bash
brew install docker
```

## Starting Colima

1. **Start Colima:**
   ```bash
   colima start
   ```

2. **Verify it's running:**
   ```bash
   colima status
   docker info
   ```

## Using the Setup Script

The `docker-start.sh` script has been updated to automatically:
- Detect Colima
- Start Colima if it's not running
- Configure Docker context

Just run:
```bash
./docker-start.sh
```

## Manual Setup

If you prefer to set up manually:

1. **Start Colima:**
   ```bash
   colima start
   ```

2. **Verify Docker is working:**
   ```bash
   docker --version
   docker info
   ```

3. **Start YOURLS:**
   ```bash
   docker-compose up -d
   ```

## Common Colima Commands

```bash
# Start Colima
colima start

# Stop Colima
colima stop

# Check status
colima status

# View logs
colima logs

# Restart Colima
colima restart
```

## Troubleshooting

### Docker CLI not found
```bash
brew install docker
```

### Colima fails to start
- Check system resources (CPU, RAM)
- Try: `colima start --cpu 2 --memory 4`
- View logs: `colima logs`

### Docker context issues
```bash
# List contexts
docker context ls

# Use Colima context
docker context use colima

# Check current context
docker context show
```

## Resource Configuration

If you need to adjust Colima resources, edit the Colima config:

```bash
# Start with custom resources
colima start --cpu 4 --memory 8 --disk 60

# Or edit config
colima edit
```

## Notes

- Colima runs Docker in a lightweight VM
- The Docker CLI must be installed separately from Colima
- Colima is a great alternative to Docker Desktop on macOS
- All standard Docker and docker-compose commands work the same
