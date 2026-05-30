<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role (Doctors or Admins can perform consultation)
requireLogin();
requireAnyRole(['admin', 'doctor']);

$page_title = "Consultation - Smart Hospital Management System";
$page_heading = "Patient Consultation";

$error = '';
$success = '';

// Get appointment ID
$appointment_record_id = intval($_GET['id'] ?? 0);
if ($appointment_record_id <= 0) {
    redirect('index.php?error=Invalid appointment ID');
}

// Fetch appointment details
$appointment_sql = "SELECT a.*, p.first_name as patient_first_name, p.last_name as patient_last_name, p.phone as patient_phone, p.email as patient_email, p.date_of_birth, p.gender, p.blood_group, p.medical_history, p.allergies, p.current_medications,
                           d.id as doctor_id, d.first_name as doctor_first_name, d.last_name as doctor_last_name, d.specialization, d.consultation_fee
                    FROM appointments a 
                    JOIN patients p ON a.patient_id = p.id 
                    JOIN doctors d ON a.doctor_id = d.id 
                    WHERE a.id = ?";
$stmt = prepare($appointment_sql);
$stmt->bind_param("i", $appointment_record_id);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();

if (!$appointment) {
    redirect('index.php?error=Appointment not found');
}

// Check if user is a doctor and it is their own appointment (unless admin)
if (hasRole('doctor')) {
    $current_user_id = $_SESSION['user_id'];
    $doctor_check_sql = "SELECT id FROM doctors WHERE user_id = ?";
    $doctor_check_stmt = prepare($doctor_check_sql);
    $doctor_check_stmt->bind_param("i", $current_user_id);
    $doctor_check_stmt->execute();
    $doctor_record = $doctor_check_stmt->get_result()->fetch_assoc();
    
    if (!$doctor_record || $doctor_record['id'] != $appointment['doctor_id']) {
        redirect('index.php?error=Access denied: This appointment is assigned to another doctor');
    }
}

// Fetch lists of medicines, lab tests, and radiology tests
$medicines = query("SELECT id, name, generic_name, unit, selling_price, stock_quantity FROM medicines WHERE status = 'active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$lab_tests = query("SELECT id, name, price, category FROM lab_tests WHERE status = 'active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$radiology_tests = query("SELECT id, name, price, modality FROM radiology_tests WHERE status = 'active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Handle consultation submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        // Collect form data
        $symptoms = sanitizeInput($_POST['symptoms'] ?? '');
        $diagnosis = sanitizeInput($_POST['diagnosis'] ?? '');
        $treatment = sanitizeInput($_POST['treatment'] ?? '');
        $notes = sanitizeInput($_POST['notes'] ?? '');
        $follow_up_date = sanitizeInput($_POST['follow_up_date'] ?? '');
        
        // Vitals
        $bp = sanitizeInput($_POST['bp'] ?? '');
        $temp = sanitizeInput($_POST['temp'] ?? '');
        $pulse = sanitizeInput($_POST['pulse'] ?? '');
        $resp = sanitizeInput($_POST['resp'] ?? '');
        $weight = sanitizeInput($_POST['weight'] ?? '');
        $height = sanitizeInput($_POST['height'] ?? '');
        
        $vitals_str = "BP: $bp | Temp: {$temp}°C | Pulse: $pulse bpm | Resp: $resp bpm | Weight: $weight kg | Height: $height cm";
        
        // Validate inputs
        if (empty($diagnosis)) {
            $error = 'Diagnosis is required.';
        } elseif (empty($treatment)) {
            $error = 'Treatment plan is required.';
        } else {
            try {
                // Begin Transaction
                $conn->begin_transaction();
                
                // 1. Update appointment status
                $update_apt = prepare("UPDATE appointments SET status = 'completed', symptoms = ?, notes = ? WHERE id = ?");
                $update_apt->bind_param("ssi", $symptoms, $notes, $appointment_record_id);
                if (!$update_apt->execute()) {
                    throw new Exception('Failed to update appointment status.');
                }
                
                // 2. Create medical record
                $record_id = 'REC' . str_pad($appointment_record_id, 6, '0', STR_PAD_LEFT);
                $rec_desc = "Vitals: " . $vitals_str . "\nNotes: " . $notes;
                
                $med_rec_sql = "INSERT INTO medical_records (record_id, patient_id, doctor_id, appointment_id, type, title, description, diagnosis, treatment, follow_up_date) 
                                VALUES (?, ?, ?, ?, 'consultation', 'Consultation Visit', ?, ?, ?, ?)";
                $med_rec_stmt = prepare($med_rec_sql);
                $patient_id = $appointment['patient_id'];
                $doctor_id = $appointment['doctor_id'];
                
                $db_follow_up = !empty($follow_up_date) ? $follow_up_date : null;
                $med_rec_stmt->bind_param("siiissss", $record_id, $patient_id, $doctor_id, $appointment_record_id, $rec_desc, $diagnosis, $treatment, $db_follow_up);
                
                if (!$med_rec_stmt->execute()) {
                    throw new Exception('Failed to create medical record.');
                }
                
                // 3. Create prescription (if medicines selected)
                $medicine_ids = $_POST['medicine_id'] ?? [];
                $dosages = $_POST['dosage'] ?? [];
                $frequencies = $_POST['frequency'] ?? [];
                $durations = $_POST['duration'] ?? [];
                $instructions = $_POST['instructions'] ?? [];
                $quantities = $_POST['quantity'] ?? [];
                
                if (!empty($medicine_ids)) {
                    $prescription_id = 'PRSC' . str_pad($appointment_record_id, 6, '0', STR_PAD_LEFT);
                    $prescription_sql = "INSERT INTO prescriptions (prescription_id, appointment_id, patient_id, doctor_id, diagnosis, notes, status) 
                                         VALUES (?, ?, ?, ?, ?, ?, 'active')";
                    $prsc_stmt = prepare($prescription_sql);
                    $prsc_stmt->bind_param("siiiss", $prescription_id, $appointment_record_id, $patient_id, $doctor_id, $diagnosis, $treatment);
                    
                    if (!$prsc_stmt->execute()) {
                        throw new Exception('Failed to create prescription.');
                    }
                    
                    $presc_record_id = insertId();
                    
                    for ($i = 0; $i < count($medicine_ids); $i++) {
                        if (!empty($medicine_ids[$i])) {
                            $m_id = intval($medicine_ids[$i]);
                            $m_dosage = sanitizeInput($dosages[$i] ?? '');
                            $m_freq = sanitizeInput($frequencies[$i] ?? '');
                            $m_dur = sanitizeInput($durations[$i] ?? '');
                            $m_inst = sanitizeInput($instructions[$i] ?? '');
                            $m_qty = intval($quantities[$i] ?? 1);
                            
                            $pm_sql = "INSERT INTO prescription_medicines (prescription_id, medicine_id, dosage, frequency, duration, instructions, quantity) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?)";
                            $pm_stmt = prepare($pm_sql);
                            $pm_stmt->bind_param("iissssi", $presc_record_id, $m_id, $m_dosage, $m_freq, $m_dur, $m_inst, $m_qty);
                            
                            if (!$pm_stmt->execute()) {
                                throw new Exception('Failed to add prescription medicine.');
                            }
                        }
                    }
                }
                
                // 4. Create Laboratory Test Requests
                $lab_test_ids = $_POST['lab_test_id'] ?? [];
                $lab_priorities = $_POST['lab_priority'] ?? [];
                $lab_notes = $_POST['lab_notes'] ?? [];
                
                for ($i = 0; $i < count($lab_test_ids); $i++) {
                    if (!empty($lab_test_ids[$i])) {
                        $lt_id = intval($lab_test_ids[$i]);
                        $l_priority = sanitizeInput($lab_priorities[$i] ?? 'normal');
                        $l_notes = sanitizeInput($lab_notes[$i] ?? '');
                        $lab_req_id = 'LAB-' . date('Ymd') . '-' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
                        
                        $lab_sql = "INSERT INTO lab_test_requests (request_id, patient_id, doctor_id, test_id, appointment_id, requested_date, status, priority, notes) 
                                    VALUES (?, ?, ?, ?, ?, CURDATE(), 'pending', ?, ?)";
                        $lab_stmt = prepare($lab_sql);
                        $lab_stmt->bind_param("siiiiss", $lab_req_id, $patient_id, $doctor_id, $lt_id, $appointment_record_id, $l_priority, $l_notes);
                        
                        if (!$lab_stmt->execute()) {
                            throw new Exception('Failed to request laboratory test.');
                        }
                    }
                }
                
                // 5. Create Radiology Scan Requests
                $rad_test_ids = $_POST['rad_test_id'] ?? [];
                $rad_priorities = $_POST['rad_priority'] ?? [];
                $rad_indications = $_POST['rad_indication'] ?? [];
                $rad_notes = $_POST['rad_notes'] ?? [];
                
                for ($i = 0; $i < count($rad_test_ids); $i++) {
                    if (!empty($rad_test_ids[$i])) {
                        $rt_id = intval($rad_test_ids[$i]);
                        $r_priority = sanitizeInput($rad_priorities[$i] ?? 'routine');
                        $r_indication = sanitizeInput($rad_indications[$i] ?? '');
                        $r_notes = sanitizeInput($rad_notes[$i] ?? '');
                        
                        // We let the trigger handle request_id, or we can pass empty string.
                        $rad_sql = "INSERT INTO radiology_requests (request_id, patient_id, doctor_id, test_id, requested_date, priority, status, clinical_indication, notes) 
                                    VALUES ('', ?, ?, ?, CURDATE(), ?, 'pending', ?, ?)";
                        $rad_stmt = prepare($rad_sql);
                        $rad_stmt->bind_param("iiisss", $patient_id, $doctor_id, $rt_id, $r_priority, $r_indication, $r_notes);
                        
                        if (!$rad_stmt->execute()) {
                            throw new Exception('Failed to request radiology scan.');
                        }
                    }
                }
                
                // 6. Generate Invoice for Doctor's Consultation Fee
                $fee = floatval($appointment['consultation_fee']);
                if ($fee > 0) {
                    $invoice_id = 'INV' . str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
                    $invoice_date = date('Y-m-d');
                    $due_date = date('Y-m-d', strtotime('+3 days'));
                    $invoice_notes = "Consultation Fee for Appointment ID: " . $appointment['appointment_id'];
                    $created_by_user = $_SESSION['user_id'];
                    
                    $invoice_sql = "INSERT INTO invoices (invoice_id, patient_id, appointment_id, invoice_date, due_date, subtotal, tax_amount, discount_amount, total_amount, paid_amount, balance_amount, status, notes, created_by) 
                                    VALUES (?, ?, ?, ?, ?, ?, 0.00, 0.00, ?, 0.00, ?, 'sent', ?, ?)";
                    $inv_stmt = prepare($invoice_sql);
                    $inv_stmt->bind_param("siissdddsi", $invoice_id, $patient_id, $appointment_record_id, $invoice_date, $due_date, $fee, $fee, $fee, $invoice_notes, $created_by_user);
                    
                    if (!$inv_stmt->execute()) {
                        throw new Exception('Failed to generate billing invoice.');
                    }
                    
                    $new_invoice_record_id = insertId();
                    
                    $item_sql = "INSERT INTO invoice_items (invoice_id, item_type, item_id, description, quantity, unit_price, total_price) 
                                 VALUES (?, 'consultation', ?, 'Doctor Consultation Fee', 1, ?, ?)";
                    $item_stmt = prepare($item_sql);
                    $item_stmt->bind_param("iidd", $new_invoice_record_id, $doctor_id, $fee, $fee);
                    
                    if (!$item_stmt->execute()) {
                        throw new Exception('Failed to create invoice billing item.');
                    }
                }
                
                // Commit
                $conn->commit();
                
                logActivity('consultation_completed', "Doctor completed consultation for appointment: " . $appointment['appointment_id']);
                
                $success = "Consultation recorded successfully! Redirecting you back to appointment details...";
                header('Refresh: 2; url=view.php?id=' . $appointment_record_id);
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Transaction failed: " . $e->getMessage();
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Patient Details Summary Sidebar -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm border-0 sticky-lg-top" style="top: 80px; z-index: 1;">
                <div class="card-header bg-gradient-primary text-white py-3">
                    <h5 class="card-title mb-0"><i class="fas fa-id-card me-2"></i>Patient Profile</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="rounded-circle bg-light text-primary d-inline-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 75px; height: 75px; font-size: 2rem;">
                            <?php echo strtoupper(substr($appointment['patient_first_name'], 0, 1) . substr($appointment['patient_last_name'], 0, 1)); ?>
                        </div>
                        <h4 class="mt-3 mb-0"><?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></h4>
                        <span class="badge bg-secondary">Patient</span>
                    </div>
                    
                    <hr>
                    
                    <div class="row g-2 small">
                        <div class="col-6 text-muted">Gender:</div>
                        <div class="col-6 fw-bold text-dark"><?php echo ucfirst($appointment['gender']); ?></div>
                        
                        <div class="col-6 text-muted">Blood Group:</div>
                        <div class="col-6 fw-bold text-danger"><?php echo htmlspecialchars($appointment['blood_group'] ?: 'Not Specified'); ?></div>
                        
                        <div class="col-6 text-muted">Age / DOB:</div>
                        <div class="col-6 fw-bold text-dark">
                            <?php 
                            $dob = new DateTime($appointment['date_of_birth']);
                            $diff = $dob->diff(new DateTime());
                            echo $diff->y . " yrs (" . formatDate($appointment['date_of_birth']) . ")";
                            ?>
                        </div>
                        
                        <div class="col-6 text-muted">Phone:</div>
                        <div class="col-6 fw-bold text-dark"><?php echo htmlspecialchars($appointment['patient_phone']); ?></div>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <h6 class="text-danger fw-bold"><i class="fas fa-exclamation-triangle me-1"></i>Allergies</h6>
                        <div class="bg-light p-2 rounded text-dark small border-start border-danger border-3">
                            <?php echo nl2br(htmlspecialchars($appointment['allergies'] ?: 'No allergies recorded')); ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-primary fw-bold"><i class="fas fa-notes-medical me-1"></i>Medical History</h6>
                        <div class="bg-light p-2 rounded text-dark small">
                            <?php echo nl2br(htmlspecialchars($appointment['medical_history'] ?: 'No medical history recorded')); ?>
                        </div>
                    </div>
                    
                    <div class="mb-0">
                        <h6 class="text-info fw-bold"><i class="fas fa-pills me-1"></i>Current Medications</h6>
                        <div class="bg-light p-2 rounded text-dark small">
                            <?php echo nl2br(htmlspecialchars($appointment['current_medications'] ?: 'No current medications recorded')); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Consultation Form -->
        <div class="col-lg-8">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-gradient-success text-white py-3">
                    <h5 class="card-title mb-0"><i class="fas fa-stethoscope me-2"></i>Active Session: Consultation</h5>
                </div>
                
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <?php echo getCSRFInput(); ?>
                        
                        <!-- Tabs Navigation -->
                        <ul class="nav nav-pills nav-fill mb-4 bg-light p-2 rounded" id="consultationTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="notes-tab" data-bs-toggle="tab" data-bs-target="#notesTab" type="button" role="tab"><i class="fas fa-file-signature me-1"></i>1. Notes & Vitals</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="prescriptions-tab" data-bs-toggle="tab" data-bs-target="#prescriptionsTab" type="button" role="tab"><i class="fas fa-prescription-bottle-alt me-1"></i>2. Prescriptions</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="orders-tab" data-bs-toggle="tab" data-bs-target="#ordersTab" type="button" role="tab"><i class="fas fa-microscope me-1"></i>3. Lab & Radiology</button>
                            </li>
                        </ul>

                        <!-- Tabs Content -->
                        <div class="tab-content" id="consultationTabsContent">
                            
                            <!-- TAB 1: Notes and Vitals -->
                            <div class="tab-pane fade show active" id="notesTab" role="tabpanel">
                                <!-- Vitals Section -->
                                <h6 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-heartbeat me-1"></i>Patient Vital Signs</h6>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-4">
                                        <label for="bp" class="form-label">Blood Pressure</label>
                                        <input type="text" class="form-control" id="bp" name="bp" placeholder="e.g. 120/80">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="temp" class="form-label">Temperature (°C)</label>
                                        <input type="number" step="0.1" class="form-control" id="temp" name="temp" placeholder="e.g. 36.5">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="pulse" class="form-label">Pulse Rate (bpm)</label>
                                        <input type="number" class="form-control" id="pulse" name="pulse" placeholder="e.g. 72">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="resp" class="form-label">Respiratory Rate (bpm)</label>
                                        <input type="number" class="form-control" id="resp" name="resp" placeholder="e.g. 16">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="weight" class="form-label">Weight (kg)</label>
                                        <input type="number" step="0.1" class="form-control" id="weight" name="weight" placeholder="e.g. 70">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="height" class="form-label">Height (cm)</label>
                                        <input type="number" class="form-control" id="height" name="height" placeholder="e.g. 175">
                                    </div>
                                </div>
                                
                                <!-- Clinical Info Section -->
                                <h6 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-edit me-1"></i>Clinical Findings & Notes</h6>
                                
                                <div class="mb-3">
                                    <label for="symptoms" class="form-label">Chief Complaints / Symptoms</label>
                                    <textarea class="form-control" id="symptoms" name="symptoms" rows="3" placeholder="Enter patient symptoms..."><?php echo htmlspecialchars($appointment['reason']); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="diagnosis" class="form-label">Diagnosis *</label>
                                    <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3" placeholder="Enter primary diagnosis..." required></textarea>
                                    <div class="invalid-feedback">Please enter a diagnosis.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="treatment" class="form-label">Treatment Plan / Advice *</label>
                                    <textarea class="form-control" id="treatment" name="treatment" rows="4" placeholder="Advise patient on therapy, precautions, rest, etc..." required></textarea>
                                    <div class="invalid-feedback">Please enter a treatment plan.</div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="follow_up_date" class="form-label">Follow-up Date</label>
                                        <input type="date" class="form-control" id="follow_up_date" name="follow_up_date" min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="notes" class="form-label">Internal Clinical Notes</label>
                                        <input type="text" class="form-control" id="notes" name="notes" placeholder="Confidential clinical notes...">
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end mt-3">
                                    <button type="button" class="btn btn-primary" onclick="switchTab('prescriptions-tab')">Next: Prescriptions <i class="fas fa-arrow-right ms-1"></i></button>
                                </div>
                            </div>
                            
                            <!-- TAB 2: Prescriptions -->
                            <div class="tab-pane fade" id="prescriptionsTab" role="tabpanel">
                                <h6 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-pills me-1"></i>Prescribe Medications</h6>
                                
                                <div id="prescribedMedicines">
                                    <!-- Dynamic Medicine Rows go here -->
                                </div>
                                
                                <button type="button" class="btn btn-outline-primary btn-sm mb-4" onclick="addMedicineRow()">
                                    <i class="fas fa-plus me-1"></i>Add Medicine
                                </button>
                                
                                <div class="d-flex justify-content-between mt-3">
                                    <button type="button" class="btn btn-secondary" onclick="switchTab('notes-tab')"><i class="fas fa-arrow-left me-1"></i> Back</button>
                                    <button type="button" class="btn btn-primary" onclick="switchTab('orders-tab')">Next: Lab & Radiology <i class="fas fa-arrow-right ms-1"></i></button>
                                </div>
                            </div>
                            
                            <!-- TAB 3: Lab and Radiology Orders -->
                            <div class="tab-pane fade" id="ordersTab" role="tabpanel">
                                
                                <!-- Laboratory Section -->
                                <h6 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-flask me-1"></i>Laboratory Test Orders</h6>
                                <div id="labTestOrders" class="mb-3">
                                    <!-- Dynamic Lab Rows go here -->
                                </div>
                                <button type="button" class="btn btn-outline-info btn-sm mb-4" onclick="addLabRow()">
                                    <i class="fas fa-plus me-1"></i>Order Laboratory Test
                                </button>
                                
                                <!-- Radiology Section -->
                                <h6 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-x-ray me-1"></i>Radiology / Imaging Orders</h6>
                                <div id="radiologyOrders" class="mb-3">
                                    <!-- Dynamic Radiology Rows go here -->
                                </div>
                                <button type="button" class="btn btn-outline-success btn-sm mb-4" onclick="addRadiologyRow()">
                                    <i class="fas fa-plus me-1"></i>Order Radiology Scan
                                </button>
                                
                                <!-- Submission Area -->
                                <hr class="my-4">
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-secondary" onclick="switchTab('prescriptions-tab')"><i class="fas fa-arrow-left me-1"></i> Back</button>
                                    <div class="d-flex gap-2">
                                        <a href="view.php?id=<?php echo $appointment_record_id; ?>" class="btn btn-outline-secondary">Cancel</a>
                                        <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i> Complete Consultation</button>
                                    </div>
                                </div>
                            </div>
                            
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Template scripts for generating dynamic rows -->
<script>
function switchTab(tabId) {
    const tabTrigger = new bootstrap.Tab(document.getElementById(tabId));
    tabTrigger.show();
}

// Medicine rows
const medicinesList = <?php echo json_encode($medicines); ?>;
function addMedicineRow() {
    const container = document.getElementById('prescribedMedicines');
    const rowId = 'med_row_' + Date.now();
    
    let optionsHtml = '<option value="">-- Choose Medicine --</option>';
    medicinesList.forEach(med => {
        const qtyText = med.stock_quantity > 0 ? `(Stock: ${med.stock_quantity})` : '(Out of Stock)';
        optionsHtml += `<option value="${med.id}">${med.name} [${med.generic_name}] ${qtyText}</option>`;
    });
    
    const rowHtml = `
        <div class="row g-2 align-items-center mb-3 p-3 bg-light border rounded position-relative" id="${rowId}">
            <button type="button" class="btn-close position-absolute" style="top: 10px; right: 10px;" onclick="document.getElementById('${rowId}').remove()"></button>
            <div class="col-md-5">
                <label class="form-label small fw-bold">Medicine</label>
                <select class="form-select" name="medicine_id[]" required>
                    ${optionsHtml}
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Dosage</label>
                <input type="text" class="form-control" name="dosage[]" placeholder="e.g. 500mg / 1 tab" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Frequency</label>
                <select class="form-select" name="frequency[]" required>
                    <option value="Daily">Daily</option>
                    <option value="Twice daily">Twice daily (BD)</option>
                    <option value="Three times daily">Three times daily (TDS)</option>
                    <option value="Four times daily">Four times daily (QDS)</option>
                    <option value="As needed">As needed (PRN)</option>
                    <option value="At bedtime">At bedtime (HS)</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Duration</label>
                <input type="text" class="form-control" name="duration[]" placeholder="e.g. 5 days" required>
            </div>
            <div class="col-md-1">
                <label class="form-label small fw-bold">Qty</label>
                <input type="number" class="form-control" name="quantity[]" value="1" min="1" required>
            </div>
            <div class="col-md-11 mt-2">
                <input type="text" class="form-control form-control-sm" name="instructions[]" placeholder="Special administration instructions (e.g. After food)...">
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', rowHtml);
}

// Laboratory rows
const labTestsList = <?php echo json_encode($lab_tests); ?>;
function addLabRow() {
    const container = document.getElementById('labTestOrders');
    const rowId = 'lab_row_' + Date.now();
    
    let optionsHtml = '<option value="">-- Choose Lab Test --</option>';
    labTestsList.forEach(test => {
        optionsHtml += `<option value="${test.id}">${test.name} (${test.category})</option>`;
    });
    
    const rowHtml = `
        <div class="row g-2 align-items-center mb-3 p-3 bg-light border rounded position-relative" id="${rowId}">
            <button type="button" class="btn-close position-absolute" style="top: 10px; right: 10px;" onclick="document.getElementById('${rowId}').remove()"></button>
            <div class="col-md-6">
                <label class="form-label small fw-bold">Test Name</label>
                <select class="form-select" name="lab_test_id[]" required>
                    ${optionsHtml}
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-bold">Priority</label>
                <select class="form-select" name="lab_priority[]">
                    <option value="normal">Normal</option>
                    <option value="urgent">Urgent</option>
                    <option value="stat">STAT</option>
                </select>
            </div>
            <div class="col-md-12 mt-2">
                <input type="text" class="form-control form-control-sm" name="lab_notes[]" placeholder="Clinical indications / instructions for lab technician...">
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', rowHtml);
}

// Radiology rows
const radTestsList = <?php echo json_encode($radiology_tests); ?>;
function addRadiologyRow() {
    const container = document.getElementById('radiologyOrders');
    const rowId = 'rad_row_' + Date.now();
    
    let optionsHtml = '<option value="">-- Choose Radiology Scan --</option>';
    radTestsList.forEach(test => {
        optionsHtml += `<option value="${test.id}">${test.name} [${test.modality}]</option>`;
    });
    
    const rowHtml = `
        <div class="row g-2 align-items-center mb-3 p-3 bg-light border rounded position-relative" id="${rowId}">
            <button type="button" class="btn-close position-absolute" style="top: 10px; right: 10px;" onclick="document.getElementById('${rowId}').remove()"></button>
            <div class="col-md-6">
                <label class="form-label small fw-bold">Modality / Scan Name</label>
                <select class="form-select" name="rad_test_id[]" required>
                    ${optionsHtml}
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-bold">Priority</label>
                <select class="form-select" name="rad_priority[]">
                    <option value="routine">Routine</option>
                    <option value="urgent">Urgent</option>
                    <option value="stat">STAT</option>
                </select>
            </div>
            <div class="col-md-12 mt-2">
                <input type="text" class="form-control form-control-sm mb-1" name="rad_indication[]" placeholder="Clinical indications (e.g. chronic cough, suspected fracture)..." required>
                <input type="text" class="form-control form-control-sm" name="rad_notes[]" placeholder="Instructions (e.g. views required, special preparation)...">
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', rowHtml);
}

// Form Validation
(function () {
  'use strict'
  var forms = document.querySelectorAll('.needs-validation')
  Array.prototype.slice.call(forms).forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!form.checkValidity()) {
        event.preventDefault()
        event.stopPropagation()
      }
      form.classList.add('was-validated')
    }, false)
  })
})()
</script>

<?php include '../includes/footer.php'; ?>
