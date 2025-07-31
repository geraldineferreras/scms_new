# Railway Deployment Guide for SCMS

This guide will help you deploy your CodeIgniter PHP application to Railway.

## Prerequisites

1. **Railway Account**: Sign up at [railway.app](https://railway.app)
2. **Git Repository**: Your code should be in a Git repository (GitHub, GitLab, etc.)
3. **Database**: You'll need a MySQL database (Railway provides this)

## Step 1: Prepare Your Database

### Option A: Use Railway's MySQL Service
1. Create a new Railway project
2. Add a MySQL service to your project
3. Railway will provide you with connection details

### Option B: Use External Database
- **PlanetScale** (recommended)
- **AWS RDS**
- **Google Cloud SQL**
- **DigitalOcean Managed MySQL**

## Step 2: Environment Variables Setup

In your Railway project dashboard, add these environment variables:

### Required Variables:
```
DB_HOST=your-database-host
DB_PORT=3306
DB_NAME=your-database-name
DB_USER=your-database-username
DB_PASSWORD=your-database-password
BASE_URL=https://your-app-name.railway.app
ENCRYPTION_KEY=your-32-character-encryption-key
ENVIRONMENT=production
```

### Optional Variables:
```
CI_ENV=production
```

## Step 3: Deploy to Railway

### Method 1: GitHub Integration (Recommended)
1. Connect your GitHub repository to Railway
2. Railway will automatically deploy when you push to main branch
3. Set up environment variables in Railway dashboard

### Method 2: Manual Deployment
1. Install Railway CLI: `npm install -g @railway/cli`
2. Login: `railway login`
3. Initialize: `railway init`
4. Deploy: `railway up`

## Step 4: Database Migration

After deployment, you need to set up your database:

1. **Export your local database**:
   ```bash
   mysqldump -u root -p scms_db > database_backup.sql
   ```

2. **Import to Railway database**:
   - Use Railway's database interface
   - Or connect via MySQL client with Railway's connection details

## Step 5: File Uploads

For file uploads to work in production:

1. **Create a persistent volume** in Railway for uploads
2. **Update upload paths** in your application to use the volume
3. **Set proper permissions** for the upload directory

## Step 6: SSL and Domain

1. Railway provides automatic SSL certificates
2. You can add a custom domain in Railway dashboard
3. Update your `BASE_URL` environment variable accordingly

## Troubleshooting

### Common Issues:

1. **Database Connection Failed**
   - Check environment variables
   - Verify database is running
   - Check firewall settings

2. **500 Internal Server Error**
   - Check Railway logs
   - Verify file permissions
   - Check CodeIgniter error logs

3. **File Upload Issues**
   - Check directory permissions
   - Verify upload path configuration
   - Check file size limits

### Checking Logs:
```bash
railway logs
```

## Security Considerations

1. **Environment Variables**: Never commit sensitive data to Git
2. **Database**: Use strong passwords
3. **SSL**: Always use HTTPS in production
4. **File Permissions**: Restrict access to sensitive files

## Performance Optimization

1. **Enable Caching**: Configure CodeIgniter caching
2. **Database Optimization**: Use proper indexes
3. **CDN**: Consider using a CDN for static assets
4. **Monitoring**: Use Railway's built-in monitoring

## Support

- **Railway Documentation**: [docs.railway.app](https://docs.railway.app)
- **CodeIgniter Documentation**: [codeigniter.com](https://codeigniter.com)
- **Railway Discord**: [discord.gg/railway](https://discord.gg/railway) 