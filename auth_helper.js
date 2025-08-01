// Authentication Helper for SCMS API
class AuthHelper {
    constructor() {
        this.baseURL = 'https://scmsnew-production.up.railway.app/index.php/api';
    }

    // Check if user is authenticated
    isAuthenticated() {
        return !!this.getToken();
    }

    // Login function
    async login(email, password) {
        try {
            const response = await fetch(`${this.baseURL}/auth/login`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ email, password })
            });

            const data = await response.json();

            if (data.status && data.data.token) {
                this.setToken(data.data.token);
                return {
                    success: true,
                    user: data.data
                };
            } else {
                return {
                    success: false,
                    message: data.message || 'Login failed'
                };
            }
        } catch (error) {
            return {
                success: false,
                message: error.message || 'Network error'
            };
        }
    }

    // Google OAuth Login
    async googleLogin() {
        try {
            // Get Google OAuth URL from backend
            const response = await fetch(`${this.baseURL}/auth/google-login`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();

            if (data.status && data.data.auth_url) {
                // Redirect to Google OAuth
                window.location.href = data.data.auth_url;
                return {
                    success: true,
                    message: 'Redirecting to Google OAuth'
                };
            } else {
                return {
                    success: false,
                    message: data.message || 'Failed to get Google OAuth URL'
                };
            }
        } catch (error) {
            return {
                success: false,
                message: error.message || 'Network error'
            };
        }
    }

    // Handle OAuth callback (called when user returns from Google)
    handleOAuthCallback() {
        const urlParams = new URLSearchParams(window.location.search);
        const token = urlParams.get('token');
        const userId = urlParams.get('user_id');

        if (token && userId) {
            // Store the token
            this.setToken(token);
            
            // Clear URL parameters
            const newUrl = window.location.pathname;
            window.history.replaceState({}, document.title, newUrl);
            
            return {
                success: true,
                message: 'Google OAuth login successful',
                user: { user_id: userId }
            };
        }

        return {
            success: false,
            message: 'No OAuth token received'
        };
    }

    // Logout function
    async logout() {
        try {
            const token = this.getToken();
            if (token) {
                const response = await fetch(`${this.baseURL}/auth/logout`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${token}`
                    }
                });
            }
        } catch (error) {
            console.error('Logout error:', error);
        } finally {
            // Always clear local storage
            this.clearToken();
            this.clearUser();
        }
    }

    // Set token in localStorage
    setToken(token) {
        localStorage.setItem('auth_token', token);
    }

    // Get token from localStorage
    getToken() {
        return localStorage.getItem('auth_token');
    }

    // Clear token from localStorage
    clearToken() {
        localStorage.removeItem('auth_token');
    }

    // Set user data in localStorage
    setUser(user) {
        localStorage.setItem('user_data', JSON.stringify(user));
    }

    // Get user data from localStorage
    getUser() {
        const userData = localStorage.getItem('user_data');
        return userData ? JSON.parse(userData) : null;
    }

    // Clear user data from localStorage
    clearUser() {
        localStorage.removeItem('user_data');
    }

    // Get authentication headers
    getAuthHeaders() {
        const token = this.getToken();
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(token && { 'Authorization': `Bearer ${token}` })
        };
    }

    // Make authenticated API request
    async makeAuthenticatedRequest(url, options = {}) {
        try {
            const response = await fetch(url, {
                ...options,
                headers: {
                    ...this.getAuthHeaders(),
                    ...options.headers
                }
            });

            const data = await response.json();
            return { success: response.ok, data, status: response.status };
        } catch (error) {
            return { success: false, data: { message: error.message }, status: 0 };
        }
    }

    // Check if token is expired
    isTokenExpired() {
        const token = this.getToken();
        if (!token) return true;

        try {
            const payload = JSON.parse(atob(token.split('.')[1]));
            const currentTime = Date.now() / 1000;
            return payload.exp < currentTime;
        } catch (error) {
            return true;
        }
    }

    // Refresh token if needed
    async refreshTokenIfNeeded() {
        if (this.isTokenExpired()) {
            await this.logout();
            return false;
        }
        return true;
    }
}

// Create global instance
const authHelper = new AuthHelper();

// Handle OAuth callback on page load
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('token')) {
        const result = authHelper.handleOAuthCallback();
        if (result.success) {
            // Redirect to dashboard or show success message
            console.log('OAuth login successful');
            // You can add your own success handling here
        }
    }
});