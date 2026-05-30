<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'pharmacist', 'doctor']);

// Fetch active patients and doctors
$patients = query("SELECT id, patient_id, first_name, last_name FROM patients WHERE status = 'active' ORDER BY first_name, last_name")->fetch_all(MYSQLI_ASSOC);
$doctors = query("SELECT id, doctor_id, first_name, last_name, specialization FROM doctors WHERE status = 'active' ORDER BY first_name, last_name")->fetch_all(MYSQLI_ASSOC);
$medicines = query("SELECT id, name, generic_name, unit, stock_quantity FROM medicines WHERE status = 'active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request token.';
    } else {
        $patient_id = intval($_POST['patient_id'] ?? 0);
        $doctor_id = intval($_POST['doctor_id'] ?? 0);
        $diagnosis = sanitizeInput($_POST['diagnosis'] ?? '');
        $notes = sanitizeInput($_POST['notes'] ?? '');
        
        $medicine_ids = $_POST['medicine_id'] ?? [];
        $dosages = $_POST['dosage'] ?? [];
        $frequencies = $_POST['frequency'] ?? [];
        $durations = $_POST['duration'] ?? [];
        $instructions = $_POST['instructions'] ?? [];
        $quantities = $_POST['quantity'] ?? [];

        if ($patient_id <= 0 || $doctor_id <= 0 || empty($diagnosis)) {
            $error = 'Please fill in all required fields (Patient, Doctor, and Diagnosis).';
        } elseif (empty($medicine_ids) || count($medicine_ids) === 0 || empty($medicine_ids[0])) {
            $error = 'Please add at least one medicine to the prescription.';
        } else {
            $conn->begin_transaction();
            try {
                // Generate unique prescription ID
                $count_res = query("SELECT COUNT(*) as count FROM prescriptions");
                $next_num = $count_res->fetch_assoc()['count'] + 1;
                $prescription_id = 'PRSC' . str_pad($next_num, 6, '0', STR_PAD_LEFT);

                // Create prescription
                $stmt = prepare("INSERT INTO prescriptions (prescription_id, appointment_id, patient_id, doctor_id, diagnosis, notes, status, created_at) 
                                 VALUES (?, 0, ?, ?, ?, ?, 'active', NOW())");
                $stmt->bind_param("siiiss", $prescription_id, $patient_id, $doctor_id, $diagnosis, $notes);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to insert prescription.');
                }
                
                $prescription_db_id = insertId();

                // Insert medicines
                for ($i = 0; $i < count($medicine_ids); $i++) {
                    if (!empty($medicine_ids[$i])) {
                        $m_id = intval($medicine_ids[$i]);
                        $m_dosage = sanitizeInput($dosages[$i] ?? '');
                        $m_freq = sanitizeInput($frequencies[$i] ?? '');
                        $m_dur = sanitizeInput($durations[$i] ?? '');
                        $m_inst = sanitizeInput($instructions[$i] ?? '');
                        $m_qty = intval($quantities[$i] ?? 1);
                        
                        $pm_stmt = prepare("INSERT INTO prescription_medicines (prescription_id, medicine_id, dosage, frequency, duration, instructions, quantity, created_at) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                        $pm_stmt->bind_param("iissssi", $prescription_db_id, $m_id, $m_dosage, $m_freq, $m_dur, $m_inst, $m_qty);
                        
                        if (!$pm_stmt->execute()) {
                            throw new Exception('Failed to add prescription medicine.');
                        }
                    }
                }

                $conn->commit();
                logActivity('prescription_created_manual', 'Manually created prescription: ' . $prescription_id);
                setNotification('Prescription created successfully.', 'success');
                redirect('prescription_view.php?id=' . $prescription_db_id);
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

$page_title = "New Prescription - Smart Hospital Management System";
$page_heading = "Create Prescription";
include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-9 mx-auto">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-file-prescription me-2"></i>Create New Prescription</h5>
                <a href="prescriptions.php" class="btn btn-sm btn-outline-light">Cancel</a>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="needs-validation" novalidate>
                    <?php echo getCSRFInput(); ?>

                    <!-- Patient and Doctor Info -->
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="patient_id" class="form-label fw-bold">Patient *</label>
                            <select class="form-select" id="patient_id" name="patient_id" required>
                                <option value="">-- Select Patient --</option>
                                <?php foreach ($patients as $pat): ?>
                                    <option value="<?php echo $pat['id']; ?>">
                                        <?php echo htmlspecialchars($pat['first_name'] . ' ' . $pat['last_name'] . ' (' . $pat['patient_id'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="doctor_id" class="form-label fw-bold">Doctor *</label>
                            <select class="form-select" id="doctor_id" name="doctor_id" required>
                                <option value="">-- Select Prescribing Doctor --</option>
                                <?php foreach ($doctors as $doc): ?>
                                    <option value="<?php echo $doc['id']; ?>">
                                        Dr. <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name'] . ' (' . $doc['specialization'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Clinical Findings -->
                    <div class="mb-4">
                        <label for="diagnosis" class="form-label fw-bold">Diagnosis *</label>
                        <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3" placeholder="Enter clinical diagnosis..." required></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="notes" class="form-label fw-bold">Additional Notes / Advice</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Dietary restrictions, rest instructions, etc..."></textarea>
                    </div>

                    <!-- Prescription Items -->
                    <h5 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-pills me-2"></i>Prescribe Medications</h5>
                    <div id="prescribedMedicines" class="mb-3">
                        <!-- Medicine rows populated dynamically -->
                    </div>

                    <button type="button" class="btn btn-outline-primary btn-sm mb-4" onclick="addMedicineRow()">
                        <i class="fas fa-plus me-1"></i>Add Medicine
                    </button>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="prescriptions.php" class="btn btn-outline-secondary me-md-2">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Prescription</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
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
                <input type="text" class="form-control" name="dosage[]" placeholder="e.g. 500mg" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Frequency</label>
                <select class="form-select" name="frequency[]" required>
                    <option value="Daily">Daily</option>
                    <option value="Twice daily">Twice daily (BD)</option>
                    <option value="Three times daily">Three times daily (TDS)</option>
                    <option value="Four times daily">Four times daily (QDS)</option>
                    <option value="As needed">As needed (PRN)</option>
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
            <div class="col-md-12 mt-2">
                <input type="text" class="form-control form-control-sm" name="instructions[]" placeholder="Administration advice (e.g., Take after food)...">
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', rowHtml);
}

// Add the first row on page load
document.addEventListener('DOMContentLoaded', addMedicineRow);

// Bootstrap validation
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
