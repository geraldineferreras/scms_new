<?php
require_once(APPPATH . 'controllers/api/BaseController.php');

class TeacherController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        // Implement the index method
    }

    public function create()
    {
        // Implement the create method
    }

    public function update()
    {
        // Implement the update method
    }

    public function delete()
    {
        // Implement the delete method
    }

    // --- Teacher Classroom Management ---
    public function classrooms_get() {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        $this->load->model('Classroom_model');
        $this->load->model('Subject_model');
        $this->load->model('Section_model');
        
        // Get only classrooms created by this teacher
        $classrooms = $this->db->select('classrooms.*, users.full_name as teacher_name')
            ->from('classrooms')
            ->join('users', 'classrooms.teacher_id = users.user_id', 'left')
            ->where('classrooms.teacher_id', $user_data['user_id'])
            ->order_by('classrooms.created_at', 'DESC')
            ->get()->result_array();
        
        $result = [];
        foreach ($classrooms as $classroom) {
            // Fetch subject name
            $subject = $this->Subject_model->get_by_id($classroom['subject_id']);
            $subject_name = $subject ? $subject['subject_name'] : '';
            // Fetch section name
            $section = $this->Section_model->get_by_id($classroom['section_id']);
            $section_name = $section ? $section['section_name'] : '';
            // Count students in section (users table, role=student)
            $student_count = $this->db->where('section_id', $classroom['section_id'])->where('role', 'student')->count_all_results('users');
            $result[] = [
                'class_code' => $classroom['class_code'],
                'subject_name' => $subject_name,
                'section_name' => $section_name,
                'semester' => $classroom['semester'],
                'school_year' => $classroom['school_year'],
                'student_count' => $student_count
            ];
        }
        return json_response(true, 'Classrooms retrieved successfully', $result);
    }

    public function classroom_get($id) {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        $this->load->model('Classroom_model');
        $classroom = $this->Classroom_model->get_by_id($id);
        if (!$classroom) {
            return json_response(false, 'Classroom not found', null, 404);
        }
        return json_response(true, 'Classroom retrieved successfully', $classroom);
    }

    public function classrooms_post() {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        $this->load->model('Classroom_model');
        $data = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return json_response(false, 'Invalid JSON format', null, 400);
        }
        $required = ['subject_id', 'section_id', 'semester', 'school_year'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return json_response(false, "$field is required", null, 400);
            }
        }
        
        // Validate that the teacher is assigned to this subject and section
        $assigned_class = $this->db->select('classes.*')
            ->from('classes')
            ->where('classes.teacher_id', $user_data['user_id'])
            ->where('classes.subject_id', $data['subject_id'])
            ->where('classes.section_id', $data['section_id'])
            ->get()->row_array();
        
        if (!$assigned_class) {
            return json_response(false, 'You are not assigned to teach this subject and section combination', null, 403);
        }
        
        // Check if classroom already exists for this teacher, subject, section, semester, and school year
        $existing_classroom = $this->db->select('classrooms.*')
            ->from('classrooms')
            ->where('classrooms.teacher_id', $user_data['user_id'])
            ->where('classrooms.subject_id', $data['subject_id'])
            ->where('classrooms.section_id', $data['section_id'])
            ->where('classrooms.semester', $data['semester'])
            ->where('classrooms.school_year', $data['school_year'])
            ->get()->row_array();
        
        if ($existing_classroom) {
            return json_response(false, 'A classroom already exists for this subject, section, semester, and school year combination', null, 409);
        }
        
        $data['teacher_id'] = $user_data['user_id'];
        if (empty($data['title']) && !empty($data['custom_title'])) {
            $data['title'] = $data['custom_title'];
        }
        unset($data['custom_title']);
        $id = $this->Classroom_model->insert($data);
        if ($id) {
            // Fetch subject name
            $this->load->model('Subject_model');
            $subject = $this->Subject_model->get_by_id($data['subject_id']);
            $subject_name = $subject ? $subject['subject_name'] : '';
            // Fetch section name
            $this->load->model('Section_model');
            $section = $this->Section_model->get_by_id($data['section_id']);
            $section_name = $section ? $section['section_name'] : '';
            // Count students in section (users table, role=student)
            $student_count = $this->db->where('section_id', $data['section_id'])->where('role', 'student')->count_all_results('users');
            // Get class_code
            $classroom = $this->Classroom_model->get_by_id($id);
            $class_code = $classroom['class_code'];
            $response = [
                'class_code' => $class_code,
                'subject_name' => $subject_name,
                'section_name' => $section_name,
                'semester' => $data['semester'],
                'school_year' => $data['school_year'],
                'student_count' => $student_count
            ];
            return json_response(true, 'Classroom created successfully', $response, 201);
        } else {
            return json_response(false, 'Failed to create classroom', null, 500);
        }
    }

    public function classrooms_put($id) {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        $this->load->model('Classroom_model');
        $data = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return json_response(false, 'Invalid JSON format', null, 400);
        }
        $success = $this->Classroom_model->update($id, $data);
        if ($success) {
            return json_response(true, 'Classroom updated successfully');
        } else {
            return json_response(false, 'Failed to update classroom', null, 500);
        }
    }

    public function classrooms_delete($id) {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        $this->load->model('Classroom_model');
        $success = $this->Classroom_model->delete($id);
        if ($success) {
            return json_response(true, 'Classroom deleted successfully');
        } else {
            return json_response(false, 'Failed to delete classroom', null, 500);
        }
    }

    public function classroom_by_code_get($class_code) {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        $this->load->model('Classroom_model');
        $this->load->model('Subject_model');
        $this->load->model('Section_model');
        $classroom = $this->Classroom_model->get_by_code($class_code);
        if (!$classroom) {
            return json_response(false, 'Classroom not found', null, 404);
        }
        $subject = $this->Subject_model->get_by_id($classroom['subject_id']);
        $subject_name = $subject ? $subject['subject_name'] : '';
        $section = $this->Section_model->get_by_id($classroom['section_id']);
        $section_name = $section ? $section['section_name'] : '';
        $student_count = $this->db->where('section_id', $classroom['section_id'])->where('role', 'student')->count_all_results('users');
        $response = [
            'class_code' => $classroom['class_code'],
            'subject_name' => $subject_name,
            'section_name' => $section_name,
            'semester' => $classroom['semester'],
            'school_year' => $classroom['school_year'],
            'student_count' => $student_count
        ];
        return json_response(true, 'Classroom retrieved successfully', $response);
    }

    public function classrooms_put_by_code($class_code) {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        $this->load->model('Classroom_model');
        $data = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return json_response(false, 'Invalid JSON format', null, 400);
        }
        $classroom = $this->Classroom_model->get_by_code($class_code);
        if (!$classroom) {
            return json_response(false, 'Classroom not found', null, 404);
        }
        $success = $this->Classroom_model->update($classroom['id'], $data);
        if ($success) {
            return json_response(true, 'Classroom updated successfully');
        } else {
            return json_response(false, 'Failed to update classroom', null, 500);
        }
    }

    public function classrooms_delete_by_code($class_code) {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        $this->load->model('Classroom_model');
        $classroom = $this->Classroom_model->get_by_code($class_code);
        if (!$classroom) {
            return json_response(false, 'Classroom not found', null, 404);
        }
        $success = $this->Classroom_model->delete($classroom['id']);
        if ($success) {
            return json_response(true, 'Classroom deleted successfully');
        } else {
            return json_response(false, 'Failed to delete classroom', null, 500);
        }
    }

    public function classroom_stream_post($class_code) {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        $this->load->model('ClassroomStream_model');

        // Check if multipart/form-data with files and JSON
        if (isset($_FILES) && !empty($_FILES) && isset($_POST['data'])) {
            $data = json_decode($_POST['data'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return json_response(false, 'Invalid JSON format in data field', null, 400);
            }
            
            // Handle multiple file uploads
            $uploaded_files = [];
            $upload_path = FCPATH . 'uploads/announcement/';
            if (!is_dir($upload_path)) {
                mkdir($upload_path, 0755, true);
            }
            
            // Process each uploaded file
            foreach ($_FILES as $field_name => $file_data) {
                if ($file_data['error'] === UPLOAD_ERR_OK) {
                    $config['upload_path'] = $upload_path;
                    $config['allowed_types'] = 'gif|jpg|jpeg|png|webp|pdf|doc|docx|ppt|pptx|xls|xlsx|txt|zip|rar|mp4|mp3';
                    $config['max_size'] = 10240; // 10MB
                    $config['encrypt_name'] = false;
                    $config['remove_spaces'] = true;
                    $config['file_ext_tolower'] = true;
                    
                    // Get original filename and sanitize it
                    $original_name = $file_data['name'];
                    $file_extension = pathinfo($original_name, PATHINFO_EXTENSION);
                    $file_name_without_ext = pathinfo($original_name, PATHINFO_FILENAME);
                    
                    // Sanitize filename: keep Unicode characters, alphanumeric, dots, hyphens, underscores, and spaces
                    // Remove only potentially dangerous characters
                    $sanitized_name = preg_replace('/[^\p{L}\p{N}\s._-]/u', '', $file_name_without_ext);
                    $sanitized_name = trim($sanitized_name, '._-');
                    // Replace multiple spaces with single space
                    $sanitized_name = preg_replace('/\s+/', ' ', $sanitized_name);
                    
                    // If sanitized name is empty, use a default name
                    if (empty($sanitized_name)) {
                        $sanitized_name = 'file';
                    }
                    
                    // Check if file already exists and append number if necessary
                    $final_filename = $sanitized_name . '.' . $file_extension;
                    $counter = 1;
                    while (file_exists($upload_path . $final_filename)) {
                        $final_filename = $sanitized_name . '_' . $counter . '.' . $file_extension;
                        $counter++;
                    }
                    
                    $config['file_name'] = $final_filename;
                    $this->load->library('upload', $config);
                    $this->upload->initialize($config);
                    
                    if ($this->upload->do_upload($field_name)) {
                        $upload_data = $this->upload->data();
                        $file_path = 'uploads/announcement/' . $upload_data['file_name'];
                        $uploaded_files[] = [
                            'field_name' => $field_name,
                            'file_path' => $file_path,
                            'file_name' => $upload_data['file_name'],
                            'file_size' => $upload_data['file_size'],
                            'file_type' => $upload_data['file_type']
                        ];
                    } else {
                        $error = $this->upload->display_errors('', '');
                        return json_response(false, 'Upload failed for ' . $field_name . ': ' . $error, null, 400);
                    }
                }
            }
            
            // Store file information in data
            if (!empty($uploaded_files)) {
                $data['attachment_type'] = 'multiple';
                $data['attachment_url'] = json_encode($uploaded_files);
            }
        } else {
            // Fallback to JSON body (raw)
            $data = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return json_response(false, 'Invalid JSON format', null, 400);
            }
        }
        
        $required = ['content'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return json_response(false, "$field is required", null, 400);
            }
        }
        
        $insert_data = [
            'class_code' => $class_code,
            'user_id' => $user_data['user_id'],
            'title' => $data['title'] ?? null,
            'content' => $data['content'],
            'is_draft' => $data['is_draft'] ?? 0,
            'is_scheduled' => $data['is_scheduled'] ?? 0,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'allow_comments' => $data['allow_comments'] ?? 1,
            'attachment_type' => $data['attachment_type'] ?? null,
            'attachment_url' => $data['attachment_url'] ?? null
        ];
        
        if (!empty($data['student_ids'])) {
            $insert_data['student_ids'] = $data['student_ids'];
        }
        
        $id = $this->ClassroomStream_model->insert($insert_data);
        if ($id) {
            $post = $this->ClassroomStream_model->get_by_id($id);
            return json_response(true, 'Announcement posted successfully', $post, 201);
        } else {
            return json_response(false, 'Failed to post announcement', null, 500);
        }
    }

    public function classroom_stream_get($class_code) {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        $this->load->model('ClassroomStream_model');
        $posts = $this->ClassroomStream_model->get_stream_for_classroom_ui($class_code);
        return json_response(true, 'Stream posts retrieved successfully', $posts);
    }

    // Like a stream post
    public function classroom_stream_like_post($class_code, $stream_id) {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        $this->load->model('ClassroomStream_model');
        $post = $this->ClassroomStream_model->get_by_id($stream_id);
        if (!$post || $post['class_code'] !== $class_code) {
            return json_response(false, 'Stream post not found', null, 404);
        }
        $likes = json_decode($post['liked_by_user_ids'], true) ?: [];
        if (!in_array($user_data['user_id'], $likes)) {
            $likes[] = $user_data['user_id'];
            $this->db->where('id', $stream_id)->update('classroom_stream', [
                'liked_by_user_ids' => json_encode($likes)
            ]);
        }
        return json_response(true, 'Post liked successfully');
    }

    // Unlike a stream post
    public function classroom_stream_unlike_post($class_code, $stream_id) {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        $this->load->model('ClassroomStream_model');
        $post = $this->ClassroomStream_model->get_by_id($stream_id);
        if (!$post || $post['class_code'] !== $class_code) {
            return json_response(false, 'Stream post not found', null, 404);
        }
        $likes = json_decode($post['liked_by_user_ids'], true) ?: [];
        $likes = array_diff($likes, [$user_data['user_id']]);
        $this->db->where('id', $stream_id)->update('classroom_stream', [
            'liked_by_user_ids' => json_encode(array_values($likes))
        ]);
        return json_response(true, 'Post unliked successfully');
    }

    // Pin a stream post
    public function classroom_stream_pin_post($class_code, $stream_id) {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        $this->load->model('ClassroomStream_model');
        $post = $this->ClassroomStream_model->get_by_id($stream_id);
        if (!$post || $post['class_code'] !== $class_code) {
            return json_response(false, 'Stream post not found', null, 404);
        }
        $this->db->where('id', $stream_id)->update('classroom_stream', [
            'is_pinned' => 1
        ]);
        return json_response(true, 'Post pinned successfully');
    }

    // Unpin a stream post
    public function classroom_stream_unpin_post($class_code, $stream_id) {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        $this->load->model('ClassroomStream_model');
        $post = $this->ClassroomStream_model->get_by_id($stream_id);
        if (!$post || $post['class_code'] !== $class_code) {
            return json_response(false, 'Stream post not found', null, 404);
        }
        $this->db->where('id', $stream_id)->update('classroom_stream', [
            'is_pinned' => 0
        ]);
        return json_response(true, 'Post unpinned successfully');
    }

    public function classroom_stream_scheduled_get($class_code) {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        $this->load->model('ClassroomStream_model');
        $posts = $this->ClassroomStream_model->get_scheduled_for_classroom_ui($class_code);
        return json_response(true, 'Scheduled posts retrieved successfully', $posts);
    }

    public function classroom_stream_drafts_get($class_code) {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        $this->load->model('ClassroomStream_model');
        $posts = $this->ClassroomStream_model->get_drafts_for_classroom_ui($class_code);
        return json_response(true, 'Draft posts retrieved successfully', $posts);
    }

    // Update a draft by ID (can also publish by setting is_draft=0)
    public function classroom_stream_draft_put($class_code, $draft_id) {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        $this->load->model('ClassroomStream_model');
        $data = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return json_response(false, 'Invalid JSON format', null, 400);
        }
        $post = $this->ClassroomStream_model->get_by_id($draft_id);
        if (!$post || $post['class_code'] !== $class_code) {
            return json_response(false, 'Draft post not found', null, 404);
        }
        $success = $this->ClassroomStream_model->update($draft_id, $data);
        if ($success) {
            return json_response(true, 'Draft updated successfully');
        } else {
            return json_response(false, 'Failed to update draft', null, 500);
        }
    }

    // Get teacher's assigned subjects and sections from offerings management
    public function assigned_subjects_get() {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        
        $this->load->model('Class_model');
        $this->load->model('Subject_model');
        $this->load->model('Section_model');
        
        // Get all classes (offerings) assigned to this teacher
        $assigned_classes = $this->db->select('classes.*, subjects.subject_name, subjects.subject_code, sections.section_name')
            ->from('classes')
            ->join('subjects', 'classes.subject_id = subjects.id', 'left')
            ->join('sections', 'classes.section_id = sections.section_id', 'left')
            ->where('classes.teacher_id', $user_data['user_id'])
            ->get()->result_array();
        
        // Group by subject and section
        $subjects = [];
        $sections = [];
        
        foreach ($assigned_classes as $class) {
            $subject_key = $class['subject_id'];
            if (!isset($subjects[$subject_key])) {
                $subjects[$subject_key] = [
                    'id' => $class['subject_id'],
                    'name' => $class['subject_name'],
                    'code' => $class['subject_code'],
                    'sections' => []
                ];
            }
            
            $section_key = $class['section_id'];
            if (!isset($sections[$section_key])) {
                $sections[$section_key] = [
                    'id' => $class['section_id'],
                    'name' => $class['section_name']
                ];
            }
            
            // Add section to subject if not already added
            $section_exists = false;
            foreach ($subjects[$subject_key]['sections'] as $existing_section) {
                if ($existing_section['id'] == $class['section_id']) {
                    $section_exists = true;
                    break;
                }
            }
            
            if (!$section_exists) {
                $subjects[$subject_key]['sections'][] = [
                    'id' => $class['section_id'],
                    'name' => $class['section_name']
                ];
            }
        }
        
        $result = [
            'subjects' => array_values($subjects),
            'sections' => array_values($sections)
        ];
        
        return json_response(true, 'Teacher assigned subjects retrieved successfully', $result);
    }

    // Get available subjects for teacher (filtered by assigned subjects)
    public function available_subjects_get() {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        
        $this->load->model('Subject_model');
        
        // Get subjects assigned to this teacher from classes table
        $assigned_subject_ids = $this->db->select('DISTINCT(subject_id)')
            ->from('classes')
            ->where('teacher_id', $user_data['user_id'])
            ->get()->result_array();
        
        $subject_ids = array_column($assigned_subject_ids, 'subject_id');
        
        if (empty($subject_ids)) {
            return json_response(true, 'No subjects assigned to teacher', []);
        }
        
        // Get subject details
        $subjects = $this->db->select('id, subject_name, subject_code')
            ->from('subjects')
            ->where_in('id', $subject_ids)
            ->order_by('subject_name', 'ASC')
            ->get()->result_array();
        
        return json_response(true, 'Available subjects retrieved successfully', $subjects);
    }

    // Get available sections for a specific subject (filtered by teacher's assignments)
    public function available_sections_get($subject_id) {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        
        $this->load->model('Section_model');
        
        // Get sections assigned to this teacher for the specific subject
        $assigned_sections = $this->db->select('sections.section_id, sections.section_name')
            ->from('classes')
            ->join('sections', 'classes.section_id = sections.section_id', 'left')
            ->where('classes.teacher_id', $user_data['user_id'])
            ->where('classes.subject_id', $subject_id)
            ->get()->result_array();
        
        return json_response(true, 'Available sections retrieved successfully', $assigned_sections);
    }

    // Add a comment to a stream post
    public function classroom_stream_comment_post($class_code, $stream_id) {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        $this->load->model('ClassroomStream_model');
        $post = $this->ClassroomStream_model->get_by_id($stream_id);
        if (!$post || $post['class_code'] !== $class_code) {
            return json_response(false, 'Stream post not found', null, 404);
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($data['comment'])) {
            return json_response(false, 'Comment is required', null, 400);
        }
        $comment_id = $this->ClassroomStream_model->add_comment($stream_id, $user_data['user_id'], $data['comment']);
        if ($comment_id) {
            $comments = $this->ClassroomStream_model->get_comments($stream_id);
            return json_response(true, 'Comment added successfully', $comments);
        } else {
            return json_response(false, 'Failed to add comment', null, 500);
        }
    }

    // Get all comments for a stream post
    public function classroom_stream_comments_get($class_code, $stream_id) {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        $this->load->model('ClassroomStream_model');
        $post = $this->ClassroomStream_model->get_by_id($stream_id);
        if (!$post || $post['class_code'] !== $class_code) {
            return json_response(false, 'Stream post not found', null, 404);
        }
        $comments = $this->ClassroomStream_model->get_comments($stream_id);
        return json_response(true, 'Comments retrieved successfully', $comments);
    }

    // Edit a comment
    public function classroom_stream_comment_put($class_code, $stream_id, $comment_id) {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        $this->load->model('ClassroomStream_model');
        $post = $this->ClassroomStream_model->get_by_id($stream_id);
        if (!$post || $post['class_code'] !== $class_code) {
            return json_response(false, 'Stream post not found', null, 404);
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($data['comment'])) {
            return json_response(false, 'Comment is required', null, 400);
        }
        $success = $this->ClassroomStream_model->update_comment($comment_id, $user_data['user_id'], $data['comment']);
        if ($success) {
            $comments = $this->ClassroomStream_model->get_comments($stream_id);
            return json_response(true, 'Comment updated successfully', $comments);
        } else {
            return json_response(false, 'Failed to update comment (maybe not your comment)', null, 403);
        }
    }

    // Delete a comment
    public function classroom_stream_comment_delete($class_code, $stream_id, $comment_id) {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        $this->load->model('ClassroomStream_model');
        $post = $this->ClassroomStream_model->get_by_id($stream_id);
        if (!$post || $post['class_code'] !== $class_code) {
            return json_response(false, 'Stream post not found', null, 404);
        }
        $success = $this->ClassroomStream_model->delete_comment($comment_id, $user_data['user_id']);
        if ($success) {
            $comments = $this->ClassroomStream_model->get_comments($stream_id);
            return json_response(true, 'Comment deleted successfully', $comments);
        } else {
            return json_response(false, 'Failed to delete comment (maybe not your comment)', null, 403);
        }
    }

    /**
     * Get list of students enrolled in a specific class
     * GET /api/teacher/classroom/{class_code}/students
     */
    public function classroom_students_get($class_code) {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        
        // Get classroom by code and verify teacher ownership
        $this->load->model('Classroom_model');
        $classroom = $this->Classroom_model->get_by_code($class_code);
        if (!$classroom) {
            return json_response(false, 'Classroom not found', null, 404);
        }
        
        // Verify that this teacher owns the classroom
        if ($classroom['teacher_id'] != $user_data['user_id']) {
            return json_response(false, 'Access denied. You can only view students in your own classes.', null, 403);
        }
        
        // Get enrolled students with their details - using raw query to handle collation
        $query = "SELECT 
                    ce.enrolled_at,
                    ce.status as enrollment_status,
                    u.user_id,
                    u.full_name,
                    u.email,
                    u.student_num,
                    u.contact_num,
                    u.program,
                    u.section_id
                FROM classroom_enrollments ce
                JOIN users u ON ce.student_id = u.user_id COLLATE utf8mb4_unicode_ci
                WHERE ce.classroom_id = ?
                AND ce.status = 'active'
                ORDER BY u.full_name ASC";
        
        $enrolled_students = $this->db->query($query, [$classroom['id']])->result_array();
        
        // Format the response
        $students = [];
        foreach ($enrolled_students as $student) {
            // Get section name separately to avoid collation issues
            $section_name = '';
            if (!empty($student['section_id'])) {
                $section = $this->db->get_where('sections', ['section_id' => $student['section_id']])->row_array();
                $section_name = $section ? $section['section_name'] : '';
            }
            
            $students[] = [
                'user_id' => $student['user_id'],
                'full_name' => $student['full_name'],
                'email' => $student['email'],
                'student_num' => $student['student_num'],
                'contact_num' => $student['contact_num'],
                'program' => $student['program'],
                'section_name' => $section_name,
                'enrolled_at' => $student['enrolled_at'],
                'enrollment_status' => $student['enrollment_status']
            ];
        }
        
        $response_data = [
            'class_code' => $classroom['class_code'],
            'total_students' => count($students),
            'students' => $students
        ];
        
        return json_response(true, 'Enrolled students retrieved successfully', $response_data);
    }

    /**
     * Get enrollment statistics for a class
     * GET /api/teacher/classroom/{class_code}/enrollment-stats
     */
    public function classroom_enrollment_stats_get($class_code) {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        
        // Get classroom by code and verify teacher ownership
        $this->load->model('Classroom_model');
        $classroom = $this->Classroom_model->get_by_code($class_code);
        if (!$classroom) {
            return json_response(false, 'Classroom not found', null, 404);
        }
        
        // Verify that this teacher owns the classroom
        if ($classroom['teacher_id'] != $user_data['user_id']) {
            return json_response(false, 'Access denied. You can only view statistics for your own classes.', null, 403);
        }
        
        // Get enrollment statistics
        $total_enrolled = $this->db->where('classroom_id', $classroom['id'])
            ->where('status', 'active')
            ->count_all_results('classroom_enrollments');
        
        $total_inactive = $this->db->where('classroom_id', $classroom['id'])
            ->where('status', 'inactive')
            ->count_all_results('classroom_enrollments');
        
        $total_dropped = $this->db->where('classroom_id', $classroom['id'])
            ->where('status', 'dropped')
            ->count_all_results('classroom_enrollments');
        
        // Get recent enrollments (last 7 days)
        $recent_enrollments = $this->db->where('classroom_id', $classroom['id'])
            ->where('status', 'active')
            ->where('enrolled_at >=', date('Y-m-d H:i:s', strtotime('-7 days')))
            ->count_all_results('classroom_enrollments');
        
        $stats = [
            'class_code' => $classroom['class_code'],
            'total_enrolled' => $total_enrolled,
            'total_inactive' => $total_inactive,
            'total_dropped' => $total_dropped,
            'recent_enrollments' => $recent_enrollments,
            'total_enrollments' => $total_enrolled + $total_inactive + $total_dropped
        ];
        
        return json_response(true, 'Enrollment statistics retrieved successfully', $stats);
    }

    // --- Teacher Attendance Management ---

    /**
     * Get teacher's subjects for attendance
     * GET /api/teacher/attendance/subjects
     */
    public function attendance_subjects_get() {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        
        try {
            // Get unique subject-section combinations for the teacher
            $subjects = $this->db->select('DISTINCT subjects.subject_name, subjects.subject_code, sections.section_name, 
                                         CONCAT(subjects.subject_name, " ", sections.section_name) as subject_section')
                                ->from('classes')
                                ->join('subjects', 'classes.subject_id = subjects.id')
                                ->join('sections', 'classes.section_id = sections.section_id')
                                ->where('classes.teacher_id', $user_data['user_id'])
                                ->order_by('subjects.subject_name', 'ASC')
                                ->order_by('sections.section_name', 'ASC')
                                ->get()->result_array();
            
            return json_response(true, 'Subjects retrieved successfully', $subjects);
            
        } catch (Exception $e) {
            return json_response(false, 'Error retrieving subjects: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Mark attendance via QR code scan (simplified for subject-section combination)
     * POST /api/teacher/attendance/qr-scan
     */
    public function attendance_qr_scan() {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        
        $this->load->model('Attendance_model');
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['qr_data']) || !isset($data['subject_section'])) {
            return json_response(false, 'Missing required data: qr_data and subject_section', null, 400);
        }
        
        try {
            // Parse QR data (format: "IDNo: 2021305973\nFull Name: ANJELA SOFIA G. SARMIENTO\nProgram: Bachelor of Science in Information Technology")
            $qr_lines = explode("\n", $data['qr_data']);
            $qr_info = [];
            
            foreach ($qr_lines as $line) {
                if (strpos($line, ':') !== false) {
                    $parts = explode(':', $line, 2);
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                    $qr_info[$key] = $value;
                }
            }
            
            // Extract student information from QR
            $student_id_no = isset($qr_info['IDNo']) ? $qr_info['IDNo'] : null;
            $student_full_name = isset($qr_info['Full Name']) ? $qr_info['Full Name'] : null;
            $student_program = isset($qr_info['Program']) ? $qr_info['Program'] : null;
            
            if (!$student_id_no) {
                return json_response(false, 'Invalid QR code: Missing student ID number', null, 400);
            }
            
            $subject_section = $data['subject_section']; // e.g., "OOP BSIT1A"
            $date = date('Y-m-d');
            $time_in = date('H:i:s');
            
            // Verify teacher has this subject-section combination
            $teacher_class = $this->db->select('classes.*, subjects.subject_name, sections.section_name')
                                    ->from('classes')
                                    ->join('subjects', 'classes.subject_id = subjects.id')
                                    ->join('sections', 'classes.section_id = sections.section_id')
                                    ->where('classes.teacher_id', $user_data['user_id'])
                                    ->where('CONCAT(subjects.subject_name, " ", sections.section_name)', $subject_section)
                                    ->get()->row_array();
            
            if (!$teacher_class) {
                return json_response(false, 'Teacher not assigned to this subject-section', null, 400);
            }
            
            // Find student by ID number in the specific section
            $student = $this->db->where('student_num', $student_id_no)
                               ->where('section_id', $teacher_class['section_id'])
                               ->where('role', 'student')
                               ->get('users')->row_array();
            
            if (!$student) {
                return json_response(false, 'Student not enrolled in this section', null, 400);
            }
            
            // Verify student name matches (additional security)
            if ($student_full_name && $student['full_name'] !== $student_full_name) {
                return json_response(false, 'Student information mismatch', null, 400);
            }
            
            // Check if attendance already exists for today (subject-section + student)
            $existing_attendance = $this->db->where('student_id', $student_id_no)
                                          ->where('subject_section', $subject_section)
                                          ->where('date', $date)
                                          ->get('attendance')->row_array();
            
            if ($existing_attendance) {
                return json_response(false, 'Attendance already recorded for this student today', null, 400);
            }
            
            // Determine attendance status based on time
            $current_time = date('H:i:s');
            $late_threshold = '08:00:00'; // Adjust as needed
            $attendance_status = ($current_time <= $late_threshold) ? 'present' : 'late';
            
            // Insert attendance record (simplified structure)
            $attendance_data = [
                'student_id' => $student_id_no, // varchar(20) - using the ID number directly
                'subject_section' => $subject_section, // e.g., "OOP BSIT1A"
                'date' => $date, // date
                'time_in' => $time_in, // time
                'status' => $attendance_status // enum('present','late','absent','excused') with default 'present'
            ];
            
            $this->db->insert('attendance', $attendance_data);
            $attendance_id = $this->db->insert_id();
            
            return json_response(true, 'Attendance marked successfully', [
                'attendance_id' => $attendance_id,
                'student_name' => $student['full_name'],
                'student_id' => $student_id_no,
                'student_program' => $student_program,
                'status' => $attendance_status,
                'time_in' => $time_in,
                'subject_section' => $subject_section,
                'date' => $date,
                'qr_data_parsed' => [
                    'id_no' => $student_id_no,
                    'full_name' => $student_full_name,
                    'program' => $student_program
                ]
            ]);
            
        } catch (Exception $e) {
            return json_response(false, 'Error marking attendance: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Manual attendance entry for a subject-section
     * POST /api/teacher/attendance/manual
     */
    public function attendance_manual() {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        
        $this->load->model('Attendance_model');
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['subject_section']) || !isset($data['student_id']) || !isset($data['status'])) {
            return json_response(false, 'Missing required data: subject_section, student_id, and status', null, 400);
        }
        
        // Validate status
        $valid_statuses = ['present', 'late', 'absent', 'excused'];
        if (!in_array($data['status'], $valid_statuses)) {
            return json_response(false, 'Invalid status. Must be: present, late, absent, or excused', null, 400);
        }
        
        try {
            $subject_section = $data['subject_section']; // e.g., "OOP BSIT1A"
            $student_id = $data['student_id'];
            $status = $data['status'];
            $date = isset($data['date']) ? $data['date'] : date('Y-m-d');
            $time_in = isset($data['time_in']) ? $data['time_in'] : date('H:i:s');
            
            // Verify teacher has this subject-section
            $teacher_class = $this->db->select('classes.*, subjects.subject_name, sections.section_name')
                                    ->from('classes')
                                    ->join('subjects', 'classes.subject_id = subjects.id')
                                    ->join('sections', 'classes.section_id = sections.section_id')
                                    ->where('classes.teacher_id', $user_data['user_id'])
                                    ->where('CONCAT(subjects.subject_name, " ", sections.section_name)', $subject_section)
                                    ->get()->row_array();
            
            if (!$teacher_class) {
                return json_response(false, 'Teacher not assigned to this subject-section', null, 400);
            }
            
            // Check if student is enrolled in this section
            $student = $this->db->where('student_num', $student_id)
                               ->where('section_id', $teacher_class['section_id'])
                               ->where('role', 'student')
                               ->get('users')->row_array();
            
            if (!$student) {
                return json_response(false, 'Student not enrolled in this section', null, 400);
            }
            
            // Check if attendance already exists for today
            $existing_attendance = $this->db->where('student_id', $student_id)
                                          ->where('subject_section', $subject_section)
                                          ->where('date', $date)
                                          ->get('attendance')->row_array();
            
            if ($existing_attendance) {
                // Update existing attendance
                $this->db->where('attendance_id', $existing_attendance['attendance_id'])
                         ->update('attendance', [
                             'status' => $status,
                             'time_in' => $time_in
                         ]);
                
                return json_response(true, 'Attendance updated successfully', [
                    'attendance_id' => $existing_attendance['attendance_id'],
                    'student_name' => $student['full_name'],
                    'student_id' => $student_id,
                    'status' => $status,
                    'time_in' => $time_in,
                    'subject_section' => $subject_section,
                    'date' => $date,
                    'action' => 'updated'
                ]);
            } else {
                // Insert new attendance
                $attendance_data = [
                    'student_id' => $student_id,
                    'subject_section' => $subject_section,
                    'date' => $date,
                    'time_in' => $time_in,
                    'status' => $status
                ];
                
                $this->db->insert('attendance', $attendance_data);
                $attendance_id = $this->db->insert_id();
                
                return json_response(true, 'Attendance marked successfully', [
                    'attendance_id' => $attendance_id,
                    'student_name' => $student['full_name'],
                    'student_id' => $student_id,
                    'status' => $status,
                    'time_in' => $time_in,
                    'subject_section' => $subject_section,
                    'date' => $date,
                    'action' => 'created'
                ]);
            }
            
        } catch (Exception $e) {
            return json_response(false, 'Error marking attendance: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get attendance records (flexible - all records or specific subject-section)
     * GET /api/teacher/attendance/records (all records for teacher)
     * GET /api/teacher/attendance/records?subject_section=OOP BSIT1A (specific subject-section)
     * GET /api/teacher/attendance/records?subject_section=OOP BSIT1A&date=2025-01-27 (specific date)
     */
    public function attendance_records_get() {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        
        try {
            $subject_section = $this->input->get('subject_section');
            $date = $this->input->get('date');
            
            // Build the query
            $this->db->select('attendance.*, users.full_name as student_name, users.student_num')
                     ->from('attendance')
                     ->join('users', 'attendance.student_id = users.student_num');
            
            // If subject_section is provided, filter by it
            if ($subject_section) {
                // Verify teacher has this subject-section
                $teacher_class = $this->db->select('classes.*, subjects.subject_name, sections.section_name')
                                        ->from('classes')
                                        ->join('subjects', 'classes.subject_id = subjects.id')
                                        ->join('sections', 'classes.section_id = sections.section_id')
                                        ->where('classes.teacher_id', $user_data['user_id'])
                                        ->where('CONCAT(subjects.subject_name, " ", sections.section_name)', $subject_section)
                                        ->get()->row_array();
                
                if (!$teacher_class) {
                    return json_response(false, 'Teacher not assigned to this subject-section', null, 400);
                }
                
                $this->db->where('attendance.subject_section', $subject_section);
            } else {
                // Get all subject-sections for this teacher
                $teacher_subjects = $this->db->select('DISTINCT CONCAT(subjects.subject_name, " ", sections.section_name) as subject_section')
                                           ->from('classes')
                                           ->join('subjects', 'classes.subject_id = subjects.id')
                                           ->join('sections', 'classes.section_id = sections.section_id')
                                           ->where('classes.teacher_id', $user_data['user_id'])
                                           ->get()->result_array();
                
                if (empty($teacher_subjects)) {
                    return json_response(true, 'No attendance records found', [
                        'records' => [],
                        'summary' => 'No classes assigned to this teacher'
                    ]);
                }
                
                $subject_sections = array_column($teacher_subjects, 'subject_section');
                $this->db->where_in('attendance.subject_section', $subject_sections);
            }
            
            // If date is provided, filter by it
            if ($date) {
                $this->db->where('attendance.date', $date);
            }
            
            $attendance = $this->db->order_by('attendance.date', 'DESC')
                                  ->order_by('attendance.subject_section', 'ASC')
                                  ->order_by('users.full_name', 'ASC')
                                  ->get()->result_array();
            
            // Group records by subject-section and date for better organization
            $grouped_records = [];
            foreach ($attendance as $record) {
                $key = $record['subject_section'] . '_' . $record['date'];
                if (!isset($grouped_records[$key])) {
                    $grouped_records[$key] = [
                        'subject_section' => $record['subject_section'],
                        'date' => $record['date'],
                        'records' => []
                    ];
                }
                $grouped_records[$key]['records'][] = $record;
            }
            
            $response_data = [
                'records' => array_values($grouped_records),
                'total_records' => count($attendance),
                'filters' => [
                    'subject_section' => $subject_section ?: 'all',
                    'date' => $date ?: 'all'
                ]
            ];
            
            return json_response(true, 'Attendance records retrieved successfully', $response_data);
            
        } catch (Exception $e) {
            return json_response(false, 'Error retrieving attendance: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get attendance statistics (flexible - all stats or specific subject-section)
     * GET /api/teacher/attendance/stats (all stats for teacher)
     * GET /api/teacher/attendance/stats?subject_section=OOP BSIT1A (specific subject-section)
     */
    public function attendance_stats_get() {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        
        try {
            $subject_section = $this->input->get('subject_section');
            
            if ($subject_section) {
                // Get stats for specific subject-section
                $teacher_class = $this->db->select('classes.*, subjects.subject_name, sections.section_name')
                                        ->from('classes')
                                        ->join('subjects', 'classes.subject_id = subjects.id')
                                        ->join('sections', 'classes.section_id = sections.section_id')
                                        ->where('classes.teacher_id', $user_data['user_id'])
                                        ->where('CONCAT(subjects.subject_name, " ", sections.section_name)', $subject_section)
                                        ->get()->row_array();
                
                if (!$teacher_class) {
                    return json_response(false, 'Teacher not assigned to this subject-section', null, 400);
                }
                
                $stats = $this->db->select('
                    COUNT(*) as total_records,
                    SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late_count,
                    SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent_count,
                    SUM(CASE WHEN status = "excused" THEN 1 ELSE 0 END) as excused_count
                ')
                ->from('attendance')
                ->where('subject_section', $subject_section)
                ->get()->row_array();
                
                return json_response(true, 'Statistics retrieved successfully', [
                    'subject_section' => $subject_section,
                    'statistics' => $stats
                ]);
                
            } else {
                // Get stats for all subject-sections assigned to teacher
                $teacher_subjects = $this->db->select('DISTINCT CONCAT(subjects.subject_name, " ", sections.section_name) as subject_section')
                                           ->from('classes')
                                           ->join('subjects', 'classes.subject_id = subjects.id')
                                           ->join('sections', 'classes.section_id = sections.section_id')
                                           ->where('classes.teacher_id', $user_data['user_id'])
                                           ->get()->result_array();
                
                if (empty($teacher_subjects)) {
                    return json_response(true, 'No statistics available', [
                        'summary' => 'No classes assigned to this teacher',
                        'statistics' => []
                    ]);
                }
                
                $subject_sections = array_column($teacher_subjects, 'subject_section');
                
                // Get overall stats
                $overall_stats = $this->db->select('
                    COUNT(*) as total_records,
                    SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late_count,
                    SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent_count,
                    SUM(CASE WHEN status = "excused" THEN 1 ELSE 0 END) as excused_count
                ')
                ->from('attendance')
                ->where_in('subject_section', $subject_sections)
                ->get()->row_array();
                
                // Get stats per subject-section
                $per_subject_stats = [];
                foreach ($subject_sections as $subject_section) {
                    $stats = $this->db->select('
                        COUNT(*) as total_records,
                        SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present_count,
                        SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late_count,
                        SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent_count,
                        SUM(CASE WHEN status = "excused" THEN 1 ELSE 0 END) as excused_count
                    ')
                    ->from('attendance')
                    ->where('subject_section', $subject_section)
                    ->get()->row_array();
                    
                    $per_subject_stats[] = [
                        'subject_section' => $subject_section,
                        'statistics' => $stats
                    ];
                }
                
                return json_response(true, 'Statistics retrieved successfully', [
                    'overall_statistics' => $overall_stats,
                    'per_subject_statistics' => $per_subject_stats,
                    'total_subject_sections' => count($subject_sections)
                ]);
            }
            
        } catch (Exception $e) {
            return json_response(false, 'Error retrieving statistics: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Export attendance data
     * POST /api/teacher/attendance/export
     */
    public function attendance_export() {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['section_id'])) {
            return json_response(false, 'Missing required data: section_id', null, 400);
        }
        
        $section_id = $data['section_id'];
        $date_from = isset($data['date_from']) ? $data['date_from'] : date('Y-m-d', strtotime('-30 days'));
        $date_to = isset($data['date_to']) ? $data['date_to'] : date('Y-m-d');
        
        try {
            $this->load->model('Attendance_model');
            
            $filters = [
                'section_id' => $section_id,
                'teacher_id' => $user_data['user_id'],
                'date_from' => $date_from,
                'date_to' => $date_to
            ];
            
            $attendance_data = $this->Attendance_model->get_attendance_report($filters);
            
            // Format data for export
            $export_data = [];
            foreach ($attendance_data as $record) {
                $export_data[] = [
                    'Date' => $record['date'],
                    'Student Name' => $record['student_name'],
                    'Student ID' => $record['student_id'],
                    'Section' => $record['section_name'],
                    'Subject' => $record['subject_name'],
                    'Status' => ucfirst($record['attendance_status']),
                    'Time In' => $record['time_in'],
                    'Notes' => $record['notes']
                ];
            }
            
            return json_response(true, 'Export data generated successfully', [
                'data' => $export_data,
                'total_records' => count($export_data),
                'date_range' => $date_from . ' to ' . $date_to
            ]);
            
        } catch (Exception $e) {
            return json_response(false, 'Error generating export: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get sections assigned to teacher for attendance
     * GET /api/teacher/attendance/sections
     */
    public function attendance_sections_get() {
        $user_data = require_teacher($this);
        if (!$user_data) return;
        
        try {
            // Get sections where teacher has classes
            $sections = $this->db->select('DISTINCT sections.section_id, sections.section_name, subjects.subject_name, subjects.subject_code')
                               ->from('classes')
                               ->join('sections', 'classes.section_id = sections.section_id')
                               ->join('subjects', 'classes.subject_id = subjects.id')
                               ->where('classes.teacher_id', $user_data['user_id'])
                               ->get()->result_array();
            
            return json_response(true, 'Teacher sections retrieved successfully', $sections);
            
        } catch (Exception $e) {
            return json_response(false, 'Error retrieving sections: ' . $e->getMessage(), null, 500);
        }
    }
}
