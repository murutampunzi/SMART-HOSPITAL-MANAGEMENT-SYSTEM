<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect('prescriptions.php?error=Invalid prescription ID');
}

// Fetch prescription details
$stmt = prepare("SELECT pr.*, 
                p.first_name as patient_first_name, p.last_name as patient_last_name, p.patient_id, p.date_of_birth, p.gender, p.blood_group,
                d.first_name as doctor_first_name, d.last_name as doctor_last_name, d.specialization, d.doctor_id
                FROM prescriptions pr
                JOIN patients p ON pr.patient_id = p.id
                JOIN doctors d ON pr.doctor_id = d.id
                WHERE pr.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$prescription = $stmt->get_result()->fetch_assoc();

if (!$prescription) {
    redirect('prescriptions.php?error=Prescription not found');
}

$page_title = "Prescription " . $prescription['prescription_id'] . " - Smart Hospital Management System";
$page_heading = "Prescription Details";

// Fetch prescribed medicines
$med_stmt = prepare("SELECT pm.*, m.name as medicine_name, m.generic_name, m.unit, m.selling_price
                     FROM prescription_medicines pm
                     JOIN medicines m ON pm.medicine_id = m.id
                     WHERE pm.prescription_id = ?");
$med_stmt->bind_param("i", $id);
$med_stmt->execute();
$prescription_medicines = $med_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="row">
    <!-- Left Column: Prescription & Medicines -->
    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm border-0 mb-4 animate fade-in">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold"><i class="fas fa-prescription text-primary me-2"></i>Prescription Information</h5>
                <span class="badge bg-secondary py-2 px-3">ID: <?php echo htmlspecialchars($prescription['prescription_id']); ?></span>
            </div>
            <div class="card-body p-4">
                <div class="row mb-4">
                    <div class="col-md-6 mb-3 text-start">
                        <label class="form-label text-primary fw-bold mb-1">Diagnosis</label>
                        <p class="text-dark fw-bold bg-light p-3 rounded border"><?php echo nl2br(htmlspecialchars($prescription['diagnosis'] ?: 'No diagnosis recorded.')); ?></p>
                    </div>
                    <div class="col-md-6 mb-3 text-md-end">
                        <label class="form-label text-primary fw-bold mb-1">Date & Status</label>
                        <div><strong>Prescribed Date:</strong> <?php echo formatDate($prescription['created_at']); ?></div>
                        <div class="mt-2"><?php echo getStatusBadge($prescription['status']); ?></div>
                    </div>
                </div>

                <hr class="my-4">

                <h5 class="fw-bold mb-3 text-primary"><i class="fas fa-pills me-2"></i>Prescribed Medicines</h5>
                <div class="table-responsive shadow-sm rounded">
                    <table class="table table-bordered table-hover align-middle mb-0 bg-white text-center">
                        <thead class="table-dark">
                            <tr>
                                <th class="text-start ps-3">Medicine Name</th>
                                <th>Dosage</th>
                                <th>Frequency</th>
                                <th>Duration</th>
                                <th>Quantity</th>
                                <th>Special Instructions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($prescription_medicines as $med): ?>
                                <tr>
                                    <td class="fw-bold text-dark text-start ps-3">
                                        <?php echo htmlspecialchars($med['medicine_name']); ?>
                                        <?php if ($med['generic_name']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($med['generic_name']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($med['dosage']); ?></td>
                                    <td><span class="badge bg-info-subtle text-info border border-info-subtle"><?php echo htmlspecialchars($med['frequency']); ?></span></td>
                                    <td><?php echo htmlspecialchars($med['duration']); ?></td>
                                    <td><?php echo htmlspecialchars($med['quantity']); ?> <small class="text-muted"><?php echo htmlspecialchars($med['unit']); ?></small></td>
                                    <td class="text-muted text-start"><?php echo htmlspecialchars($med['instructions'] ?: 'Take as directed'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($prescription['notes']): ?>
                    <div class="mt-4 text-start">
                        <h6 class="text-primary fw-bold mb-2">Doctor Notes:</h6>
                        <p class="text-muted bg-light p-3 rounded border"><?php echo nl2br(htmlspecialchars($prescription['notes'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Patient Info & Actions -->
    <div class="col-lg-4 mb-4">
        <!-- Patient Info Card -->
        <div class="card shadow-sm border-0 mb-4 animate fade-in">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="card-title mb-0 fw-bold"><i class="fas fa-user-injured text-primary me-2"></i>Patient Information</h6>
            </div>
            <div class="card-body">
                <h5 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($prescription['patient_first_name'] . ' ' . $prescription['patient_last_name']); ?></h5>
                <small class="text-muted d-block mb-3">Patient ID: <?php echo htmlspecialchars($prescription['patient_id']); ?></small>
                
                <hr class="my-3">
                
                <div class="small text-muted mb-2"><i class="fas fa-venus-mars me-2"></i><strong>Gender:</strong> <?php echo ucfirst($prescription['gender']); ?></div>
                <div class="small text-muted mb-2"><i class="fas fa-tint me-2"></i><strong>Blood Group:</strong> <?php echo $prescription['blood_group'] ?: 'N/A'; ?></div>
                <div class="small text-muted mb-2"><i class="fas fa-calendar-day me-2"></i><strong>DOB:</strong> <?php echo formatDate($prescription['date_of_birth']); ?></div>
                <div class="small text-muted mb-0"><i class="fas fa-user-md me-2"></i><strong>Prescribed By:</strong> Dr. <?php echo htmlspecialchars($prescription['doctor_first_name'] . ' ' . $prescription['doctor_last_name']); ?> (<?php echo htmlspecialchars($prescription['specialization']); ?>)</div>
            </div>
        </div>

        <!-- Administrative Actions -->
        <div class="card shadow-sm border-0 mb-4 no-print">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="card-title mb-0 fw-bold"><i class="fas fa-sliders-h text-primary me-2"></i>Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if ($prescription['status'] === 'active' && (hasRole('admin') || hasRole('pharmacist'))): ?>
                        <a href="prescription_dispense.php?id=<?php echo $id; ?>" class="btn btn-success">
                            <i class="fas fa-check me-2"></i>Dispense Prescription
                        </a>
                    <?php endif; ?>

                    <a href="prescription_print.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary" target="_blank">
                        <i class="fas fa-print me-2"></i>Print Prescription
                    </a>

                    <a href="prescriptions.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to List
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
