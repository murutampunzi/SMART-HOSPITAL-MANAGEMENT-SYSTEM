-- =========================================================
-- SMART HOSPITAL MANAGEMENT SYSTEM (SHMS)
-- RADIOLOGY MODULE DATABASE
-- File: radiology_tables.sql
-- =========================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";



-- =========================================================
-- TABLE: radiology_tests
-- =========================================================

CREATE TABLE IF NOT EXISTS radiology_tests (
    id INT(11) NOT NULL AUTO_INCREMENT,

    name VARCHAR(100) NOT NULL,

    description TEXT DEFAULT NULL,

    modality ENUM(
        'X-Ray',
        'CT',
        'MRI',
        'Ultrasound',
        'Mammography',
        'Fluoroscopy',
        'Nuclear Medicine',
        'PET Scan'
    ) NOT NULL,

    contrast_required TINYINT(1) DEFAULT 0,

    preparation_instructions TEXT DEFAULT NULL,

    duration_minutes INT(11) DEFAULT 30,

    price DECIMAL(10,2) DEFAULT 0.00,

    status ENUM('active','inactive')
        DEFAULT 'active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    KEY idx_modality (modality),

    KEY idx_status (status)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;



-- =========================================================
-- TABLE: radiology_requests
-- =========================================================

CREATE TABLE IF NOT EXISTS radiology_requests (

    id INT(11) NOT NULL AUTO_INCREMENT,

    request_id VARCHAR(20) NOT NULL,

    patient_id INT(11) NOT NULL,

    doctor_id INT(11) DEFAULT NULL,

    test_id INT(11) NOT NULL,

    requested_date DATE NOT NULL,

    scheduled_date DATE DEFAULT NULL,

    scheduled_time TIME DEFAULT NULL,

    priority ENUM(
        'routine',
        'urgent',
        'stat'
    ) DEFAULT 'routine',

    status ENUM(
        'pending',
        'scheduled',
        'in_progress',
        'completed',
        'cancelled'
    ) DEFAULT 'pending',

    clinical_indication TEXT DEFAULT NULL,

    contrast_allergy TINYINT(1) DEFAULT 0,

    pregnancy TINYINT(1) DEFAULT 0,

    notes TEXT DEFAULT NULL,

    cancelled_by ENUM(
        'patient',
        'doctor',
        'admin',
        'technician'
    ) DEFAULT NULL,

    cancellation_reason TEXT DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uk_request_id (request_id),

    KEY idx_patient_id (patient_id),

    KEY idx_doctor_id (doctor_id),

    KEY idx_test_id (test_id),

    KEY idx_status (status),

    KEY idx_priority (priority),

    KEY idx_requested_date (requested_date),

    CONSTRAINT fk_radiology_test
        FOREIGN KEY (test_id)
        REFERENCES radiology_tests(id)
        ON DELETE CASCADE

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;



-- =========================================================
-- TABLE: radiology_images
-- =========================================================

CREATE TABLE IF NOT EXISTS radiology_images (

    id INT(11) NOT NULL AUTO_INCREMENT,

    request_id INT(11) NOT NULL,

    image_path VARCHAR(255) DEFAULT NULL,

    image_type ENUM(
        'original',
        'processed',
        'annotated'
    ) DEFAULT 'original',

    image_description TEXT DEFAULT NULL,

    upload_date DATETIME DEFAULT NULL,

    uploaded_by INT(11) DEFAULT NULL,

    report_path VARCHAR(255) DEFAULT NULL,

    report_text LONGTEXT DEFAULT NULL,

    findings LONGTEXT DEFAULT NULL,

    impression LONGTEXT DEFAULT NULL,

    recommendation LONGTEXT DEFAULT NULL,

    radiologist_id INT(11) DEFAULT NULL,

    report_date DATETIME DEFAULT NULL,

    status ENUM(
        'pending_review',
        'reviewed',
        'signed'
    ) DEFAULT 'pending_review',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    KEY idx_request_id (request_id),

    KEY idx_status (status),

    CONSTRAINT fk_radiology_request
        FOREIGN KEY (request_id)
        REFERENCES radiology_requests(id)
        ON DELETE CASCADE

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;



-- =========================================================
-- SAMPLE DATA: radiology_tests
-- =========================================================

INSERT INTO radiology_tests
(
    name,
    description,
    modality,
    contrast_required,
    preparation_instructions,
    duration_minutes,
    price
)
VALUES

(
    'Chest X-Ray',
    'Standard chest radiography',
    'X-Ray',
    0,
    'No preparation needed',
    15,
    50.00
),

(
    'Abdominal X-Ray',
    'Abdominal radiography series',
    'X-Ray',
    0,
    'No food or water 4 hours before',
    20,
    75.00
),

(
    'Head CT',
    'Computed tomography of head',
    'CT',
    1,
    'No preparation needed',
    25,
    150.00
),

(
    'Chest CT',
    'CT scan of chest with contrast',
    'CT',
    1,
    'No food 4 hours before',
    30,
    200.00
),

(
    'Brain MRI',
    'Magnetic resonance imaging of brain',
    'MRI',
    0,
    'Remove metal objects',
    45,
    350.00
),

(
    'Spine MRI',
    'MRI of spinal column',
    'MRI',
    0,
    'Remove metal objects',
    50,
    400.00
),

(
    'Abdominal Ultrasound',
    'Ultrasound of abdominal organs',
    'Ultrasound',
    0,
    'No food 6 hours before',
    25,
    80.00
),

(
    'Pelvic Ultrasound',
    'Pelvic ultrasound examination',
    'Ultrasound',
    0,
    'Drink water before exam',
    30,
    100.00
),

(
    'Mammography',
    'Breast cancer screening',
    'Mammography',
    0,
    'No deodorant or powder',
    20,
    120.00
),

(
    'PET-CT',
    'Positron emission tomography scan',
    'PET Scan',
    1,
    'No food 6 hours before',
    90,
    800.00
);



-- =========================================================
-- TRIGGER: Generate Request ID
-- =========================================================

DELIMITER $$

CREATE TRIGGER before_radiology_request_insert
BEFORE INSERT ON radiology_requests
FOR EACH ROW
BEGIN

    IF NEW.request_id IS NULL
       OR NEW.request_id = '' THEN

        SET NEW.request_id =
            CONCAT(
                'RAD-',
                DATE_FORMAT(NOW(), '%Y%m%d'),
                '-',
                LPAD(FLOOR(RAND() * 9999), 4, '0')
            );

    END IF;

END$$

DELIMITER ;



-- =========================================================
-- VIEW: radiology_dashboard
-- =========================================================

CREATE VIEW radiology_dashboard AS

SELECT

    rr.id,
    rr.request_id,
    rr.patient_id,
    rr.doctor_id,

    rt.name AS test_name,

    rt.modality,

    rr.requested_date,

    rr.scheduled_date,

    rr.scheduled_time,

    rr.priority,

    rr.status,

    rr.created_at

FROM radiology_requests rr

INNER JOIN radiology_tests rt
    ON rr.test_id = rt.id;



-- =========================================================
-- STORED PROCEDURE:
-- GetRadiologyStatistics
-- =========================================================

DELIMITER $$

CREATE PROCEDURE GetRadiologyStatistics()
BEGIN

    SELECT
        'total_requests' AS statistic,
        COUNT(*) AS total
    FROM radiology_requests

    UNION ALL

    SELECT
        'pending_requests',
        COUNT(*)
    FROM radiology_requests
    WHERE status = 'pending'

    UNION ALL

    SELECT
        'completed_requests',
        COUNT(*)
    FROM radiology_requests
    WHERE status = 'completed'

    UNION ALL

    SELECT
        'urgent_requests',
        COUNT(*)
    FROM radiology_requests
    WHERE priority = 'urgent';

END$$

DELIMITER ;



COMMIT;