<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class TeacherController extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->helper('response');
    }
    
    // Helper function to require teacher authentication
    private function require_teacher($controller) {
        $headers = getallheaders();
        $token = null;
        
        if (isset($headers['Authorization'])) {
            $token = str_replace('Bearer ', '', $headers['Authorization']);
        }
        
        if (!$token) {
            json_response(false, 'No token provided', null, 401);
            return false;
        }
        
        try {
            $this->load->library('Token_lib');
            $payload = $this->token_lib->validate_token($token);
            
            if (!$payload || $payload->role !== 'teacher') {
                json_response(false, 'Invalid token or insufficient permissions', null, 401);
                return false;
            }
            
            return [
                'user_id' => $payload->user_id,
                'email' => $payload->email,
                'role' => $payload->role
            ];
            
        } catch (Exception $e) {
            json_response(false, 'Token validation failed: ' . $e->getMessage(), null, 401);
            return false;
        }
    }
    
    // Teacher Classroom Management Methods
    public function classrooms_get() {
        $user_data = $this->require_teacher($this);
        if (!$user_data) return;
        
        try {
            $classrooms = $this->db->where('teacher_id', $user_data['user_id'])
                                  ->get('classrooms')->result_array();
            
            return json_response(true, 'Classrooms retrieved successfully', $classrooms);
            
        } catch (Exception $e) {
            return json_response(false, 'Error retrieving classrooms: ' . $e->getMessage(), null, 500);
        }
    }
    
    public function classrooms_post() {
        $user_data = $this->require_teacher($this);
        if (!$user_data) return;
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['classroom_name'])) {
            return json_response(false, 'Missing required data: classroom_name', null, 400);
        }
        
        try {
            $classroom_data = [
                'teacher_id' => $user_data['user_id'],
                'classroom_name' => $data['classroom_name'],
                'classroom_code' => $this->generate_classroom_code(),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->insert('classrooms', $classroom_data);
            $classroom_id = $this->db->insert_id();
            
            return json_response(true, 'Classroom created successfully', [
                'classroom_id' => $classroom_id,
                'classroom_code' => $classroom_data['classroom_code']
            ]);
            
        } catch (Exception $e) {
            return json_response(false, 'Error creating classroom: ' . $e->getMessage(), null, 500);
        }
    }
    
    private function generate_classroom_code() {
        return 'CR' . strtoupper(substr(md5(uniqid()), 0, 6));
    }
    
    public function classroom_get($classroom_id) {
        $user_data = $this->require_teacher($this);
        if (!$user_data) return;
        
        try {
            $classroom = $this->db->where('classroom_id', $classroom_id)
                                 ->where('teacher_id', $user_data['user_id'])
                                 ->get('classrooms')->row_array();
            
            if (!$classroom) {
                return json_response(false, 'Classroom not found', null, 404);
            }
            
            return json_response(true, 'Classroom retrieved successfully', $classroom);
            
        } catch (Exception $e) {
            return json_response(false, 'Error retrieving classroom: ' . $e->getMessage(), null, 500);
        }
    }
    
    public function classrooms_put($classroom_id) {
        $user_data = $this->require_teacher($this);
        if (!$user_data) return;
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['classroom_name'])) {
            return json_response(false, 'Missing required data: classroom_name', null, 400);
        }
        
        try {
            $this->db->where('classroom_id', $classroom_id)
                     ->where('teacher_id', $user_data['user_id'])
                     ->update('classrooms', [
                         'classroom_name' => $data['classroom_name'],
                         'updated_at' => date('Y-m-d H:i:s')
                     ]);
            
            return json_response(true, 'Classroom updated successfully', null);
            
        } catch (Exception $e) {
            return json_response(false, 'Error updating classroom: ' . $e->getMessage(), null, 500);
        }
    }
    
    public function classrooms_delete($classroom_id) {
        $user_data = $this->require_teacher($this);
        if (!$user_data) return;
        
        try {
            $this->db->where('classroom_id', $classroom_id)
                     ->where('teacher_id', $user_data['user_id'])
                     ->delete('classrooms');
            
            return json_response(true, 'Classroom deleted successfully', null);
            
        } catch (Exception $e) {
            return json_response(false, 'Error deleting classroom: ' . $e->getMessage(), null, 500);
        }
    }
    
    // Teacher Assigned Subjects and Sections
    public function assigned_subjects_get() {
        $user_data = $this->require_teacher($this);
        if (!$user_data) return;
        
        try {
            $subjects = $this->db->select('DISTINCT subjects.*')
                                ->from('classes')
                                ->join('subjects', 'classes.subject_id = subjects.id')
                                ->where('classes.teacher_id', $user_data['user_id'])
                                ->get()->result_array();
            
            return json_response(true, 'Assigned subjects retrieved successfully', $subjects);
            
        } catch (Exception $e) {
            return json_response(false, 'Error retrieving assigned subjects: ' . $e->getMessage(), null, 500);
        }
    }
    
    public function available_subjects_get() {
        $user_data = $this->require_teacher($this);
        if (!$user_data) return;
        
        try {
            $subjects = $this->db->get('subjects')->result_array();
            
            return json_response(true, 'Available subjects retrieved successfully', $subjects);
            
        } catch (Exception $e) {
            return json_response(false, 'Error retrieving available subjects: ' . $e->getMessage(), null, 500);
        }
    }
    
    public function available_sections_get($subject_id) {
        $user_data = $this->require_teacher($this);
        if (!$user_data) return;
        
        try {
            $sections = $this->db->select('sections.*')
                                ->from('sections')
                                ->where('sections.subject_id', $subject_id)
                                ->get()->result_array();
            
            return json_response(true, 'Available sections retrieved successfully', $sections);
            
        } catch (Exception $e) {
            return json_response(false, 'Error retrieving available sections: ' . $e->getMessage(), null, 500);
        }
    }
}
