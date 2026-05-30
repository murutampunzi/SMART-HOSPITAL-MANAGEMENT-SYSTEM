-- Smart Hospital Management System Database Schema
-- Generated for SHMS v1.0.0

-- Create database (Commented out for shared hosting / production deployment)
-- CREATE DATABASE IF NOT EXISTS `smart_hospital` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `smart_hospital`;

-- Users table (authentication and user management)
CREATE TABLE `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `email` varchar(100) NOT NULL,
    `password` varchar(255) NOT NULL,
    `role` enum('admin','doctor','nurse','receptionist','pharmacist','lab_technician','patient') NOT NULL,
    `phone` varchar(20) DEFAULT NULL,
    `address` text DEFAULT NULL,
    `profile_image` varchar(255) DEFAULT NULL,
    `status` enum('active','inactive','suspended') DEFAULT 'active',
    `last_login` datetime DEFAULT NULL,
    `email_verified` tinyint(1) DEFAULT 0,
    `email_verification_token` varchar(255) DEFAULT NULL,
    `password_reset_token` varchar(255) DEFAULT NULL,
    `password_reset_expires` datetime DEFAULT NULL,
    `remember_token` varchar(255) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`),
    KEY `role` (`role`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Patients table
CREATE TABLE `patients` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) DEFAULT NULL,
    `patient_id` varchar(20) NOT NULL,
    `first_name` varchar(50) NOT NULL,
    `last_name` varchar(50) NOT NULL,
    `date_of_birth` date NOT NULL,
    `gender` enum('male','female','other') NOT NULL,
    `blood_group` enum('A+','A-','B+','B-','O+','O-','AB+','AB-') DEFAULT NULL,
    `phone` varchar(20) NOT NULL,
    `email` varchar(100) DEFAULT NULL,
    `address` text DEFAULT NULL,
    `emergency_contact_name` varchar(100) DEFAULT NULL,
    `emergency_contact_phone` varchar(20) DEFAULT NULL,
    `emergency_contact_relation` varchar(50) DEFAULT NULL,
    `medical_history` text DEFAULT NULL,
    `allergies` text DEFAULT NULL,
    `current_medications` text DEFAULT NULL,
    `insurance_provider` varchar(100) DEFAULT NULL,
    `insurance_policy_number` varchar(50) DEFAULT NULL,
    `profile_image` varchar(255) DEFAULT NULL,
    `status` enum('active','inactive') DEFAULT 'active',
    `admission_date` datetime DEFAULT NULL,
    `discharge_date` datetime DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `patient_id` (`patient_id`),
    KEY `user_id` (`user_id`),
    KEY `first_name` (`first_name`),
    KEY `last_name` (`last_name`),
    KEY `phone` (`phone`),
    KEY `status` (`status`),
    CONSTRAINT `patients_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Doctors table
CREATE TABLE `doctors` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) DEFAULT NULL,
    `doctor_id` varchar(20) NOT NULL,
    `first_name` varchar(50) NOT NULL,
    `last_name` varchar(50) NOT NULL,
    `specialization` varchar(100) NOT NULL,
    `qualification` varchar(255) DEFAULT NULL,
    `experience_years` int(11) DEFAULT 0,
    `phone` varchar(20) NOT NULL,
    `email` varchar(100) DEFAULT NULL,
    `address` text DEFAULT NULL,
    `availability` varchar(50) DEFAULT 'full_time',
    `consultation_hours` varchar(255) DEFAULT NULL,
    `consultation_fee` decimal(10,2) DEFAULT 0.00,
    `available_days` varchar(100) DEFAULT NULL,
    `available_time_start` time DEFAULT NULL,
    `available_time_end` time DEFAULT NULL,
    `profile_image` varchar(255) DEFAULT NULL,
    `bio` text DEFAULT NULL,
    `education` text DEFAULT NULL,
    `status` enum('active','inactive','on_leave') DEFAULT 'active',
    `license_number` varchar(50) DEFAULT NULL,
    `license_expiry` date DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `doctor_id` (`doctor_id`),
    KEY `user_id` (`user_id`),
    KEY `specialization` (`specialization`),
    KEY `status` (`status`),
    CONSTRAINT `doctors_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Appointments table
CREATE TABLE `appointments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `appointment_id` varchar(20) NOT NULL,
    `patient_id` int(11) NOT NULL,
    `doctor_id` int(11) NOT NULL,
    `appointment_date` date NOT NULL,
    `appointment_time` time NOT NULL,
    `duration` int(11) DEFAULT 30,
    `type` enum('consultation','follow_up','emergency','surgery','checkup') DEFAULT 'consultation',
    `status` enum('pending','confirmed','cancelled','completed','no_show') DEFAULT 'pending',
    `reason` text DEFAULT NULL,
    `symptoms` text DEFAULT NULL,
    `notes` text DEFAULT NULL,
    `payment_status` enum('pending','paid','refunded') DEFAULT 'pending',
    `payment_amount` decimal(10,2) DEFAULT 0.00,
    `cancelled_by` enum('patient','doctor','admin') DEFAULT NULL,
    `cancellation_reason` text DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `appointment_id` (`appointment_id`),
    KEY `patient_id` (`patient_id`),
    KEY `doctor_id` (`doctor_id`),
    KEY `appointment_date` (`appointment_date`),
    KEY `status` (`status`),
    CONSTRAINT `appointments_patient_id_fk` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `appointments_doctor_id_fk` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Prescriptions table
CREATE TABLE `prescriptions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `prescription_id` varchar(20) NOT NULL,
    `appointment_id` int(11) NOT NULL,
    `patient_id` int(11) NOT NULL,
    `doctor_id` int(11) NOT NULL,
    `diagnosis` text DEFAULT NULL,
    `notes` text DEFAULT NULL,
    `status` enum('active','completed','cancelled') DEFAULT 'active',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `prescription_id` (`prescription_id`),
    KEY `appointment_id` (`appointment_id`),
    KEY `patient_id` (`patient_id`),
    KEY `doctor_id` (`doctor_id`),
    CONSTRAINT `prescriptions_appointment_id_fk` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `prescriptions_patient_id_fk` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `prescriptions_doctor_id_fk` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Prescription medicines table
CREATE TABLE `prescription_medicines` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `prescription_id` int(11) NOT NULL,
    `medicine_id` int(11) NOT NULL,
    `dosage` varchar(50) NOT NULL,
    `frequency` varchar(50) NOT NULL,
    `duration` varchar(50) NOT NULL,
    `instructions` text DEFAULT NULL,
    `quantity` int(11) DEFAULT 0,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `prescription_id` (`prescription_id`),
    KEY `medicine_id` (`medicine_id`),
    CONSTRAINT `prescription_medicines_prescription_id_fk` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Medicines table
CREATE TABLE `medicines` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `medicine_id` varchar(20) NOT NULL,
    `name` varchar(100) NOT NULL,
    `generic_name` varchar(100) DEFAULT NULL,
    `category` varchar(50) DEFAULT NULL,
    `description` text DEFAULT NULL,
    `manufacturer` varchar(100) DEFAULT NULL,
    `unit` varchar(20) DEFAULT NULL,
    `stock_quantity` int(11) DEFAULT 0,
    `reorder_level` int(11) DEFAULT 10,
    `unit_price` decimal(10,2) DEFAULT 0.00,
    `selling_price` decimal(10,2) DEFAULT 0.00,
    `expiry_date` date DEFAULT NULL,
    `batch_number` varchar(50) DEFAULT NULL,
    `supplier` varchar(100) DEFAULT NULL,
    `storage_conditions` text DEFAULT NULL,
    `side_effects` text DEFAULT NULL,
    `contraindications` text DEFAULT NULL,
    `status` enum('active','inactive','discontinued') DEFAULT 'active',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `medicine_id` (`medicine_id`),
    KEY `name` (`name`),
    KEY `category` (`category`),
    KEY `status` (`status`),
    KEY `expiry_date` (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Laboratory tests table
CREATE TABLE `lab_tests` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `test_id` varchar(20) NOT NULL,
    `name` varchar(100) NOT NULL,
    `description` text DEFAULT NULL,
    `category` varchar(50) DEFAULT NULL,
    `sample_type` enum('blood','urine','stool','saliva','tissue','other') DEFAULT 'blood',
    `preparation_instructions` text DEFAULT NULL,
    `normal_range` text DEFAULT NULL,
    `unit` varchar(20) DEFAULT NULL,
    `price` decimal(10,2) DEFAULT 0.00,
    `duration_minutes` int(11) DEFAULT 30,
    `status` enum('active','inactive') DEFAULT 'active',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `test_id` (`test_id`),
    KEY `name` (`name`),
    KEY `category` (`category`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lab test requests table
CREATE TABLE `lab_test_requests` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `request_id` varchar(20) NOT NULL,
    `patient_id` int(11) NOT NULL,
    `doctor_id` int(11) DEFAULT NULL,
    `test_id` int(11) NOT NULL,
    `appointment_id` int(11) DEFAULT NULL,
    `requested_date` date NOT NULL,
    `sample_collected` tinyint(1) DEFAULT 0,
    `sample_collected_date` datetime DEFAULT NULL,
    `sample_collected_by` int(11) DEFAULT NULL,
    `status` enum('pending','sample_collected','in_progress','completed','cancelled') DEFAULT 'pending',
    `priority` enum('normal','urgent','stat') DEFAULT 'normal',
    `notes` text DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `request_id` (`request_id`),
    KEY `patient_id` (`patient_id`),
    KEY `doctor_id` (`doctor_id`),
    KEY `test_id` (`test_id`),
    KEY `appointment_id` (`appointment_id`),
    KEY `status` (`status`),
    CONSTRAINT `lab_test_requests_patient_id_fk` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `lab_test_requests_doctor_id_fk` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL,
    CONSTRAINT `lab_test_requests_test_id_fk` FOREIGN KEY (`test_id`) REFERENCES `lab_tests` (`id`) ON DELETE CASCADE,
    CONSTRAINT `lab_test_requests_appointment_id_fk` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lab results table
CREATE TABLE `lab_results` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `result_id` varchar(20) NOT NULL,
    `request_id` int(11) NOT NULL,
    `patient_id` int(11) NOT NULL,
    `test_id` int(11) NOT NULL,
    `result_value` varchar(100) DEFAULT NULL,
    `result_text` text DEFAULT NULL,
    `normal_range` text DEFAULT NULL,
    `unit` varchar(20) DEFAULT NULL,
    `status` enum('normal','abnormal','critical') DEFAULT 'normal',
    `comments` text DEFAULT NULL,
    `technician_id` int(11) DEFAULT NULL,
    `verified_by` int(11) DEFAULT NULL,
    `verified_date` datetime DEFAULT NULL,
    `report_file` varchar(255) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `result_id` (`result_id`),
    KEY `request_id` (`request_id`),
    KEY `patient_id` (`patient_id`),
    KEY `test_id` (`test_id`),
    KEY `technician_id` (`technician_id`),
    KEY `verified_by` (`verified_by`),
    CONSTRAINT `lab_results_request_id_fk` FOREIGN KEY (`request_id`) REFERENCES `lab_test_requests` (`id`) ON DELETE CASCADE,
    CONSTRAINT `lab_results_patient_id_fk` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `lab_results_test_id_fk` FOREIGN KEY (`test_id`) REFERENCES `lab_tests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Billing invoices table
CREATE TABLE `invoices` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `invoice_id` varchar(20) NOT NULL,
    `patient_id` int(11) NOT NULL,
    `appointment_id` int(11) DEFAULT NULL,
    `invoice_date` date NOT NULL,
    `due_date` date DEFAULT NULL,
    `subtotal` decimal(10,2) DEFAULT 0.00,
    `tax_amount` decimal(10,2) DEFAULT 0.00,
    `discount_amount` decimal(10,2) DEFAULT 0.00,
    `total_amount` decimal(10,2) DEFAULT 0.00,
    `paid_amount` decimal(10,2) DEFAULT 0.00,
    `balance_amount` decimal(10,2) DEFAULT 0.00,
    `status` enum('draft','sent','paid','overdue','cancelled') DEFAULT 'draft',
    `payment_method` enum('cash','card','insurance','online') DEFAULT NULL,
    `notes` text DEFAULT NULL,
    `created_by` int(11) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `invoice_id` (`invoice_id`),
    KEY `patient_id` (`patient_id`),
    KEY `appointment_id` (`appointment_id`),
    KEY `status` (`status`),
    KEY `created_by` (`created_by`),
    CONSTRAINT `invoices_patient_id_fk` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `invoices_appointment_id_fk` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invoice items table
CREATE TABLE `invoice_items` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `invoice_id` int(11) NOT NULL,
    `item_type` enum('consultation','test','medicine','procedure','other') NOT NULL,
    `item_id` int(11) DEFAULT NULL,
    `description` varchar(255) NOT NULL,
    `quantity` int(11) DEFAULT 1,
    `unit_price` decimal(10,2) DEFAULT 0.00,
    `total_price` decimal(10,2) DEFAULT 0.00,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `invoice_id` (`invoice_id`),
    CONSTRAINT `invoice_items_invoice_id_fk` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments table
CREATE TABLE `payments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `payment_id` varchar(20) NOT NULL,
    `invoice_id` int(11) NOT NULL,
    `patient_id` int(11) NOT NULL,
    `amount` decimal(10,2) NOT NULL,
    `payment_method` enum('cash','card','insurance','online','bank_transfer') NOT NULL,
    `payment_date` datetime NOT NULL,
    `transaction_id` varchar(100) DEFAULT NULL,
    `status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
    `notes` text DEFAULT NULL,
    `received_by` int(11) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `payment_id` (`payment_id`),
    KEY `invoice_id` (`invoice_id`),
    KEY `patient_id` (`patient_id`),
    KEY `status` (`status`),
    CONSTRAINT `payments_invoice_id_fk` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
    CONSTRAINT `payments_patient_id_fk` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications table
CREATE TABLE `notifications` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) DEFAULT NULL,
    `title` varchar(255) NOT NULL,
    `message` text NOT NULL,
    `type` enum('appointment','message','lab_result','prescription','payment','system','emergency','reminder') NOT NULL,
    `link` varchar(255) DEFAULT NULL,
    `read` tinyint(1) DEFAULT 0,
    `read_at` datetime DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `type` (`type`),
    KEY `read` (`read`),
    KEY `created_at` (`created_at`),
    CONSTRAINT `notifications_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Messages table
CREATE TABLE `messages` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `sender_id` int(11) NOT NULL,
    `receiver_id` int(11) NOT NULL,
    `subject` varchar(255) DEFAULT NULL,
    `message` text NOT NULL,
    `attachment` varchar(255) DEFAULT NULL,
    `read` tinyint(1) DEFAULT 0,
    `read_at` datetime DEFAULT NULL,
    `deleted_by_sender` tinyint(1) DEFAULT 0,
    `deleted_by_receiver` tinyint(1) DEFAULT 0,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `sender_id` (`sender_id`),
    KEY `receiver_id` (`receiver_id`),
    KEY `read` (`read`),
    KEY `created_at` (`created_at`),
    CONSTRAINT `messages_sender_id_fk` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `messages_receiver_id_fk` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity logs table
CREATE TABLE `activity_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) DEFAULT NULL,
    `action` varchar(100) NOT NULL,
    `details` text DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `action` (`action`),
    KEY `created_at` (`created_at`),
    CONSTRAINT `activity_logs_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System settings table
CREATE TABLE `system_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(100) NOT NULL,
    `setting_value` text DEFAULT NULL,
    `setting_type` enum('string','number','boolean','json') DEFAULT 'string',
    `description` text DEFAULT NULL,
    `editable` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Departments table
CREATE TABLE `departments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `description` text DEFAULT NULL,
    `head_doctor_id` int(11) DEFAULT NULL,
    `location` varchar(255) DEFAULT NULL,
    `phone` varchar(20) DEFAULT NULL,
    `email` varchar(100) DEFAULT NULL,
    `status` enum('active','inactive') DEFAULT 'active',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `head_doctor_id` (`head_doctor_id`),
    CONSTRAINT `departments_head_doctor_id_fk` FOREIGN KEY (`head_doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Doctor departments table (many-to-many relationship)
CREATE TABLE `doctor_departments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `doctor_id` int(11) NOT NULL,
    `department_id` int(11) NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `doctor_department` (`doctor_id`,`department_id`),
    KEY `doctor_id` (`doctor_id`),
    KEY `department_id` (`department_id`),
    CONSTRAINT `doctor_departments_doctor_id_fk` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
    CONSTRAINT `doctor_departments_department_id_fk` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Medical records table
CREATE TABLE `medical_records` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `record_id` varchar(20) NOT NULL,
    `patient_id` int(11) NOT NULL,
    `doctor_id` int(11) NOT NULL,
    `appointment_id` int(11) DEFAULT NULL,
    `type` enum('consultation','diagnosis','treatment','surgery','lab_result','prescription','other') NOT NULL,
    `title` varchar(255) NOT NULL,
    `description` text NOT NULL,
    `diagnosis` text DEFAULT NULL,
    `treatment` text DEFAULT NULL,
    `follow_up_date` date DEFAULT NULL,
    `attachments` text DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `record_id` (`record_id`),
    KEY `patient_id` (`patient_id`),
    KEY `doctor_id` (`doctor_id`),
    KEY `appointment_id` (`appointment_id`),
    KEY `type` (`type`),
    CONSTRAINT `medical_records_patient_id_fk` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `medical_records_doctor_id_fk` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
    CONSTRAINT `medical_records_appointment_id_fk` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user
INSERT INTO `users` (`name`, `email`, `password`, `role`, `phone`, `status`, `email_verified`) VALUES
('Administrator', 'admin@shms.com', '$2y$12$ALx9vTG22ydiwFhat3UMOeUMZ5Pqd50NK4/LsqdHM1jN2rek0ecA6', 'admin', '+1234567890', 'active', 1);

-- Insert default system settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('hospital_name', 'Smart Hospital Management System', 'string', 'Hospital name'),
('hospital_address', '123 Hospital Street, Medical City', 'string', 'Hospital address'),
('hospital_phone', '+1 234 567 8900', 'string', 'Hospital phone number'),
('hospital_email', 'info@shms.com', 'string', 'Hospital email address'),
('appointment_duration', '30', 'number', 'Default appointment duration in minutes'),
('appointment_advance_booking', '7', 'number', 'Maximum days in advance for appointment booking'),
('currency', 'USD', 'string', 'Currency symbol'),
('tax_rate', '0', 'number', 'Tax rate (percentage)'),
('email_notifications', '1', 'boolean', 'Enable email notifications'),
('sms_notifications', '0', 'boolean', 'Enable SMS notifications'),
('auto_backup', '1', 'boolean', 'Enable automatic backup'),
('backup_frequency', 'daily', 'string', 'Backup frequency'),
('session_timeout', '30', 'number', 'Session timeout in minutes'),
('max_file_size', '5', 'number', 'Maximum file upload size in MB'),
('allowed_file_types', '["pdf","doc","docx","jpg","jpeg","png","gif"]', 'json', 'Allowed file types for upload'),
('maintenance_mode', '0', 'boolean', 'Maintenance mode'),
('timezone', 'UTC', 'string', 'System timezone');

-- Insert default departments
INSERT INTO `departments` (`name`, `description`, `status`) VALUES
('General Medicine', 'General medical consultations and treatments', 'active'),
('Cardiology', 'Heart and cardiovascular system treatments', 'active'),
('Neurology', 'Brain and nervous system treatments', 'active'),
('Orthopedics', 'Bone and joint treatments', 'active'),
('Pediatrics', 'Children healthcare', 'active'),
('Gynecology', 'Women healthcare', 'active'),
('Dermatology', 'Skin treatments', 'active'),
('Ophthalmology', 'Eye treatments', 'active'),
('ENT', 'Ear, Nose, and Throat treatments', 'active'),
('Psychiatry', 'Mental health treatments', 'active'),
('Emergency', 'Emergency medical care', 'active'),
('Laboratory', 'Medical laboratory services', 'active'),
('Radiology', 'Medical imaging services', 'active'),
('Pharmacy', 'Medicine dispensing', 'active'),
('Surgery', 'Surgical procedures', 'active');

-- Insert sample lab tests
INSERT INTO `lab_tests` (`test_id`, `name`, `category`, `sample_type`, `normal_range`, `unit`, `price`) VALUES
('CBC001', 'Complete Blood Count', 'Hematology', 'blood', 'RBC: 4.5-5.5 M/cu mm, WBC: 4000-11000 /cu mm, Platelets: 150000-450000 /cu mm', 'cells/mm³', 25.00),
('BMP001', 'Basic Metabolic Panel', 'Chemistry', 'blood', 'Glucose: 70-100 mg/dL, BUN: 7-20 mg/dL, Creatinine: 0.6-1.2 mg/dL', 'mg/dL', 35.00),
('LIP001', 'Lipid Panel', 'Chemistry', 'blood', 'Total Cholesterol: <200 mg/dL, HDL: >40 mg/dL, LDL: <100 mg/dL', 'mg/dL', 40.00),
('URM001', 'Urine Routine', 'Urine', 'urine', 'Color: Pale yellow, Specific gravity: 1.003-1.035, pH: 4.5-8.0', 'various', 15.00),
('THY001', 'Thyroid Function Test', 'Endocrinology', 'blood', 'T3: 80-200 ng/dL, T4: 4.5-12.5 µg/dL, TSH: 0.4-4.0 mIU/L', 'various', 50.00);

-- Insert sample medicines
INSERT INTO `medicines` (`medicine_id`, `name`, `generic_name`, `category`, `unit`, `stock_quantity`, `unit_price`, `selling_price`) VALUES
('MED001', 'Paracetamol 500mg', 'Acetaminophen', 'Analgesic', 'tablet', 1000, 0.50, 1.00),
('MED002', 'Ibuprofen 400mg', 'Ibuprofen', 'NSAID', 'tablet', 500, 0.75, 1.50),
('MED003', 'Amoxicillin 500mg', 'Amoxicillin', 'Antibiotic', 'capsule', 300, 1.20, 2.50),
('MED004', 'Omeprazole 20mg', 'Omeprazole', 'PPI', 'capsule', 200, 0.80, 1.75),
('MED005', 'Metformin 500mg', 'Metformin', 'Antidiabetic', 'tablet', 400, 0.60, 1.25);

-- Create indexes for better performance
CREATE INDEX `idx_appointments_date_time` ON `appointments` (`appointment_date`, `appointment_time`);
CREATE INDEX `idx_lab_requests_status_date` ON `lab_test_requests` (`status`, `requested_date`);
CREATE INDEX `idx_notifications_unread` ON `notifications` (`read`, `created_at`);
CREATE INDEX `idx_messages_unread` ON `messages` (`read`, `created_at`);
CREATE INDEX `idx_activity_logs_user_date` ON `activity_logs` (`user_id`, `created_at`);

-- Create views for common queries
CREATE VIEW `patient_appointments` AS
SELECT 
    p.patient_id, p.first_name, p.last_name, p.phone,
    a.appointment_id, a.appointment_date, a.appointment_time, a.status,
    d.doctor_id, d.first_name as doctor_first_name, d.last_name as doctor_last_name, d.specialization
FROM patients p
JOIN appointments a ON p.id = a.patient_id
JOIN doctors d ON a.doctor_id = d.id;

CREATE VIEW `doctor_schedule` AS
SELECT 
    d.doctor_id, d.first_name, d.last_name, d.specialization,
    a.appointment_date, a.appointment_time, a.status, a.patient_id,
    p.first_name as patient_first_name, p.last_name as patient_last_name
FROM doctors d
LEFT JOIN appointments a ON d.id = a.doctor_id 
    AND a.appointment_date >= CURDATE()
    AND a.status IN ('pending', 'confirmed')
LEFT JOIN patients p ON a.patient_id = p.id
ORDER BY d.doctor_id, a.appointment_date, a.appointment_time;

CREATE VIEW `pending_lab_tests` AS
SELECT 
    r.request_id, r.requested_date, r.status,
    p.patient_id, p.first_name, p.last_name, p.phone,
    t.test_id, t.name as test_name, t.category,
    d.doctor_id, d.first_name as doctor_first_name, d.last_name as doctor_last_name
FROM lab_test_requests r
JOIN patients p ON r.patient_id = p.id
JOIN lab_tests t ON r.test_id = t.id
LEFT JOIN doctors d ON r.doctor_id = d.id
WHERE r.status IN ('pending', 'sample_collected', 'in_progress')
ORDER BY r.requested_date, r.priority;

-- Create stored procedures for common operations
DELIMITER //

CREATE PROCEDURE `GetPatientAppointments`(IN patient_id_param INT)
BEGIN
    SELECT 
        a.appointment_id, a.appointment_date, a.appointment_time, a.type, a.status,
        d.first_name as doctor_first_name, d.last_name as doctor_last_name, d.specialization
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    WHERE a.patient_id = patient_id_param
    ORDER BY a.appointment_date DESC, a.appointment_time DESC;
END //

CREATE PROCEDURE `GetDoctorAppointments`(IN doctor_id_param INT, IN date_param DATE)
BEGIN
    SELECT 
        a.appointment_id, a.appointment_time, a.type, a.status,
        p.patient_id, p.first_name, p.last_name, p.phone
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id = doctor_id_param AND a.appointment_date = date_param
    ORDER BY a.appointment_time;
END //

CREATE PROCEDURE `UpdateMedicineStock`(IN medicine_id_param INT, IN quantity_change INT)
BEGIN
    DECLARE current_stock INT;
    
    SELECT stock_quantity INTO current_stock 
    FROM medicines 
    WHERE id = medicine_id_param;
    
    UPDATE medicines 
    SET stock_quantity = current_stock + quantity_change,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = medicine_id_param;
    
    -- Check if stock is below reorder level
    IF (current_stock + quantity_change) <= (SELECT reorder_level FROM medicines WHERE id = medicine_id_param) THEN
        INSERT INTO notifications (user_id, title, message, type, link)
        SELECT id, 'Low Stock Alert', 
               CONCAT('Medicine ', name, ' stock is below reorder level'), 
               'system', 'pharmacy/index.php'
        FROM users 
        WHERE role = 'pharmacist';
    END IF;
END //

DELIMITER ;

-- Create triggers for data integrity
DELIMITER //

CREATE TRIGGER `before_patient_insert` 
BEFORE INSERT ON `patients`
FOR EACH ROW
BEGIN
    IF NEW.patient_id IS NULL OR NEW.patient_id = '' THEN
        SET NEW.patient_id = CONCAT('PAT', LPAD(CONNECTION_ID(), 6, '0'));
    END IF;
END //

CREATE TRIGGER `before_doctor_insert` 
BEFORE INSERT ON `doctors`
FOR EACH ROW
BEGIN
    IF NEW.doctor_id IS NULL OR NEW.doctor_id = '' THEN
        SET NEW.doctor_id = CONCAT('DOC', LPAD(CONNECTION_ID(), 6, '0'));
    END IF;
END //

CREATE TRIGGER `before_appointment_insert` 
BEFORE INSERT ON `appointments`
FOR EACH ROW
BEGIN
    IF NEW.appointment_id IS NULL OR NEW.appointment_id = '' THEN
        SET NEW.appointment_id = CONCAT('APT', LPAD(CONNECTION_ID(), 6, '0'));
    END IF;
END //

CREATE TRIGGER `after_appointment_insert` 
AFTER INSERT ON `appointments`
FOR EACH ROW
BEGIN
    -- Create notification for doctor
    INSERT INTO notifications (user_id, title, message, type, link)
    SELECT u.id, 
           'New Appointment', 
           CONCAT('New appointment scheduled on ', NEW.appointment_date, ' at ', NEW.appointment_time),
           'appointment',
           CONCAT('appointments/view.php?id=', NEW.id)
    FROM users u
    JOIN doctors d ON u.id = d.user_id
    WHERE d.id = NEW.doctor_id;
    
    -- Create notification for patient
    INSERT INTO notifications (user_id, title, message, type, link)
    SELECT u.id,
           'Appointment Confirmed',
           CONCAT('Your appointment is confirmed for ', NEW.appointment_date, ' at ', NEW.appointment_time),
           'appointment',
           CONCAT('appointments/view.php?id=', NEW.id)
    FROM users u
    JOIN patients p ON u.id = p.user_id
    WHERE p.id = NEW.patient_id;
END //

DELIMITER ;

-- Final database setup complete
SELECT 'Smart Hospital Management System Database Setup Complete!' as message;
