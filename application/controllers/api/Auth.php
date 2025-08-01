<?php
require_once(APPPATH . 'controllers/api/BaseController.php');

defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends BaseController {

    public function __construct() {
        parent::__construct();
        error_reporting(0);
        $this->load->model('User_model');
        $this->load->helper(['response', 'auth']);
        $this->load->library('Token_lib');
    }

    // Google OAuth Configuration
    private function getGoogleOAuthConfig() {
        return [
            'client_id' => '44239670641-jddshdurb1ub7jaiktnpv6piugs2go2c.apps.googleusercontent.com',
            'client_secret' => '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v',
            'redirect_uri' => 'https://scmsnew-production.up.railway.app/index.php/api/auth/google-callback',
            'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'userinfo_url' => 'https://www.googleapis.com/oauth2/v2/userinfo'
        ];
    }

    // Google OAuth Login - Returns authorization URL
    public function google_login() {
        try {
            $config = $this->getGoogleOAuthConfig();
            
            $params = [
                'client_id' => $config['client_id'],
                'redirect_uri' => $config['redirect_uri'],
                'response_type' => 'code',
                'scope' => 'email profile',
                'access_type' => 'offline',
                'prompt' => 'consent'
            ];
            
            $auth_url = $config['auth_url'] . '?' . http_build_query($params);
            
            $this->output
                ->set_status_header(200)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => true,
                    'message' => 'Google OAuth URL generated',
                    'data' => [
                        'auth_url' => $auth_url
                    ]
                ]));
                
        } catch (Exception $e) {
            log_message('error', 'Google OAuth URL generation error: ' . $e->getMessage());
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => false,
                    'message' => 'Failed to generate Google OAuth URL'
                ]));
        }
    }

    // Google OAuth Signup with Role Selection
    public function google_signup() {
        try {
            $data = json_decode(file_get_contents('php://input'));
            
            if (!$data || !isset($data->google_data) || !isset($data->selected_role)) {
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'status' => false,
                        'message' => 'Missing required data'
                    ]));
                return;
            }
            
            $google_data = $data->google_data;
            $selected_role = $data->selected_role;
            
            // Validate role
            $valid_roles = ['admin', 'teacher', 'student'];
            if (!in_array($selected_role, $valid_roles)) {
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'status' => false,
                        'message' => 'Invalid role selected'
                    ]));
                return;
            }
            
            // Check if user already exists
            $existing_user = $this->User_model->get_by_email($google_data->email);
            
            if ($existing_user) {
                // Update existing user's role if needed
                if ($existing_user['role'] !== $selected_role) {
                    $this->User_model->update($existing_user['user_id'], [
                        'role' => $selected_role,
                        'google_id' => $google_data->id,
                        'profile_picture' => $google_data->picture ?? $existing_user['profile_picture'],
                        'last_login' => date('Y-m-d H:i:s')
                    ]);
                    $existing_user = $this->User_model->get_by_id($existing_user['user_id']);
                }
                
                $user = $existing_user;
            } else {
                // Create new user with selected role
                $user_data = [
                    'email' => $google_data->email,
                    'full_name' => $google_data->name,
                    'password' => password_hash(uniqid(), PASSWORD_DEFAULT),
                    'role' => $selected_role,
                    'status' => 'active',
                    'google_id' => $google_data->id,
                    'profile_picture' => $google_data->picture ?? null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'last_login' => date('Y-m-d H:i:s')
                ];
                
                $this->db->insert('users', $user_data);
                $user_id = $this->db->insert_id();
                $user = $this->User_model->get_by_id($user_id);
            }
            
            // Generate JWT token
            $token_payload = [
                'user_id' => $user['user_id'],
                'role' => $user['role'],
                'email' => $user['email'],
                'full_name' => $user['full_name']
            ];
            
            $token = $this->token_lib->generate_token($token_payload);
            
            $response = [
                'status' => true,
                'message' => 'Account created successfully',
                'data' => [
                    'role' => $user['role'],
                    'user_id' => $user['user_id'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'status' => $user['status'],
                    'last_login' => date('Y-m-d H:i:s'),
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => $this->token_lib->get_expiration_time()
                ]
            ];
            
            $this->output
                ->set_status_header(200)
                ->set_content_type('application/json')
                ->set_output(json_encode($response));
                
        } catch (Exception $e) {
            log_message('error', 'Google signup error: ' . $e->getMessage());
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => false,
                    'message' => 'Account creation failed'
                ]));
        }
    }

    // Modified Google OAuth Callback to redirect to role selection for new users
    public function google_callback() {
        try {
            $code = $this->input->get('code');
            
            if (!$code) {
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'status' => false,
                        'message' => 'Authorization code not received'
                    ]));
                return;
            }
            
            $config = $this->getGoogleOAuthConfig();
            
            // Exchange code for access token
            $token_data = $this->exchangeCodeForToken($code, $config);
            
            if (!$token_data) {
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'status' => false,
                        'message' => 'Failed to exchange code for token'
                    ]));
                return;
            }
            
            // Get user info from Google
            $user_info = $this->getGoogleUserInfo($token_data['access_token']);
            
            if (!$user_info) {
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'status' => false,
                        'message' => 'Failed to get user info from Google'
                    ]));
                return;
            }
            
            // Check if user exists
            $user = $this->User_model->get_by_email($user_info['email']);
            
            if ($user) {
                // Existing user - proceed with normal login
                $update_data = [
                    'google_id' => $user_info['id'],
                    'profile_picture' => $user_info['picture'] ?? $user['profile_picture'],
                    'last_login' => date('Y-m-d H:i:s')
                ];
                
                $this->User_model->update($user['user_id'], $update_data);
                $user = $this->User_model->get_by_id($user['user_id']);
                
                // Generate JWT token for existing user
                $token_payload = [
                    'user_id' => $user['user_id'],
                    'role' => $user['role'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name']
                ];
                
                $token = $this->token_lib->generate_token($token_payload);
                
                // Redirect to frontend with token
                $frontend_url = 'https://your-frontend-domain.vercel.app'; // Update this
                $redirect_url = $frontend_url . '?token=' . urlencode($token) . '&user_id=' . $user['user_id'];
                
                redirect($redirect_url);
                
            } else {
                // New user - redirect to role selection
                $google_data = [
                    'id' => $user_info['id'],
                    'email' => $user_info['email'],
                    'name' => $user_info['name'],
                    'picture' => $user_info['picture'] ?? null
                ];
                
                $frontend_url = 'https://your-frontend-domain.vercel.app'; // Update this
                $redirect_url = $frontend_url . '/google_oauth_role_selection.html?google_data=' . urlencode(json_encode($google_data));
                
                redirect($redirect_url);
            }
            
        } catch (Exception $e) {
            log_message('error', 'Google OAuth callback error: ' . $e->getMessage());
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => false,
                    'message' => 'OAuth callback error'
                ]));
        }
    }

    // Exchange authorization code for access token
    private function exchangeCodeForToken($code, $config) {
        $post_data = [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $config['redirect_uri']
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $config['token_url']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            return json_decode($response, true);
        }
        
        log_message('error', 'Token exchange failed: ' . $response);
        return null;
    }

    // Get user info from Google
    private function getGoogleUserInfo($access_token) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->getGoogleOAuthConfig()['userinfo_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            return json_decode($response, true);
        }
        
        log_message('error', 'User info fetch failed: ' . $response);
        return null;
    }

    public function login() {
        try {
            // Enable error reporting for debugging
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            
            // Log the request
            log_message('debug', 'Login request received');
            
            $data = json_decode(file_get_contents('php://input'));

            if (json_last_error() !== JSON_ERROR_NONE) {
                log_message('error', 'JSON decode error: ' . json_last_error_msg());
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['status' => false, 'message' => 'Invalid JSON format']));
                return;
            }

            log_message('debug', 'Incoming login data: ' . json_encode($data));

            $email = isset($data->email) ? $data->email : null;
            $password = isset($data->password) ? $data->password : null;

            if (empty($email) || empty($password)) {
                log_message('error', 'Missing email or password');
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['status' => false, 'message' => 'Email and Password are required']));
                return;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                log_message('error', 'Invalid email format: ' . $email);
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['status' => false, 'message' => 'Invalid email format']));
                return;
            }

            // Test database connection
            try {
                $this->load->database();
                log_message('debug', 'Database connection successful');
            } catch (Exception $e) {
                log_message('error', 'Database connection failed: ' . $e->getMessage());
                $this->output
                    ->set_status_header(500)
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['status' => false, 'message' => 'Database connection error']));
                return;
            }

            $user = $this->User_model->get_by_email($email);
            
            if (!$user) {
                log_message('error', 'User not found: ' . $email);
                $this->output
                    ->set_status_header(401)
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['status' => false, 'message' => 'Invalid email or password']));
                return;
            }

            if (!password_verify($password, $user['password'])) {
                log_message('error', 'Invalid password for user: ' . $email);
                $this->output
                    ->set_status_header(401)
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['status' => false, 'message' => 'Invalid email or password']));
                return;
            }

            if ($user['status'] !== 'active') {
                log_message('error', 'Inactive account: ' . $email);
                $this->output
                    ->set_status_header(403)
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['status' => false, 'message' => 'Account is inactive. Please contact administrator.']));
                return;
            }

            // Update last_login
            try {
                $this->User_model->update($user['user_id'], [
                    'last_login' => date('Y-m-d H:i:s')
                ]);
                log_message('debug', 'Last login updated for user: ' . $email);
            } catch (Exception $e) {
                log_message('error', 'Failed to update last_login: ' . $e->getMessage());
                // Continue with login even if last_login update fails
            }

            // Generate JWT token
            try {
                $token_payload = [
                    'user_id' => $user['user_id'],
                    'role' => $user['role'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name']
                ];
                $token = $this->token_lib->generate_token($token_payload);
                log_message('debug', 'Token generated successfully for user: ' . $email);
            } catch (Exception $e) {
                log_message('error', 'Token generation failed: ' . $e->getMessage());
                $this->output
                    ->set_status_header(500)
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['status' => false, 'message' => 'Token generation error']));
                return;
            }

            $response = [
                'status' => true,
                'message' => 'Login successful',
                'data' => [
                    'role' => $user['role'],
                    'user_id' => $user['user_id'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'status' => $user['status'],
                    'last_login' => date('Y-m-d H:i:s'),
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => $this->token_lib->get_expiration_time()
                ]
            ];

            log_message('debug', 'Login successful for user: ' . $email);
            
            $this->output
                ->set_status_header(200)
                ->set_content_type('application/json')
                ->set_output(json_encode($response));
                
        } catch (Exception $e) {
            log_message('error', 'Login error: ' . $e->getMessage());
            log_message('error', 'Stack trace: ' . $e->getTraceAsString());
            
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => false, 
                    'message' => 'Internal server error',
                    'debug' => ENVIRONMENT === 'development' ? $e->getMessage() : null
                ]));
        }
    }

    public function register() {
        // Check if request is multipart/form-data or JSON
        $content_type = $this->input->server('CONTENT_TYPE');
        $is_multipart = strpos($content_type, 'multipart/form-data') !== false;
        
        if ($is_multipart) {
            // Handle multipart/form-data (with images)
            $this->register_with_images();
        } else {
            // Handle JSON request (existing code)
            $this->register_json();
        }
    }

    private function register_with_images() {
        try {
            // Get form data
            $role = $this->input->post('role');
            $full_name = $this->input->post('full_name');
            $email = $this->input->post('email');
            $password = $this->input->post('password');
            $contact_num = $this->input->post('contact_num');
            $address = $this->input->post('address');
            $program = $this->input->post('program');
            $student_num = $this->input->post('student_num');
            $section_id = $this->input->post('section_id');
            $qr_code = $this->input->post('qr_code');

            // Debug logging
            log_message('debug', '=== REGISTER WITH IMAGES DEBUG ===');
            log_message('debug', 'Role: ' . $role);
            log_message('debug', 'Full Name: ' . $full_name);
            log_message('debug', 'Email: ' . $email);
            log_message('debug', 'Contact: ' . $contact_num);
            log_message('debug', 'Address: ' . $address);
            log_message('debug', 'Program: ' . $program);
            log_message('debug', 'Student Num: ' . $student_num);
            log_message('debug', 'Section ID: ' . $section_id);
            log_message('debug', 'QR Code: ' . $qr_code);
            log_message('debug', '================================');

            // Validate required fields
            if (empty($role) || empty($full_name) || empty($email) || empty($password)) {
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['status' => false, 'message' => 'Required fields are missing']));
                return;
            }

            // Check if email already exists
            $existing_user = $this->User_model->get_by_email($email);
            if ($existing_user) {
                $this->output
                    ->set_status_header(409)
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['status' => false, 'message' => 'User with this email already exists!']));
                return;
            }

            // Handle profile image upload
            $profile_pic_path = '';
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
                $profile_pic_path = $this->upload_image($_FILES['profile_pic'], 'profile');
            }

            // Handle cover image upload
            $cover_pic_path = '';
            if (isset($_FILES['cover_pic']) && $_FILES['cover_pic']['error'] == 0) {
                $cover_pic_path = $this->upload_image($_FILES['cover_pic'], 'cover');
            }

            // Prepare user data
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $user_id = generate_user_id(strtoupper(substr($role, 0, 3)));
            
            $user_data = [
                'user_id' => $user_id,
                'role' => $role,
                'full_name' => $full_name,
                'email' => $email,
                'password' => $hashed_password,
                'contact_num' => $contact_num,
                'address' => $address,
                'program' => $program,
                'profile_pic' => $profile_pic_path,
                'cover_pic' => $cover_pic_path,
                'status' => 'active',
                'last_login' => null
            ];

            // Add role-specific fields
            if ($role === 'student') {
                if (empty($student_num) || empty($qr_code)) {
                    $this->output
                        ->set_status_header(400)
                        ->set_content_type('application/json')
                        ->set_output(json_encode(['status' => false, 'message' => 'Student number and qr_code are required for student accounts.']));
                    return;
                }
                $user_data['student_num'] = $student_num;
                $user_data['qr_code'] = $qr_code;
                
                // Add section_id only if provided
                if (!empty($section_id)) {
                    $user_data['section_id'] = $section_id;
                }
            }

            // Debug final data
            log_message('debug', '=== FINAL USER DATA ===');
            log_message('debug', print_r($user_data, true));
            log_message('debug', '=====================');

            // Insert user into database
            if ($this->User_model->insert($user_data)) {
                $this->output
                    ->set_status_header(201)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'status' => true,
                        'message' => ucfirst($role) . ' registered successfully!',
                        'data' => [
                            'user_id' => $user_id,
                            'role' => $role,
                            'full_name' => $full_name,
                            'email' => $email,
                            'profile_pic' => $profile_pic_path,
                            'cover_pic' => $cover_pic_path
                        ]
                    ]));
            } else {
                $this->output
                    ->set_status_header(500)
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['status' => false, 'message' => ucfirst($role) . ' registration failed!']));
            }

        } catch (Exception $e) {
            log_message('error', 'Registration error: ' . $e->getMessage());
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'Registration failed: ' . $e->getMessage()]));
        }
    }

    private function upload_image($file, $type) {
        $upload_path = FCPATH . 'uploads/' . $type . '/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_path)) {
            mkdir($upload_path, 0755, true);
        }

        // Generate unique filename
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $type . '_' . uniqid() . '.' . $file_extension;
        $full_path = $upload_path . $filename;

        // Validate file type
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array(strtolower($file_extension), $allowed_types)) {
            throw new Exception('Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.');
        }

        // Validate file size (5MB max)
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('File size too large. Maximum 5MB allowed.');
        }

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $full_path)) {
            return 'uploads/' . $type . '/' . $filename;
        } else {
            throw new Exception('Failed to upload file');
        }
    }

    private function register_json() {
        $data = json_decode(file_get_contents('php://input'));

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'Invalid JSON format']));
            return;
        }

        log_message('debug', 'Incoming register data: ' . json_encode($data));
        
        // Debug: Log profile and cover pic data
        if (isset($data->profile_pic)) {
            log_message('debug', 'Profile pic received: ' . $data->profile_pic);
        }
        if (isset($data->cover_pic)) {
            log_message('debug', 'Cover pic received: ' . $data->cover_pic);
        }

        $role = isset($data->role) ? strtolower($data->role) : null;
        $full_name = isset($data->full_name) ? $data->full_name : null;
        $email = isset($data->email) ? $data->email : null;
        $password = isset($data->password) ? $data->password : null;
        $program = isset($data->program) ? $data->program : null;
        $contact_num = isset($data->contact_num) ? $data->contact_num : null;
        $address = isset($data->address) ? $data->address : null;
        $errors = [];

        if (empty($role)) {
            $errors[] = 'Role is required.';
        }
        if (empty($full_name)) {
            $errors[] = 'Full name is required.';
        }
        if (empty($email)) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format.';
        }
        if (empty($password)) {
            $errors[] = 'Password is required.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }
        if (empty($contact_num)) {
            $errors[] = 'Contact number is required.';
        }
        if (empty($address)) {
            $errors[] = 'Address is required.';
        }

        if (!empty($errors)) {
            $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => implode(' ', $errors)]));
            return;
        }

        // Check if user already exists
        $existing_user = $this->User_model->get_by_email($email);
        if ($existing_user) {
            $this->output
                ->set_status_header(409)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'User with this email already exists!']));
            return;
        }

        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $user_id = generate_user_id(strtoupper(substr($role, 0, 3)));
        $dataToInsert = [
            'user_id' => $user_id,
            'role' => $role,
            'full_name' => $full_name,
            'email' => $email,
            'password' => $hashed_password,
            'contact_num' => $contact_num,
            'address' => $address,
            'program' => $program,
            'status' => 'active',
            'last_login' => null,
            'profile_pic' => isset($data->profile_pic) ? $data->profile_pic : null,
            'cover_pic' => isset($data->cover_pic) ? $data->cover_pic : null
        ];

        // Student-specific fields
        if ($role === 'student') {
            if (empty($data->student_num) || empty($data->qr_code)) {
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['status' => false, 'message' => 'Student number and qr_code are required for student accounts.']));
                return;
            }
            $dataToInsert['student_num'] = $data->student_num;
            $dataToInsert['qr_code'] = $data->qr_code;
            
            // Add section_id only if provided
            if (!empty($data->section_id)) {
                $dataToInsert['section_id'] = $data->section_id;
            }
        }

        // Debug: Log the final data being inserted
        log_message('debug', 'Data to insert: ' . json_encode($dataToInsert));
        
        if ($this->User_model->insert($dataToInsert)) {
            $this->output
                ->set_status_header(201)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => true,
                    'message' => ucfirst($role) . ' registered successfully!',
                    'data' => ['user_id' => $user_id]
                ]));
        } else {
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => ucfirst($role) . ' registration failed!']));
        }
    }

    // Get all users by role
    public function get_users() {
        // Require authentication
        $user_data = require_auth($this);
        if (!$user_data) {
            return; // Error response already sent
        }
        
        $role = $this->input->get('role'); // admin, teacher, student
        
        if (empty($role)) {
            $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'Role parameter is required']));
            return;
        }

        $role = strtolower($role);
        $users = $this->User_model->get_all($role);

        $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status' => true,
                'message' => 'Users retrieved successfully',
                'data' => $users
            ]));
    }

    // Get user by ID
    public function get_user() {
        // Require authentication
        $user_data = require_auth($this);
        if (!$user_data) {
            return; // Error response already sent
        }
        
        $role = $this->input->get('role'); // admin, teacher, student
        $user_id = $this->input->get('user_id');
        
        if (empty($role) || empty($user_id)) {
            $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'Role and user_id parameters are required']));
            return;
        }

        $role = strtolower($role);
        $user = $this->User_model->get_by_id($user_id);
        if (!$user || $user['role'] !== $role) {
            $this->output
                ->set_status_header(404)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'User not found']));
            return;
        }

        $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status' => true,
                'message' => 'User retrieved successfully',
                'data' => $user
            ]));
    }

    // Update user
    public function update_user() {
        // Require authentication
        $user_data = require_auth($this);
        if (!$user_data) {
            return; // Error response already sent
        }
        
        // Check if this is a multipart form request (for file uploads)
        if ($this->input->method() === 'post' && !empty($_FILES)) {
            $this->update_user_with_images();
            return;
        }
        
        // Handle JSON request
        $this->update_user_json();
    }
    
    private function update_user_with_images() {
        try {
            // Get form data
            $role = $this->input->post('role');
            $user_id = $this->input->post('user_id');
            
            if (empty($role) || empty($user_id)) {
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['status' => false, 'message' => 'Role and user_id are required']));
                return;
            }
            
            // Check if user exists
            $user = $this->User_model->get_by_id($user_id);
            if (!$user || $user['role'] !== $role) {
                $this->output
                    ->set_status_header(404)
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['status' => false, 'message' => 'User not found']));
                return;
            }
            
            $update_data = [];
            
            // Handle profile image upload
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
                try {
                    $profile_pic_path = $this->upload_image($_FILES['profile_pic'], 'profile');
                    $update_data['profile_pic'] = $profile_pic_path;
                } catch (Exception $e) {
                    $this->output
                        ->set_status_header(400)
                        ->set_content_type('application/json')
                        ->set_output(json_encode(['status' => false, 'message' => 'Profile image upload failed: ' . $e->getMessage()]));
                    return;
                }
            }
            
            // Handle cover image upload
            if (isset($_FILES['cover_pic']) && $_FILES['cover_pic']['error'] == 0) {
                try {
                    $cover_pic_path = $this->upload_image($_FILES['cover_pic'], 'cover');
                    $update_data['cover_pic'] = $cover_pic_path;
                } catch (Exception $e) {
                    $this->output
                        ->set_status_header(400)
                        ->set_content_type('application/json')
                        ->set_output(json_encode(['status' => false, 'message' => 'Cover image upload failed: ' . $e->getMessage()]));
                    return;
                }
            }
            
            // Handle other form fields
            if ($this->input->post('full_name')) $update_data['full_name'] = $this->input->post('full_name');
            if ($this->input->post('email')) $update_data['email'] = $this->input->post('email');
            if ($this->input->post('password')) $update_data['password'] = password_hash($this->input->post('password'), PASSWORD_BCRYPT);
            if ($this->input->post('program')) $update_data['program'] = $this->input->post('program');
            if ($this->input->post('contact_num')) $update_data['contact_num'] = $this->input->post('contact_num');
            if ($this->input->post('address')) $update_data['address'] = $this->input->post('address');
            
            // Status field with validation
            if ($this->input->post('status')) {
                $new_status = strtolower($this->input->post('status'));
                if ($new_status !== 'active' && $new_status !== 'inactive') {
                    $this->output
                        ->set_status_header(400)
                        ->set_content_type('application/json')
                        ->set_output(json_encode(['status' => false, 'message' => 'Status must be either "active" or "inactive"']));
                    return;
                }
                $update_data['status'] = $new_status;
            }
            
            // Student-specific fields
            if ($role === 'student') {
                if ($this->input->post('student_num')) $update_data['student_num'] = $this->input->post('student_num');
                if ($this->input->post('section_id')) $update_data['section_id'] = $this->input->post('section_id');
                if ($this->input->post('qr_code')) $update_data['qr_code'] = $this->input->post('qr_code');
            }
            
            if (empty($update_data)) {
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['status' => false, 'message' => 'No data provided for update']));
                return;
            }
            
            $success = $this->User_model->update($user_id, $update_data);
            if ($success) {
                $this->output
                    ->set_status_header(200)
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['status' => true, 'message' => 'User updated successfully']));
            } else {
                $this->output
                    ->set_status_header(500)
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['status' => false, 'message' => 'Failed to update user']));
            }
            
        } catch (Exception $e) {
            log_message('error', 'Update user error: ' . $e->getMessage());
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'Update failed: ' . $e->getMessage()]));
        }
    }
    
    private function update_user_json() {
        $data = json_decode(file_get_contents('php://input'));

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'Invalid JSON format']));
            return;
        }

        $role = isset($data->role) ? strtolower($data->role) : null;
        $user_id = isset($data->user_id) ? $data->user_id : null;
        
        if (empty($role) || empty($user_id)) {
            $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'Role and user_id are required']));
            return;
        }

        $update_data = [];
        // Common fields
        if (isset($data->full_name)) $update_data['full_name'] = $data->full_name;
        if (isset($data->email)) $update_data['email'] = $data->email;
        if (isset($data->password)) $update_data['password'] = password_hash($data->password, PASSWORD_BCRYPT);
        if (isset($data->program)) $update_data['program'] = $data->program;
        if (isset($data->contact_num)) $update_data['contact_num'] = $data->contact_num;
        if (isset($data->address)) $update_data['address'] = $data->address;
        if (isset($data->profile_pic)) $update_data['profile_pic'] = $data->profile_pic;
        if (isset($data->cover_pic)) $update_data['cover_pic'] = $data->cover_pic;
        
        // Status field with validation
        if (isset($data->status)) {
            $new_status = strtolower($data->status);
            if ($new_status !== 'active' && $new_status !== 'inactive') {
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['status' => false, 'message' => 'Status must be either "active" or "inactive"']));
                return;
            }
            $update_data['status'] = $new_status;
        }
        
        // Student-specific fields
        if ($role === 'student') {
            if (isset($data->student_num)) $update_data['student_num'] = $data->student_num;
            if (isset($data->section_id)) $update_data['section_id'] = $data->section_id;
            if (isset($data->qr_code)) $update_data['qr_code'] = $data->qr_code;
        }

        if (empty($update_data)) {
            $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'No data provided for update']));
            return;
        }

        $user = $this->User_model->get_by_id($user_id);
        if (!$user || $user['role'] !== $role) {
            $this->output
                ->set_status_header(404)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'User not found']));
            return;
        }

        $success = $this->User_model->update($user_id, $update_data);
        if ($success) {
            $this->output
                ->set_status_header(200)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => true, 'message' => 'User updated successfully']));
        } else {
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'Failed to update user']));
        }
    }

    // Delete user
    public function delete_user() {
        // Require authentication
        $user_data = require_auth($this);
        if (!$user_data) {
            return; // Error response already sent
        }
        
        $data = json_decode(file_get_contents('php://input'));

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'Invalid JSON format']));
            return;
        }

        $role = isset($data->role) ? strtolower($data->role) : null;
        $user_id = isset($data->user_id) ? $data->user_id : null;
        
        if (empty($role) || empty($user_id)) {
            $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'Role and user_id are required']));
            return;
        }

        $user = $this->User_model->get_by_id($user_id);
        if (!$user || $user['role'] !== $role) {
            $this->output
                ->set_status_header(404)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'User not found']));
            return;
        }

        $success = $this->User_model->delete($user_id);
        if ($success) {
            $this->output
                ->set_status_header(200)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => true, 'message' => 'User deleted successfully']));
        } else {
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'Failed to delete user']));
        }
    }

    // Admin method to change user status
    public function change_user_status() {
        // Require admin authentication
        $user_data = require_admin($this);
        if (!$user_data) {
            return; // Error response already sent
        }
        
        $data = json_decode(file_get_contents('php://input'));

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'Invalid JSON format']));
            return;
        }

        $target_role = isset($data->target_role) ? strtolower($data->target_role) : null;
        $user_id = isset($data->user_id) ? $data->user_id : null;
        $new_status = isset($data->status) ? strtolower($data->status) : null;
        
        if (empty($target_role) || empty($user_id) || empty($new_status)) {
            $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'Target role, user_id, and status are required']));
            return;
        }

        if ($new_status !== 'active' && $new_status !== 'inactive') {
            $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'Status must be either "active" or "inactive"']));
            return;
        }

        $user = $this->User_model->get_by_id($user_id);
        if (!$user || $user['role'] !== $target_role) {
            $this->output
                ->set_status_header(404)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'User not found']));
            return;
        }

        $success = $this->User_model->update($user_id, ['status' => $new_status]);
        if ($success) {
            $status_text = $new_status === 'active' ? 'activated' : 'deactivated';
            $this->output
                ->set_status_header(200)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => true, 'message' => ucfirst($target_role) . ' ' . $status_text . ' successfully']));
        } else {
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'Failed to change user status']));
        }
    }

    // Token refresh method
    public function refresh_token() {
        $token = $this->token_lib->get_token_from_header();
        
        if (!$token) {
            $this->output
                ->set_status_header(401)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'Token is required']));
            return;
        }
        
        $new_token = $this->token_lib->refresh_token($token);
        
        if (!$new_token) {
            $this->output
                ->set_status_header(401)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'Invalid or expired token']));
            return;
        }
        
        $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $new_token,
                    'token_type' => 'Bearer',
                    'expires_in' => $this->token_lib->get_expiration_time()
                ]
            ]));
    }

    // Validate token method
    public function validate_token() {
        $token = $this->token_lib->get_token_from_header();
        
        if (!$token) {
            $this->output
                ->set_status_header(401)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'Token is required']));
            return;
        }
        
        $payload = $this->token_lib->validate_token($token);
        
        if (!$payload) {
            $this->output
                ->set_status_header(401)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'Invalid or expired token']));
            return;
        }
        
        $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status' => true,
                'message' => 'Token is valid',
                'data' => [
                    'user_id' => $payload['user_id'],
                    'role' => $payload['role'],
                    'email' => $payload['email'],
                    'full_name' => $payload['full_name']
                ]
            ]));
    }

    // Logout method
    public function logout() {
        // With JWT, logout is typically handled client-side by removing the token
        // However, we can implement a token blacklist if needed for additional security
        // For now, we'll just return a success message
        
        $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status' => true, 
                'message' => 'Logout successful. Please remove the token from client storage.'
            ]));
    }

    // Handle OPTIONS preflight requests (CORS)
    public function options() {
        // The BaseController constructor handles CORS and exits for OPTIONS requests.
    }
}