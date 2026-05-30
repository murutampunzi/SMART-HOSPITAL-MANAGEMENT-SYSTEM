<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'pharmacist']);

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect('prescriptions.php?error=Invalid prescription ID');
}

// Fetch prescription details
$stmt = prepare("SELECT * FROM prescriptions WHERE id = ? AND status = 'active'");
$stmt->bind_param("i", $id);
$stmt->execute();
$prescription = $stmt->get_result()->fetch_assoc();

if (!$prescription) {
    redirect('prescriptions.php?error=Prescription not active or not found');
}

// Fetch prescribed medicines
$med_stmt = prepare("SELECT pm.*, m.name as medicine_name, m.stock_quantity
                     FROM prescription_medicines pm
                     JOIN medicines m ON pm.medicine_id = m.id
                     WHERE pm.prescription_id = ?");
$med_stmt->bind_param("i", $id);
$med_stmt->execute();
$prescription_medicines = $med_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$error = '';
$success = '';

// Handle Dispense action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request token.';
    } else {
        // Validate stock levels first
        $insufficient_stock = [];
        foreach ($prescription_medicines as $med) {
            if ($med['stock_quantity'] < $med['quantity']) {
                $insufficient_stock[] = $med['medicine_name'] . " (Required: " . $med['quantity'] . ", Available: " . $med['stock_quantity'] . ")";
            }
        }

        if (!empty($insufficient_stock)) {
            $error = 'Insufficient stock for: ' . implode(', ', $insufficient_stock);
        } else {
            $conn->begin_transaction();
            try {
                // Update prescription status to completed
                $upd = prepare("UPDATE prescriptions SET status = 'completed', updated_at = NOW() WHERE id = ?");
                $upd->bind_param("i", $id);
                $upd->execute();

                // Update stock for each medicine
                foreach ($prescription_medicines as $med) {
                    $med_id = $med['medicine_id'];
                    $qty = -$med['quantity']; // negative to subtract
                    
                    // Call the stored procedure or execute manual update
                    $proc_stmt = prepare("CALL UpdateMedicineStock(?, ?)");
                    $proc_stmt->bind_param("ii", $med_id, $qty);
                    $proc_stmt->execute();
                }

                $conn->commit();
                logActivity('prescription_dispensed', 'Dispensed prescription ID: ' . $prescription['prescription_id']);
                setNotification('Prescription dispensed successfully and stock updated.', 'success');
                redirect('prescription_view.php?id=' . $id);
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Failed to dispense prescription: ' . $e->getMessage();
            }
        }
    }
}

$page_title = "Dispense Prescription - Smart Hospital Management System";
$page_heading = "Dispense Prescription";
include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-pills me-2"></i>Dispense Prescription</h5>
                <a href="prescription_view.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-light">Back</a>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="alert alert-info">
                    <strong>Prescription ID:</strong> <?php echo htmlspecialchars($prescription['prescription_id']); ?><br>
                    <strong>Diagnosis:</strong> <?php echo htmlspecialchars($prescription['diagnosis']); ?>
                </div>

                <h5 class="fw-bold mb-3">Items to Dispense:</h5>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Medicine</th>
                                <th>Required Qty</th>
                                <th>Current Stock</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($prescription_medicines as $med): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($med['medicine_name']); ?></td>
                                    <td><?php echo htmlspecialchars($med['quantity']); ?></td>
                                    <td><?php echo htmlspecialchars($med['stock_quantity']); ?></td>
                                    <td>
                                        <?php if ($med['stock_quantity'] >= $med['quantity']): ?>
                                            <span class="badge bg-success">In Stock</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Insufficient Stock</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <form method="POST">
                    <?php echo getCSRFInput(); ?>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-check-double me-2"></i>Confirm and Dispense
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
