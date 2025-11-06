# Monitoring Automation Setup

## Overview
Automate endpoint monitoring checks to run automatically at regular intervals.

---

## Option 1: Using Cron (Linux/Production)

### 1.1 Setup Cron on Host Machine

Edit crontab:
```bash
crontab -e
```

Add monitoring jobs:
```bash
# Check endpoints every minute (due endpoints only)
* * * * * docker exec api-monitor-saas-symfony-1 php bin/console app:monitor:endpoints -d >> /var/log/api-monitor-cron.log 2>&1

# Check all endpoints every 5 minutes
*/5 * * * * docker exec api-monitor-saas-symfony-1 php bin/console app:monitor:endpoints -a >> /var/log/api-monitor-cron.log 2>&1

# Clean old monitoring results (older than 30 days) - runs daily at 2 AM
0 2 * * * docker exec api-monitor-saas-symfony-1 php bin/console app:monitoring:cleanup --days=30 >> /var/log/api-monitor-cron.log 2>&1
```

### 1.2 Create Log Directory
```bash
sudo mkdir -p /var/log
sudo touch /var/log/api-monitor-cron.log
sudo chmod 666 /var/log/api-monitor-cron.log
```

### 1.3 View Cron Logs
```bash
tail -f /var/log/api-monitor-cron.log
```

---

## Option 2: Windows Task Scheduler

### 2.1 Create PowerShell Script

Create file: `C:\APIMonitor\monitor-check.ps1`
```powershell
# API Monitor - Automated Monitoring Check
$logFile = "C:\APIMonitor\logs\monitor.log"
$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"

# Ensure log directory exists
$logDir = Split-Path $logFile
if (-not (Test-Path $logDir)) {
    New-Item -ItemType Directory -Path $logDir -Force
}

# Run monitoring check
Write-Output "[$timestamp] Starting monitoring check..." | Out-File $logFile -Append

try {
    docker exec api-monitor-saas-symfony-1 php bin/console app:monitor:endpoints -d 2>&1 | Out-File $logFile -Append
    Write-Output "[$timestamp] Monitoring check completed successfully" | Out-File $logFile -Append
} catch {
    Write-Output "[$timestamp] ERROR: $($_.Exception.Message)" | Out-File $logFile -Append
}
```

### 2.2 Create Task in Task Scheduler

1. Open "Task Scheduler"
2. Click "Create Task"
3. **General Tab:**
   - Name: `API Monitor - Endpoint Checks`
   - Description: `Automated monitoring of API endpoints`
   - Run whether user is logged on or not: ✅
   - Run with highest privileges: ✅

4. **Triggers Tab:**
   - Click "New"
   - Begin the task: `On a schedule`
   - Settings: `Daily`
   - Repeat task every: `1 minute`
   - Duration: `Indefinitely`

5. **Actions Tab:**
   - Click "New"
   - Action: `Start a program`
   - Program: `powershell.exe`
   - Arguments: `-ExecutionPolicy Bypass -File "C:\APIMonitor\monitor-check.ps1"`

6. **Conditions Tab:**
   - Uncheck "Start the task only if the computer is on AC power"

7. **Settings Tab:**
   - Allow task to be run on demand: ✅
   - If task fails, restart every: `1 minute`
   - Attempt to restart up to: `3 times`

### 2.3 Test the Task
```powershell
# Run manually
schtasks /Run /TN "API Monitor - Endpoint Checks"

# View logs
Get-Content C:\APIMonitor\logs\monitor.log -Tail 20 -Wait
```

---

## Option 3: Docker Container with Cron

### 3.1 Create Monitoring Service

Create file: `monitoring-cron/Dockerfile`
```dockerfile
FROM alpine:latest

RUN apk add --no-cache docker-cli

# Copy cron file
COPY crontab /etc/crontabs/root

# Copy monitoring script
COPY monitor.sh /usr/local/bin/monitor.sh
RUN chmod +x /usr/local/bin/monitor.sh

CMD ["crond", "-f", "-l", "2"]
```

Create file: `monitoring-cron/crontab`
```
# Check due endpoints every minute
* * * * * /usr/local/bin/monitor.sh -d

# Check all endpoints every 5 minutes
*/5 * * * * /usr/local/bin/monitor.sh -a
```

Create file: `monitoring-cron/monitor.sh`
```bash
#!/bin/sh

FLAG=$1
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

echo "[$TIMESTAMP] Running monitoring check with flag: $FLAG"

docker exec api-monitor-saas-symfony-1 php bin/console app:monitor:endpoints $FLAG

if [ $? -eq 0 ]; then
    echo "[$TIMESTAMP] Monitoring check completed successfully"
else
    echo "[$TIMESTAMP] ERROR: Monitoring check failed"
fi
```

### 3.2 Add to docker-compose
Add to `docker-compose.dev.yml`:
```yaml
  monitoring-cron:
    build: ./monitoring-cron
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
    depends_on:
      - symfony
    networks:
      - webnet
    restart: unless-stopped
```

### 3.3 Build and Start
```powershell
docker compose -f docker-compose.dev.yml up -d monitoring-cron

# View logs
docker logs -f api-monitor-saas-monitoring-cron-1
```

---

## Option 4: Symfony Messenger (Advanced)

### 4.1 Configure Messenger

Add to `symfony/config/packages/messenger.yaml`:
```yaml
framework:
    messenger:
        transports:
            async: '%env(MESSENGER_TRANSPORT_DSN)%'
        
        routing:
            'App\Message\CheckEndpointMessage': async
```

### 4.2 Create Message Class

`symfony/src/Message/CheckEndpointMessage.php`:
```php
<?php

namespace App\Message;

class CheckEndpointMessage
{
    public function __construct(
        private string $endpointId
    ) {}

    public function getEndpointId(): string
    {
        return $this->endpointId;
    }
}
```

### 4.3 Create Message Handler

`symfony/src/MessageHandler/CheckEndpointMessageHandler.php`:
```php
<?php

namespace App\MessageHandler;

use App\Message\CheckEndpointMessage;
use App\Service\EndpointMonitorService;
use App\Repository\EndpointRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CheckEndpointMessageHandler
{
    public function __construct(
        private EndpointMonitorService $monitorService,
        private EndpointRepository $endpointRepository
    ) {}

    public function __invoke(CheckEndpointMessage $message): void
    {
        $endpoint = $this->endpointRepository->find($message->getEndpointId());
        
        if ($endpoint && $endpoint->isActive()) {
            $this->monitorService->checkEndpoint($endpoint);
        }
    }
}
```

### 4.4 Start Worker
```powershell
docker exec -it api-monitor-saas-symfony-1 php bin/console messenger:consume async -vv
```

---

## Monitoring Best Practices

### 1. Check Intervals
- **High Priority Endpoints**: 60 seconds
- **Normal Endpoints**: 300 seconds (5 min)
- **Low Priority**: 900 seconds (15 min)

### 2. Logging
Always log monitoring results:
```powershell
# Create log rotation config
/var/log/api-monitor/*.log {
    daily
    rotate 30
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
}
```

### 3. Error Handling
Set up alerts for monitoring failures:
- Alert if monitoring command fails
- Alert if database connection lost
- Alert if too many endpoints failing

### 4. Performance Optimization
```powershell
# Monitor database size
docker exec -it api-monitor-saas-postgres-1 psql -U appuser -d apimon -c "
SELECT 
    pg_size_pretty(pg_database_size('apimon')) as db_size,
    (SELECT COUNT(*) FROM monitoring_results) as total_results;
"

# Clean old results monthly
docker exec -it api-monitor-saas-symfony-1 php bin/console app:monitoring:cleanup --days=30
```

---

## Verification

### Check Cron is Running
```powershell
# Linux
ps aux | grep cron

# Windows
Get-ScheduledTask | Where-Object {$_.TaskName -like "*API Monitor*"}

# Docker
docker logs -f api-monitor-saas-monitoring-cron-1
```

### Verify Monitoring Activity
```powershell
# Check recent monitoring results
docker exec -it api-monitor-saas-postgres-1 psql -U appuser -d apimon -c "
SELECT 
    COUNT(*) as total_checks,
    COUNT(*) FILTER (WHERE checked_at > NOW() - INTERVAL '1 hour') as last_hour,
    COUNT(*) FILTER (WHERE checked_at > NOW() - INTERVAL '1 day') as last_day
FROM monitoring_results;
"

# Check last 10 results
docker exec -it api-monitor-saas-postgres-1 psql -U appuser -d apimon -c "
SELECT 
    checked_at,
    status_code,
    response_time,
    error_message IS NOT NULL as has_error
FROM monitoring_results
ORDER BY checked_at DESC
LIMIT 10;
"
```

---

## Recommended Setup

**For Development:**
```powershell
# Manual checks only
docker exec -it api-monitor-saas-symfony-1 php bin/console app:monitor:endpoints -a
```

**For Production (Linux):**
```bash
# Cron every minute for due checks
* * * * * docker exec api-monitor-saas-symfony-1 php bin/console app:monitor:endpoints -d
```

**For Production (Windows):**
- Task Scheduler running every 1 minute
- PowerShell script with logging
- Email notifications on failure

---

## Troubleshooting

### Cron Not Running
```bash
# Check cron service
sudo service cron status

# Restart cron
sudo service cron restart

# Check cron logs
grep CRON /var/log/syslog
```

### High CPU Usage
```powershell
# Check concurrent checks
docker stats api-monitor-saas-symfony-1

# Reduce frequency or optimize queries
```

### Database Growing Too Large
```powershell
# Clean old results (keep last 30 days)
docker exec -it api-monitor-saas-symfony-1 php bin/console app:monitoring:cleanup --days=30

# Set up automatic cleanup in cron
```
