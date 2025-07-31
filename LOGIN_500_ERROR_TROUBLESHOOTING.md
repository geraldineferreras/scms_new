# Login API 500 Error Troubleshooting Guide

## Problem Description
You're getting a 500 Internal Server Error when testing the login endpoint in Postman:
- **URL**: `https://scmsnew-production.up.railway.app/api/login`
- **Method**: POST
- **Body**: JSON with email and password

## Potential Causes & Solutions

### 1. Database Connection Issues

**Symptoms**: 
- 500 error with no specific error message
- Database-related errors in logs

**Solutions**:
- Check if Railway environment variables are properly set
- Verify database credentials in production
- Test database connection using the test endpoint: `/api/test-auth/db`

### 2. Database Collation Issues

**Symptoms**:
- "Illegal mix of collations" errors in logs
- Database queries failing

**Solutions**:
- The logs show collation conflicts between `utf8mb4_unicode_ci` and `utf8mb4_general_ci`
- This is a database schema issue that needs to be fixed

### 3. Environment Configuration Issues

**Symptoms**:
- App not using production configuration
- Wrong database settings

**Solutions**:
- Check if `ENVIRONMENT` variable is set to 'production' on Railway
- Verify that production config files are being loaded

### 4. Missing Dependencies

**Symptoms**:
- Class not found errors
- Missing file errors

**Solutions**:
- Ensure all required files are present
- Check if all models and libraries are properly loaded

## Testing Steps

### Step 1: Test Basic Functionality
Open `test_login_endpoints.html` in your browser to run automated tests.

### Step 2: Test Individual Components

1. **Test Basic Endpoint**:
   ```
   GET https://scmsnew-production.up.railway.app/api/test-auth/login
   ```

2. **Test Database Connection**:
   ```
   GET https://scmsnew-production.up.railway.app/api/test-auth/db
   ```

3. **Test Configuration**:
   ```
   GET https://scmsnew-production.up.railway.app/api/test-auth/config
   ```

4. **Test Login Endpoint**:
   ```
   POST https://scmsnew-production.up.railway.app/api/login
   Content-Type: application/json
   
   {
     "email": "dhvsuadmin@example.com",
     "password": "123456789"
   }
   ```

### Step 3: Check Railway Logs

1. Go to your Railway dashboard
2. Navigate to your app
3. Check the logs for detailed error messages
4. Look for any PHP errors or exceptions

### Step 4: Verify Environment Variables

Ensure these environment variables are set in Railway:
- `ENVIRONMENT=production`
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`
- `BASE_URL`

## Debugging Tools Created

1. **`test_login_debug.php`**: Local debugging script
2. **`test_login_endpoints.html`**: Web-based testing tool
3. **Enhanced Auth Controller**: Added comprehensive error handling and logging
4. **Test Endpoints**: Added `/api/test-auth/*` endpoints for testing

## Quick Fixes to Try

### Fix 1: Check Railway Environment
```bash
# In Railway dashboard, verify these environment variables:
ENVIRONMENT=production
DB_HOST=your-db-host
DB_PORT=3306
DB_NAME=scms_db
DB_USER=your-db-user
DB_PASSWORD=your-db-password
BASE_URL=https://scmsnew-production.up.railway.app
```

### Fix 2: Database Collation Fix
If you have database access, run this SQL to fix collation issues:
```sql
ALTER DATABASE scms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE users CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE classroom_enrollments CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Repeat for other tables with collation issues
```

### Fix 3: Check File Permissions
Ensure all files have proper permissions on Railway.

### Fix 4: Verify Routes
Check if the routes are properly configured in `application/config/routes.php`.

## Expected Responses

### Successful Login Response:
```json
{
  "status": true,
  "message": "Login successful",
  "data": {
    "role": "admin",
    "user_id": "ADM123",
    "full_name": "Admin User",
    "email": "dhvsuadmin@example.com",
    "status": "active",
    "last_login": "2025-07-31 21:45:04",
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

### Error Response:
```json
{
  "status": false,
  "message": "Error description",
  "debug": "Additional debug info (development only)"
}
```

## Next Steps

1. **Run the test endpoints** to identify the specific issue
2. **Check Railway logs** for detailed error messages
3. **Verify environment variables** are correctly set
4. **Test database connection** using the test endpoint
5. **Check if the user exists** in the database with the provided credentials

## Contact Information

If the issue persists after trying these solutions:
1. Check Railway logs for specific error messages
2. Test the individual components using the provided test endpoints
3. Verify database connectivity and user credentials
4. Ensure all environment variables are properly configured

## Files Modified for Debugging

1. `application/controllers/api/Auth.php` - Enhanced error handling
2. `application/controllers/api/TestAuth.php` - Added test endpoints
3. `application/config/routes.php` - Added test routes
4. `test_login_endpoints.html` - Web-based testing tool
5. `test_login_debug.php` - Local debugging script 