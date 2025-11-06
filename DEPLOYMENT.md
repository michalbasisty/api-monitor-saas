# Production Deployment Guide

## Pre-Deployment Checklist

- [ ] All features tested locally
- [ ] Database migrations working
- [ ] Monitoring automation configured
- [ ] SSL certificates ready
- [ ] Domain name configured
- [ ] Email service configured (optional)
- [ ] Backup strategy planned
- [ ] Environment variables secured

---

## Deployment Options

### Option 1: VPS/Cloud Server (Recommended)
- DigitalOcean, Linode, AWS EC2, Azure VM
- Full control, scalable
- **Cost**: $5-20/month

### Option 2: Platform as a Service
- Heroku, Railway, Render
- Easier setup, less control
- **Cost**: $7-25/month

### Option 3: Kubernetes
- For large scale deployments
- **Cost**: Variable

---

## Option 1: VPS Deployment (Ubuntu 22.04)

### Step 1: Server Setup

#### 1.1 Create Server
1. Create Ubuntu 22.04 VPS (minimum 2GB RAM)
2. Note down IP address
3. Set up SSH access

#### 1.2 Initial Server Configuration
```bash
# SSH into server
ssh root@your-server-ip

# Update system
apt update && apt upgrade -y

# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sh get-docker.sh

# Install Docker Compose
apt install docker-compose -y

# Create app user
adduser apimonitor
usermod -aG docker apimonitor
usermod -aG sudo apimonitor

# Switch to app user
su - apimonitor
```

### Step 2: Deploy Application

#### 2.1 Clone/Upload Code
```bash
# Create app directory
mkdir -p /home/apimonitor/app
cd /home/apimonitor/app

# Upload files (use SCP, SFTP, or Git)
# Example with Git:
git clone https://github.com/your-repo/api-monitor.git .

# Or upload via SCP from local machine:
# scp -r "f:\projects\Micro-SaaS for API Performance Monitoring\api-monitor-saas\*" apimonitor@your-server-ip:/home/apimonitor/app/
```

#### 2.2 Configure Environment
```bash
# Copy environment template
cp .env.template .env

# Edit environment variables
nano .env
```

Update `.env`:
```env
POSTGRES_USER=apimonitor_prod
POSTGRES_PASSWORD=CHANGE_THIS_SECURE_PASSWORD
POSTGRES_DB=apimonitor_prod
APP_SECRET=CHANGE_THIS_TO_RANDOM_STRING
```

#### 2.3 Create Production Docker Compose
Create `docker-compose.prod.yml`:
```yaml
version: '3.8'

services:
  angular:
    build: ./angular
    restart: unless-stopped
    environment:
      - NODE_ENV=production
    depends_on:
      - symfony
    networks:
      - webnet

  symfony:
    build: ./symfony
    restart: unless-stopped
    environment:
      - APP_ENV=prod
      - APP_DEBUG=0
      - APP_SECRET=${APP_SECRET}
      - DATABASE_URL=postgresql://${POSTGRES_USER}:${POSTGRES_PASSWORD}@postgres:5432/${POSTGRES_DB}
      - REDIS_HOST=redis
      - REDIS_PORT=6379
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_started
    networks:
      - webnet

  postgres:
    image: postgres:15
    restart: unless-stopped
    environment:
      POSTGRES_USER: ${POSTGRES_USER}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
      POSTGRES_DB: ${POSTGRES_DB}
    volumes:
      - ./postgres/init.sql:/docker-entrypoint-initdb.d/init.sql
      - pgdata:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${POSTGRES_USER} -d ${POSTGRES_DB}"]
      interval: 5s
      timeout: 5s
      retries: 5
    networks:
      - webnet

  redis:
    image: redis:7
    restart: unless-stopped
    networks:
      - webnet

  nginx-proxy:
    image: nginx:alpine
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx/nginx.conf:/etc/nginx/nginx.conf
      - ./nginx/ssl:/etc/nginx/ssl
    depends_on:
      - angular
    networks:
      - webnet

volumes:
  pgdata:

networks:
  webnet:
    driver: bridge
```

#### 2.4 Build and Start
```bash
# Build images
docker-compose -f docker-compose.prod.yml build

# Start services
docker-compose -f docker-compose.prod.yml up -d

# Check status
docker-compose -f docker-compose.prod.yml ps

# View logs
docker-compose -f docker-compose.prod.yml logs -f
```

### Step 3: SSL/HTTPS Setup

#### 3.1 Install Certbot
```bash
sudo apt install certbot python3-certbot-nginx -y
```

#### 3.2 Get SSL Certificate
```bash
# Stop nginx temporarily
docker-compose -f docker-compose.prod.yml stop nginx-proxy

# Get certificate
sudo certbot certonly --standalone -d yourdomain.com -d www.yourdomain.com

# Restart nginx
docker-compose -f docker-compose.prod.yml start nginx-proxy
```

#### 3.3 Configure Nginx for HTTPS
Create `nginx/nginx-ssl.conf`:
```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;

    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    location / {
        proxy_pass http://angular;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### Step 4: Monitoring Automation

#### 4.1 Set Up Cron
```bash
# Edit crontab
crontab -e

# Add monitoring job
* * * * * docker exec api-monitor-saas-symfony-1 php bin/console app:monitor:endpoints -d >> /var/log/api-monitor.log 2>&1
```

#### 4.2 Create Log Rotation
```bash
sudo nano /etc/logrotate.d/api-monitor

# Add:
/var/log/api-monitor.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 apimonitor apimonitor
}
```

### Step 5: Firewall Configuration

```bash
# Enable firewall
sudo ufw enable

# Allow SSH
sudo ufw allow 22

# Allow HTTP/HTTPS
sudo ufw allow 80
sudo ufw allow 443

# Check status
sudo ufw status
```

### Step 6: Database Backups

#### 6.1 Create Backup Script
```bash
mkdir -p /home/apimonitor/backups
nano /home/apimonitor/backup.sh
```

Add:
```bash
#!/bin/bash
BACKUP_DIR="/home/apimonitor/backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/apimonitor_$TIMESTAMP.sql"

docker exec api-monitor-saas-postgres-1 pg_dump -U apimonitor_prod apimonitor_prod > $BACKUP_FILE

# Compress
gzip $BACKUP_FILE

# Keep only last 7 days
find $BACKUP_DIR -name "*.sql.gz" -mtime +7 -delete

echo "Backup completed: $BACKUP_FILE.gz"
```

```bash
chmod +x /home/apimonitor/backup.sh
```

#### 6.2 Schedule Backups
```bash
crontab -e

# Add daily backup at 3 AM
0 3 * * * /home/apimonitor/backup.sh >> /var/log/backup.log 2>&1
```

---

## Option 2: Docker Hub + Cloud Deployment

### Step 1: Push Images to Docker Hub

```bash
# Login to Docker Hub
docker login

# Tag images
docker tag api-monitor-saas-angular yourusername/api-monitor-angular:latest
docker tag api-monitor-saas-symfony yourusername/api-monitor-symfony:latest

# Push images
docker push yourusername/api-monitor-angular:latest
docker push yourusername/api-monitor-symfony:latest
```

### Step 2: Deploy on Cloud Platform

Use simplified `docker-compose.yml`:
```yaml
version: '3.8'

services:
  angular:
    image: yourusername/api-monitor-angular:latest
    ports:
      - "80:80"
    environment:
      - NODE_ENV=production
    depends_on:
      - symfony

  symfony:
    image: yourusername/api-monitor-symfony:latest
    environment:
      - APP_ENV=prod
      - DATABASE_URL=${DATABASE_URL}
      - REDIS_HOST=redis
    depends_on:
      - postgres
      - redis

  postgres:
    image: postgres:15
    environment:
      - POSTGRES_PASSWORD=${POSTGRES_PASSWORD}
    volumes:
      - pgdata:/var/lib/postgresql/data

  redis:
    image: redis:7

volumes:
  pgdata:
```

---

## Post-Deployment

### 1. Health Checks
```bash
# Check all services
docker-compose -f docker-compose.prod.yml ps

# Check application
curl https://yourdomain.com

# Check API
curl https://yourdomain.com/api/health
```

### 2. Create First User
```bash
# Via API
curl -X POST https://yourdomain.com/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@yourdomain.com","password":"SecurePassword123"}'
```

### 3. Monitor Performance
```bash
# Check Docker stats
docker stats

# Check logs
docker-compose -f docker-compose.prod.yml logs -f --tail=100

# Check database size
docker exec api-monitor-saas-postgres-1 psql -U apimonitor_prod -d apimonitor_prod -c "SELECT pg_size_pretty(pg_database_size('apimonitor_prod'));"
```

### 4. Set Up Monitoring Alerts

Use services like:
- **UptimeRobot**: Monitor your domain uptime
- **Sentry**: Error tracking
- **New Relic**: Performance monitoring

---

## Scaling Considerations

### Horizontal Scaling
```bash
# Scale Symfony workers
docker-compose -f docker-compose.prod.yml up -d --scale symfony=3
```

### Database Optimization
```sql
-- Add indexes for performance
CREATE INDEX idx_monitoring_endpoint_checked ON monitoring_results(endpoint_id, checked_at);
CREATE INDEX idx_alerts_active ON alerts(is_active) WHERE is_active = true;
```

### Caching
Configure Redis caching in Symfony for better performance.

---

## Maintenance

### Update Application
```bash
# Pull latest code
git pull

# Rebuild
docker-compose -f docker-compose.prod.yml build

# Restart with zero downtime
docker-compose -f docker-compose.prod.yml up -d --no-deps --build symfony
docker-compose -f docker-compose.prod.yml up -d --no-deps --build angular
```

### Clean Up
```bash
# Remove unused images
docker system prune -a

# Remove old monitoring results
docker exec api-monitor-saas-symfony-1 php bin/console app:monitoring:cleanup --days=30
```

---

## Estimated Costs

### Small Scale (< 100 endpoints)
- **VPS**: $10/month (2GB RAM, 1 vCPU)
- **Domain**: $12/year
- **SSL**: Free (Let's Encrypt)
- **Total**: ~$11/month

### Medium Scale (100-1000 endpoints)
- **VPS**: $20/month (4GB RAM, 2 vCPU)
- **Domain**: $12/year
- **Backups**: $5/month
- **Total**: ~$26/month

### Large Scale (1000+ endpoints)
- **VPS**: $40+/month (8GB+ RAM, 4+ vCPU)
- **Managed Database**: $15/month
- **CDN**: $10/month
- **Total**: ~$66+/month

---

## Security Checklist

- [ ] SSL/HTTPS enabled
- [ ] Firewall configured
- [ ] Strong database password
- [ ] APP_SECRET is random and secure
- [ ] DEBUG mode disabled in production
- [ ] Database not exposed to public
- [ ] Regular backups automated
- [ ] Fail2ban installed (optional)
- [ ] SSH key-only authentication
- [ ] Docker daemon secured

---

## Support & Monitoring

### Log Locations
- Application: `docker-compose logs`
- Monitoring Cron: `/var/log/api-monitor.log`
- Nginx: `/var/log/nginx/`
- System: `/var/log/syslog`

### Useful Commands
```bash
# Restart all services
docker-compose -f docker-compose.prod.yml restart

# View resource usage
docker stats

# Database console
docker exec -it api-monitor-saas-postgres-1 psql -U apimonitor_prod -d apimonitor_prod

# Clear cache
docker exec api-monitor-saas-symfony-1 php bin/console cache:clear
```
