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
    
    // ==================== ATTENDANCE SYSTEM ====================
    
    // Get teacher's assigned subjects
    public function teacher_subjects() {
        $user_data = $this->require_teacher($this);
        if (!$user_data) return;
        
        try {
            $subjects = $this->db->select('DISTINCT subjects.*')
                                ->from('classes')
                                ->join('subjects', 'classes.subject_id = subjects.id')
                                ->where('classes.teacher_id', $user_data['user_id'])
                                ->where('classes.is_active', 1)
                                ->get()->result_array();
            
            return json_response(true, 'Teacher subjects retrieved successfully', $subjects);
            
        } catch (Exception $e) {
            return json_response(false, 'Error retrieving subjects: ' . $e->getMessage(), null, 500);
        }
    }
    
    // Get sections by subject
    public function sections_by_subject($subject_id) {
        $user_data = $this->require_teacher($this);
        if (!$user_data) return;
        
        try {
            $sections = $this->db->select('DISTINCT sections.*')
                                ->from('classes')
                                ->join('sections', 'classes.section_id = sections.section_id')
                                ->where('classes.subject_id', $subject_id)
                                ->where('classes.teacher_id', $user_data['user_id'])
                                ->where('classes.is_active', 1)
                                ->get()->result_array();
            
            return json_response(true, 'Sections retrieved successfully', $sections);
            
        } catch (Exception $e) {
            return json_response(false, 'Error retrieving sections: ' . $e->getMessage(), null, 500);
        }
    }
    
    // Get classes by subject and section
    public function classes_by_subject_section($subject_id, $section_id) {
        $user_data = $this->require_teacher($this);
        if (!$user_data) return;
        
        try {
            $classes = $this->db->select('classes.*, subjects.subject_name, sections.section_name')
                               ->from('classes')
                               ->join('subjects', 'classes.subject_id = subjects.id')
                               ->join('sections', 'classes.section_id = sections.section_id')
                               ->where('classes.subject_id', $subject_id)
                               ->where('classes.section_id', $section_id)
                               ->where('classes.teacher_id', $user_data['user_id'])
                               ->where('classes.is_active', 1)
                               ->get()->result_array();
            
            return json_response(true, 'Classes retrieved successfully', $classes);
            
        } catch (Exception $e) {
            return json_response(false, 'Error retrieving classes: ' . $e->getMessage(), null, 500);
        }
    }
    
    // Get students by class
    public function students_by_class($class_id) {
        $user_data = $this->require_teacher($this);
        if (!$user_data) return;
        
        try {
            // Verify teacher owns this class
            $class = $this->db->where('class_id', $class_id)
                             ->where('teacher_id', $user_data['user_id'])
                             ->get('classes')->row_array();
            
            if (!$class) {
                return json_response(false, 'Class not found or access denied', null, 404);
            }
            
            // Get students enrolled in this class
            $students = $this->db->select('users.*, sections.section_name')
                               ->from('users')
                               ->join('sections', 'users.section_id = sections.section_id')
                               ->where('users.role', 'student')
                               ->where('users.section_id', $class['section_id'])
                               ->where('users.status', 'active')
                               ->get()->result_array();
            
            return json_response(true, 'Students retrieved successfully', $students);
            
        } catch (Exception $e) {
            return json_response(false, 'Error retrieving students: ' . $e->getMessage(), null, 500);
        }
    }
    
    // Record attendance manually
    public function attendance_record() {
        $user_data = $this->require_teacher($this);
        if (!$user_data) return;
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['student_id']) || !isset($data['class_id']) || !isset($data['date'])) {
            return json_response(false, 'Missing required data: student_id, class_id, date', null, 400);
        }
        
        try {
            // Verify teacher owns this class
            $class = $this->db->where('class_id', $data['class_id'])
                             ->where('teacher_id', $user_data['user_id'])
                             ->get('classes')->row_array();
            
            if (!$class) {
                return json_response(false, 'Class not found or access denied', null, 404);
            }
            
            // Check if attendance already exists
            $existing = $this->db->where('student_id', $data['student_id'])
                               ->where('class_id', $data['class_id'])
                               ->where('date', $data['date'])
                               ->get('attendance')->row_array();
            
            if ($existing) {
                return json_response(false, 'Attendance already recorded for this student on this date', null, 409);
            }
            
            // Determine status based on time
            $status = 'present';
            if (isset($data['time_in'])) {
                // You can add logic here to determine if student is late
                // For now, we'll use the provided status or default to 'present'
                $status = isset($data['status']) ? $data['status'] : 'present';
            }
            
            $attendance_data = [
                'student_id' => $data['student_id'],
                'class_id' => $data['class_id'],
                'date' => $data['date'],
                'time_in' => isset($data['time_in']) ? $data['time_in'] : date('H:i:s'),
                'status' => $status
            ];
            
            $this->db->insert('attendance', $attendance_data);
            $attendance_id = $this->db->insert_id();
            
            return json_response(true, 'Attendance recorded successfully', [
                'attendance_id' => $attendance_id,
                'status' => $status
            ]);
            
        } catch (Exception $e) {
            return json_response(false, 'Error recording attendance: ' . $e->getMessage(), null, 500);
        }
    }
    
    // QR Code scanning
    public function attendance_qr_scan() {
        $user_data = $this->require_teacher($this);
        if (!$user_data) return;
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['qr_data']) || !isset($data['class_id'])) {
            return json_response(false, 'Missing required data: qr_data, class_id', null, 400);
        }
        
        try {
            // Verify teacher owns this class
            $class = $this->db->where('class_id', $data['class_id'])
                             ->where('teacher_id', $user_data['user_id'])
                             ->get('classes')->row_array();
            
            if (!$class) {
                return json_response(false, 'Class not found or access denied', null, 404);
            }
            
            // Parse QR data
            $qr_lines = explode("\n", $data['qr_data']);
            $student_info = [];
            
            foreach ($qr_lines as $line) {
                if (strpos($line, 'IDNo:') !== false) {
                    $student_info['student_num'] = trim(str_replace('IDNo:', '', $line));
                } elseif (strpos($line, 'Full Name:') !== false) {
                    $student_info['full_name'] = trim(str_replace('Full Name:', '', $line));
                } elseif (strpos($line, 'Program:') !== false) {
                    $student_info['program'] = trim(str_replace('Program:', '', $line));
                }
            }
            
            if (!isset($student_info['student_num'])) {
                return json_response(false, 'Invalid QR code format', null, 400);
            }
            
            // Find student by student number
            $student = $this->db->where('student_num', $student_info['student_num'])
                               ->where('role', 'student')
                               ->where('status', 'active')
                               ->get('users')->row_array();
            
            if (!$student) {
                return json_response(false, 'Student not found', null, 404);
            }
            
            // Verify student is enrolled in this class (same section)
            if ($student['section_id'] != $class['section_id']) {
                return json_response(false, 'Student is not enrolled in this class', null, 403);
            }
            
            $today = date('Y-m-d');
            
            // Check if attendance already exists
            $existing = $this->db->where('student_id', $student['user_id'])
                               ->where('class_id', $data['class_id'])
                               ->where('date', $today)
                               ->get('attendance')->row_array();
            
            if ($existing) {
                return json_response(false, 'Attendance already recorded for this student today', null, 409);
            }
            
            // Record attendance
            $attendance_data = [
                'student_id' => $student['user_id'],
                'class_id' => $data['class_id'],
                'date' => $today,
                'time_in' => date('H:i:s'),
                'status' => 'present'
            ];
            
            $this->db->insert('attendance', $attendance_data);
            $attendance_id = $this->db->insert_id();
            
            return json_response(true, 'Attendance recorded successfully', [
                'attendance_id' => $attendance_id,
                'student_name' => $student['full_name'],
                'student_num' => $student['student_num'],
                'status' => 'present',
                'time_in' => $attendance_data['time_in']
            ]);
            
        } catch (Exception $e) {
            return json_response(false, 'Error processing QR scan: ' . $e->getMessage(), null, 500);
        }
    }
    
    // Manual attendance marking
    public function attendance_manual() {
        $user_data = $this->require_teacher($this);
        if (!$user_data) return;
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['student_id']) || !isset($data['class_id']) || !isset($data['status'])) {
            return json_response(false, 'Missing required data: student_id, class_id, status', null, 400);
        }
        
        try {
            // Verify teacher owns this class
            $class = $this->db->where('class_id', $data['class_id'])
                             ->where('teacher_id', $user_data['user_id'])
                             ->get('classes')->row_array();
            
            if (!$class) {
                return json_response(false, 'Class not found or access denied', null, 404);
            }
            
            $today = date('Y-m-d');
            
            // Check if attendance already exists
            $existing = $this->db->where('student_id', $data['student_id'])
                               ->where('class_id', $data['class_id'])
                               ->where('date', $today)
                               ->get('attendance')->row_array();
            
            if ($existing) {
                // Update existing attendance
                $this->db->where('attendance_id', $existing['attendance_id'])
                        ->update('attendance', [
                            'status' => $data['status'],
                            'time_in' => isset($data['time_in']) ? $data['time_in'] : date('H:i:s')
                        ]);
                
                return json_response(true, 'Attendance updated successfully', [
                    'attendance_id' => $existing['attendance_id'],
                    'status' => $data['status']
                ]);
            } else {
                // Create new attendance record
                $attendance_data = [
                    'student_id' => $data['student_id'],
                    'class_id' => $data['class_id'],
                    'date' => $today,
                    'time_in' => isset($data['time_in']) ? $data['time_in'] : date('H:i:s'),
                    'status' => $data['status']
                ];
                
                $this->db->insert('attendance', $attendance_data);
                $attendance_id = $this->db->insert_id();
                
                return json_response(true, 'Attendance recorded successfully', [
                    'attendance_id' => $attendance_id,
                    'status' => $data['status']
                ]);
            }
            
        } catch (Exception $e) {
            return json_response(false, 'Error recording attendance: ' . $e->getMessage(), null, 500);
        }
    }
    
    // Get attendance by class
    public function attendance_by_class($class_id) {
        $user_data = $this->require_teacher($this);
        if (!$user_data) return;
        
        try {
            // Verify teacher owns this class
            $class = $this->db->where('class_id', $class_id)
                             ->where('teacher_id', $user_data['user_id'])
                             ->get('classes')->row_array();
            
            if (!$class) {
                return json_response(false, 'Class not found or access denied', null, 404);
            }
            
            $date = $this->input->get('date') ? $this->input->get('date') : date('Y-m-d');
            
            $attendance = $this->db->select('attendance.*, users.full_name, users.student_num')
                                  ->from('attendance')
                                  ->join('users', 'attendance.student_id = users.user_id')
                                  ->where('attendance.class_id', $class_id)
                                  ->where('attendance.date', $date)
                                  ->get()->result_array();
            
            return json_response(true, 'Attendance retrieved successfully', $attendance);
            
        } catch (Exception $e) {
            return json_response(false, 'Error retrieving attendance: ' . $e->getMessage(), null, 500);
        }
    }
    
    // Get attendance by date
    public function attendance_by_date($date) {
        $user_data = $this->require_teacher($this);
        if (!$user_data) return;
        
        try {
            $attendance = $this->db->select('attendance.*, users.full_name, users.student_num, classes.class_id, subjects.subject_name, sections.section_name')
                                  ->from('attendance')
                                  ->join('users', 'attendance.student_id = users.user_id')
                                  ->join('classes', 'attendance.class_id = classes.class_id')
                                  ->join('subjects', 'classes.subject_id = subjects.id')
                                  ->join('sections', 'classes.section_id = sections.section_id')
                                  ->where('classes.teacher_id', $user_data['user_id'])
                                  ->where('attendance.date', $date)
                                  ->get()->result_array();
            
            return json_response(true, 'Attendance retrieved successfully', $attendance);
            
        } catch (Exception $e) {
            return json_response(false, 'Error retrieving attendance: ' . $e->getMessage(), null, 500);
        }
    }
    
    // Update attendance
    public function attendance_update($attendance_id) {
        $user_data = $this->require_teacher($this);
        if (!$user_data) return;
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            // Verify teacher owns this attendance record
            $attendance = $this->db->select('attendance.*, classes.teacher_id')
                                  ->from('attendance')
                                  ->join('classes', 'attendance.class_id = classes.class_id')
                                  ->where('attendance.attendance_id', $attendance_id)
                                  ->where('classes.teacher_id', $user_data['user_id'])
                                  ->get()->row_array();
            
            if (!$attendance) {
                return json_response(false, 'Attendance record not found or access denied', null, 404);
            }
            
            $update_data = [];
            if (isset($data['status'])) $update_data['status'] = $data['status'];
            if (isset($data['time_in'])) $update_data['time_in'] = $data['time_in'];
            
            $this->db->where('attendance_id', $attendance_id)
                    ->update('attendance', $update_data);
            
            return json_response(true, 'Attendance updated successfully', null);
            
        } catch (Exception $e) {
            return json_response(false, 'Error updating attendance: ' . $e->getMessage(), null, 500);
        }
    }
    
    // Delete attendance
    public function attendance_delete($attendance_id) {
        $user_data = $this->require_teacher($this);
        if (!$user_data) return;
        
        try {
            // Verify teacher owns this attendance record
            $attendance = $this->db->select('attendance.*, classes.teacher_id')
                                  ->from('attendance')
                                  ->join('classes', 'attendance.class_id = classes.class_id')
                                  ->where('attendance.attendance_id', $attendance_id)
                                  ->where('classes.teacher_id', $user_data['user_id'])
                                  ->get()->row_array();
            
            if (!$attendance) {
                return json_response(false, 'Attendance record not found or access denied', null, 404);
            }
            
            $this->db->where('attendance_id', $attendance_id)
                    ->delete('attendance');
            
            return json_response(true, 'Attendance deleted successfully', null);
            
        } catch (Exception $e) {
            return json_response(false, 'Error deleting attendance: ' . $e->getMessage(), null, 500);
        }
    }
    
    // Get attendance statistics
    public function attendance_stats() {
        $user_data = $this->require_teacher($this);
        if (!$user_data) return;
        
        try {
            $class_id = $this->input->get('class_id');
            $date = $this->input->get('date') ? $this->input->get('date') : date('Y-m-d');
            
            $query = $this->db->select('attendance.status, COUNT(*) as count')
                              ->from('attendance')
                              ->join('classes', 'attendance.class_id = classes.class_id')
                              ->where('classes.teacher_id', $user_data['user_id'])
                              ->where('attendance.date', $date);
            
            if ($class_id) {
                $query->where('attendance.class_id', $class_id);
            }
            
            $stats = $query->group_by('attendance.status')
                          ->get()->result_array();
            
            // Get total students in class
            $total_students = 0;
            if ($class_id) {
                $class = $this->db->where('class_id', $class_id)
                                 ->where('teacher_id', $user_data['user_id'])
                                 ->get('classes')->row_array();
                
                if ($class) {
                    $total_students = $this->db->where('section_id', $class['section_id'])
                                              ->where('role', 'student')
                                              ->where('status', 'active')
                                              ->count_all_results('users');
                }
            }
            
            $result = [
                'date' => $date,
                'class_id' => $class_id,
                'total_students' => $total_students,
                'stats' => $stats
            ];
            
            return json_response(true, 'Attendance statistics retrieved successfully', $result);
            
        } catch (Exception $e) {
            return json_response(false, 'Error retrieving statistics: ' . $e->getMessage(), null, 500);
        }
    }
    
    // Export attendance
    public function attendance_export() {
        $user_data = $this->require_teacher($this);
        if (!$user_data) return;
        
        try {
            $class_id = $this->input->get('class_id');
            $date = $this->input->get('date') ? $this->input->get('date') : date('Y-m-d');
            
            if (!$class_id) {
                return json_response(false, 'Class ID is required for export', null, 400);
            }
            
            // Verify teacher owns this class
            $class = $this->db->where('class_id', $class_id)
                             ->where('teacher_id', $user_data['user_id'])
                             ->get('classes')->row_array();
            
            if (!$class) {
                return json_response(false, 'Class not found or access denied', null, 404);
            }
            
            $attendance = $this->db->select('users.student_num, users.full_name, attendance.status, attendance.time_in')
                                  ->from('users')
                                  ->join('attendance', 'users.user_id = attendance.student_id', 'left')
                                  ->where('users.section_id', $class['section_id'])
                                  ->where('users.role', 'student')
                                  ->where('users.status', 'active')
                                  ->where('attendance.class_id', $class_id)
                                  ->where('attendance.date', $date)
                                  ->get()->result_array();
            
            return json_response(true, 'Attendance export data retrieved successfully', $attendance);
            
        } catch (Exception $e) {
            return json_response(false, 'Error exporting attendance: ' . $e->getMessage(), null, 500);
        }
    }
}
