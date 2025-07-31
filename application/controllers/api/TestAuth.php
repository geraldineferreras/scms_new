<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class TestAuth extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->helper('response');
    }

    public function login() {
        // Simple test endpoint to verify basic functionality
        $response = [
            'status' => true,
            'message' => 'TestAuth login endpoint is working!',
            'timestamp' => date('Y-m-d H:i:s'),
            'server_info' => [
                'php_version' => phpversion(),
                'codeigniter_version' => CI_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
            ]
        ];

        $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode($response));
    }

    public function test_db() {
        // Test database connection
        try {
            $this->load->database();
            $query = $this->db->query('SELECT 1 as test');
            $result = $query->row();
            
            $response = [
                'status' => true,
                'message' => 'Database connection successful',
                'test_result' => $result->test
            ];
        } catch (Exception $e) {
            $response = [
                'status' => false,
                'message' => 'Database connection failed',
                'error' => $e->getMessage()
            ];
        }

        $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode($response));
    }

    public function test_config() {
        // Test configuration loading
        $config_info = [
            'base_url' => $this->config->item('base_url'),
            'index_page' => $this->config->item('index_page'),
            'uri_protocol' => $this->config->item('uri_protocol'),
            'environment' => ENVIRONMENT
        ];

        $response = [
            'status' => true,
            'message' => 'Configuration loaded successfully',
            'config' => $config_info
        ];

        $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode($response));
    }
} 