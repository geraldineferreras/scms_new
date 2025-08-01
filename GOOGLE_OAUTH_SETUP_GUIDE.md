# Google OAuth Setup Guide for SCMS

## Overview
This guide will help you set up Google OAuth authentication for your SCMS (Student Class Management System) with Railway backend and Vercel frontend.

## Prerequisites
- Google Cloud Console account
- Railway backend deployed at: `https://scmsnew-production.up.railway.app/`
- Vercel account for frontend deployment

## Step 1: Google Cloud Console Setup

### 1.1 Create OAuth 2.0 Credentials
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Enable Google+ API and Google OAuth2 API
4. Go to "Credentials" → "Create Credentials" → "OAuth 2.0 Client IDs"
5. Configure OAuth consent screen:
   - User Type: External
   - App name: SCMS
   - User support email: your-email@domain.com
   - Developer contact information: your-email@domain.com

### 1.2 Configure OAuth Client
1. Application type: Web application
2. Name: SCMS OAuth Client
3. Authorized redirect URIs:
   - `https://scmsnew-production.up.railway.app/index.php/api/auth/google-callback`
   - `https://your-vercel-domain.vercel.app/auth/callback` (after Vercel deployment)

### 1.3 Save Credentials
- Client ID: `44239670641-jddshdurb1ub7jaiktnpv6piugs2go2c.apps.googleusercontent.com`
- Client Secret: `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v`

## Step 2: Database Setup

### 2.1 Run Database Migration
Execute the SQL file `add_google_oauth_fields.sql` in your Railway database:

```sql
-- Add Google OAuth fields to users table
ALTER TABLE users 
ADD COLUMN google_id VARCHAR(255) NULL,
ADD COLUMN profile_picture TEXT NULL;

-- Add index for better performance
CREATE INDEX idx_google_id ON users(google_id);
```

## Step 3: Backend Configuration

### 3.1 Update Auth Controller
The `application/controllers/api/Auth.php` file has been updated with:
- `google_login()` method - generates OAuth URL
- `google_callback()` method - handles OAuth response
- `exchangeCodeForToken()` method - exchanges code for access token
- `getGoogleUserInfo()` method - fetches user info from Google

### 3.2 Update Routes
The `application/config/routes.php` file includes new routes:
- `GET /api/auth/google-login` - Get OAuth URL
- `GET /api/auth/google-callback` - Handle OAuth callback

## Step 4: Frontend Setup

### 4.1 Update Auth Helper
The `auth_helper.js` file has been updated with:
- Google OAuth login method
- OAuth callback handling
- Updated base URL to Railway backend

### 4.2 Create Login Page
The `google_oauth_login.html` file provides:
- Traditional email/password login
- Google OAuth login button
- Error/success message handling
- Automatic OAuth callback processing

## Step 5: Vercel Deployment

### 5.1 Prepare Files for Vercel
Upload these files to Vercel:
- `google_oauth_login.html`
- `auth_helper.js`
- `vercel.json`
- Any other HTML/JS/CSS files

### 5.2 Update Frontend URL
In `application/controllers/api/Auth.php`, update line 158:
```php
$frontend_url = 'https://your-vercel-domain.vercel.app'; // Update this
```

### 5.3 Deploy to Vercel
1. Connect your GitHub repository to Vercel
2. Deploy the frontend files
3. Get your Vercel domain (e.g., `https://your-app.vercel.app`)

## Step 6: Testing

### 6.1 Test Traditional Login
1. Open your Vercel login page
2. Try logging in with existing email/password
3. Verify JWT token is received and stored

### 6.2 Test Google OAuth
1. Click "Continue with Google" button
2. Complete Google OAuth flow
3. Verify user is created/updated in database
4. Verify JWT token is received and stored

## Step 7: Security Considerations

### 7.1 Environment Variables (Recommended)
For production, move OAuth credentials to environment variables:

```php
// In Auth.php
private function getGoogleOAuthConfig() {
    return [
        'client_id' => getenv('GOOGLE_CLIENT_ID'),
        'client_secret' => getenv('GOOGLE_CLIENT_SECRET'),
        // ... other config
    ];
}
```

### 7.2 CORS Configuration
Ensure your Railway backend has proper CORS headers for your Vercel domain.

## Troubleshooting

### Common Issues

1. **"Invalid redirect URI" error**
   - Check that your redirect URI in Google Console matches exactly
   - Include protocol (https://) and trailing slash if needed

2. **"Authorization code not received"**
   - Verify the callback URL is accessible
   - Check that the route is properly configured

3. **"Failed to exchange code for token"**
   - Verify client ID and secret are correct
   - Check that the redirect URI matches exactly

4. **"Failed to get user info from Google"**
   - Verify the access token is valid
   - Check that the userinfo endpoint is accessible

### Debug Steps
1. Check Railway logs for backend errors
2. Check browser console for frontend errors
3. Verify all URLs are HTTPS
4. Test with Postman first before frontend integration

## API Endpoints

### Google OAuth Endpoints
- `GET /api/auth/google-login` - Get OAuth authorization URL
- `GET /api/auth/google-callback` - Handle OAuth callback

### Traditional Auth Endpoints
- `POST /api/auth/login` - Email/password login
- `POST /api/auth/logout` - Logout
- `POST /api/auth/register` - User registration

## Next Steps

1. **Customize User Roles**: Modify the default role assignment in `google_callback()`
2. **Add Profile Management**: Create endpoints to update user profile pictures
3. **Implement Refresh Tokens**: Add token refresh functionality
4. **Add More OAuth Providers**: Extend to support Facebook, GitHub, etc.

## Support

If you encounter issues:
1. Check the logs in Railway dashboard
2. Verify all URLs and credentials
3. Test each component individually
4. Ensure all files are properly deployed 