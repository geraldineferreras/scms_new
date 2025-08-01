<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class CORS_hook {
    
    public function handle_cors() {
        // Get the origin from the request
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        
        // Allow specific origins (add your frontend URLs here)
        $allowed_origins = [
            // Local development
            'http://localhost:3000',
            'http://localhost:3001',
            'http://localhost:8080',
            'http://localhost:5173',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:3001',
            'http://127.0.0.1:8080',
            'http://127.0.0.1:5173',
            'http://localhost',
            'http://127.0.0.1',
            
            // Vercel domains (add your actual Vercel domain here)
            'https://scms-updated.vercel.app',
            'https://scms-updated-git-main-geraldineferreras-projects.vercel.app',
            'https://scms-updated-djvbhlhpj-geraldineferreras-projects.vercel.app',

            
            // Production domains
            'https://scmsnew-production.up.railway.app'
        ];
        
        // Check if origin is allowed or use wildcard for development
        if (in_array($origin, $allowed_origins)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        } else {
            // For development, allow all origins
            header('Access-Control-Allow-Origin: *');
        }
        
        // Allow specific methods (including all attendance API methods)
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
        
        // Allow specific headers (including Authorization for JWT tokens)
        header('Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding, Authorization, X-Requested-With, X-API-Key, Cache-Control, Pragma, Origin, Accept, X-CSRF-TOKEN, X-XSRF-TOKEN, X-HTTP-Method-Override, X-Auth-Token');
        
        // Allow credentials (for cookies/sessions if needed)
        header('Access-Control-Allow-Credentials: true');
        
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Max-Age: 86400'); // 24 hours cache
            http_response_code(200);
            exit();
        }
    }
}