<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'receptionist']);

$page_title = "Create Invoice - Smart Hospital Management System";
$page_heading = "Create New Invoice";

$error = '';
$success = '';

// Get pre-filled patient ID if provided
$prefilled_patient_id = intval($_GET['patient_id'] ?? 0);
$prefilled_appointment_id = intval($_GET['appointment_id'] ?? 0);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        // Get form data
        $patient_id = intval($_POST['patient_id'] ?? 0);
        $invoice_date = $_POST['invoice_date'] ?? date('Y-m-d');
        $due_date = $_POST['due_date'] ?? '';
        $notes = sanitizeInput($_POST['notes'] ?? '');
        $discount = floatval($_POST['discount'] ?? 0);
        $tax_rate = floatval($_POST['tax_rate'] ?? 0);
        
        // Get invoice items
        $items = [];
        $item_descriptions = $_POST['item_description'] ?? [];
        $item_quantities = $_POST['item_quantity'] ?? [];
        $item_prices = $_POST['item_price'] ?? [];
        
        for ($i = 0; $i < count($item_descriptions); $i++) {
            if (!empty($item_descriptions[$i]) && !empty($item_quantities[$i]) && !empty($item_prices[$i])) {
                $items[] = [
                    'description' => sanitizeInput($item_descriptions[$i]),
                    'quantity' => floatval($item_quantities[$i]),
                    'price' => floatval($item_prices[$i])
                ];
            }
        }
        
        // Validate required fields
        $required_fields = [
            'patient_id' => $patient_id,
            'invoice_date' => $invoice_date
        ];
        
        $validation_errors = validateRequired($required_fields);
        if (!empty($validation_errors)) {
            $error = reset($validation_errors);
        } elseif (!validateDate($invoice_date)) {
            $error = 'Invalid invoice date';
        } elseif (!empty($due_date) && !validateDate($due_date)) {
            $error = 'Invalid due date';
        } elseif (empty($items)) {
            $error = 'At least one item is required';
        } else {
            try {
                // Begin transaction
                $conn->begin_transaction();
                
                // Calculate totals
                $subtotal = 0;
                foreach ($items as $item) {
                    $subtotal += $item['quantity'] * $item['price'];
                }
                
                $discount_amount = $subtotal * ($discount / 100);
                $tax_amount = ($subtotal - $discount_amount) * ($tax_rate / 100);
                $total_amount = $subtotal - $discount_amount + $tax_amount;
                
                // Generate invoice ID
                $invoice_id = 'INV' . str_pad(insertId() + 1, 6, '0', STR_PAD_LEFT);
                
                // Create invoice
                $sql = "INSERT INTO invoices (invoice_id, patient_id, invoice_date, due_date, subtotal, discount, discount_amount, 
                        tax_rate, tax_amount, total_amount, notes, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'unpaid')";
                
                $stmt = prepare($sql);
                $stmt->bind_param("sisdddddds", 
                    $invoice_id, $patient_id, $invoice_date, $due_date, 
                    $subtotal, $discount, $discount_amount, $tax_rate, $tax_amount, $total_amount, $notes
                );
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to create invoice');
                }
                
                $invoice_record_id = insertId();
                
                // Add invoice items
                foreach ($items as $item) {
                    $item_total = $item['quantity'] * $item['price'];
                    $item_sql = "INSERT INTO invoice_items (invoice_id, description, quantity, price, total) 
                                VALUES (?, ?, ?, ?, ?)";
                    $item_stmt = prepare($item_sql);
                    $item_stmt->bind_param("isddd", 
                        $invoice_record_id, $item['description'], $item['quantity'], $item['price'], $item_total
                    );
                    
                    if (!$item_stmt->execute()) {
                        throw new Exception('Failed to add invoice item');
                    }
                }
                
                // Commit transaction
                $conn->commit();
                
                // Log activity
                logActivity('invoice_created', "New invoice created: $invoice_id");
                
                // Set success message
                $success = "Invoice created successfully! Invoice ID: $invoice_id. Total: " . formatCurrency($total_amount);
                
                // Redirect to invoice view
                header('Refresh: 2; url=view.php?id=' . $invoice_record_id);
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

// Get patients for dropdown
$patients = query("SELECT id, patient_id, first_name, last_name FROM patients WHERE status = 'active' ORDER BY first_name, last_name")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Invoice Details</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="needs-validation" novalidate>
                    <?php echo getCSRFInput(); ?>
                    
                    <!-- Patient Selection -->
                    <h6 class="text-primary mb-3">Patient Information</h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="patient_id" class="form-label">Patient *</label>
                            <select class="form-select" id="patient_id" name="patient_id" required>
                                <option value="">Select Patient</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>" 
                                            <?php echo $prefilled_patient_id === $patient['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' - ' . $patient['patient_id']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Patient is required</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="invoice_date" class="form-label">Invoice Date *</label>
                            <input type="date" class="form-control" id="invoice_date" name="invoice_date" 
                                   value="<?php echo htmlspecialchars($_POST['invoice_date'] ?? date('Y-m-d')); ?>" required>
                            <div class="invalid-feedback">Invoice date is required</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="due_date" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="due_date" name="due_date" 
                                   value="<?php echo htmlspecialchars($_POST['due_date'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <!-- Invoice Items -->
                    <h6 class="text-primary mb-3">Invoice Items</h6>
                    <div class="row mb-4">
                        <div class="col-12">
                            <div id="invoiceItems">
                                <div class="row mb-2 invoice-item">
                                    <div class="col-md-6 mb-2">
                                        <input type="text" class="form-control" name="item_description[]" 
                                               placeholder="Item description" required>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <input type="number" class="form-control" name="item_quantity[]" 
                                               placeholder="Qty" min="1" value="1" required>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <input type="number" class="form-control item-price" name="item_price[]" 
                                               placeholder="Price" min="0" step="0.01" required>
                                    </div>
                                    <div class="col-md-1 mb-2">
                                        <button type="button" class="btn btn-danger btn-sm" onclick="removeItem(this)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addItem()">
                                <i class="fas fa-plus me-2"></i>Add Item
                            </button>
                        </div>
                    </div>
                    
                    <!-- Totals -->
                    <h6 class="text-primary mb-3">Totals</h6>
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <label for="discount" class="form-label">Discount (%)</label>
                            <input type="number" class="form-control" id="discount" name="discount" 
                                   value="<?php echo htmlspecialchars($_POST['discount'] ?? 0); ?>" min="0" max="100" step="0.1">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="tax_rate" class="form-label">Tax Rate (%)</label>
                            <input type="number" class="form-control" id="tax_rate" name="tax_rate" 
                                   value="<?php echo htmlspecialchars($_POST['tax_rate'] ?? 0); ?>" min="0" max="100" step="0.1">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Total Amount</label>
                            <div class="form-control bg-light" id="totalAmount">$0.00</div>
                        </div>
                    </div>
                    
                    <!-- Notes -->
                    <h6 class="text-primary mb-3">Additional Notes</h6>
                    <div class="row mb-4">
                        <div class="col-12 mb-3">
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Any additional notes..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Submit Buttons -->
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Create Invoice
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Side Panel -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">Quick Tips</h6>
            </div>
            <div class="card-body">
                <ul class="list-unstyled small">
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>All fields marked with * are required</li>
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Invoice ID will be generated automatically</li>
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Add multiple items as needed</li>
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Discount and tax are calculated automatically</li>
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Due date is optional</li>
                </ul>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="card-title mb-0">Invoice Summary</h6>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal:</span>
                    <span id="subtotal">$0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Discount:</span>
                    <span id="discountAmount">$0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Tax:</span>
                    <span id="taxAmount">$0.00</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between fw-bold">
                    <span>Total:</span>
                    <span id="finalTotal">$0.00</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function addItem() {
    const container = document.getElementById('invoiceItems');
    const newItem = document.createElement('div');
    newItem.className = 'row mb-2 invoice-item';
    newItem.innerHTML = `
        <div class="col-md-6 mb-2">
            <input type="text" class="form-control" name="item_description[]" placeholder="Item description" required>
        </div>
        <div class="col-md-2 mb-2">
            <input type="number" class="form-control" name="item_quantity[]" placeholder="Qty" min="1" value="1" required>
        </div>
        <div class="col-md-3 mb-2">
            <input type="number" class="form-control item-price" name="item_price[]" placeholder="Price" min="0" step="0.01" required>
        </div>
        <div class="col-md-1 mb-2">
            <button type="button" class="btn btn-danger btn-sm" onclick="removeItem(this)">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    container.appendChild(newItem);
    calculateTotals();
}

function removeItem(button) {
    const items = document.querySelectorAll('.invoice-item');
    if (items.length > 1) {
        button.closest('.invoice-item').remove();
        calculateTotals();
    }
}

function calculateTotals() {
    let subtotal = 0;
    const items = document.querySelectorAll('.invoice-item');
    
    items.forEach(item => {
        const quantity = parseFloat(item.querySelector('[name="item_quantity[]"]').value) || 0;
        const price = parseFloat(item.querySelector('.item-price').value) || 0;
        subtotal += quantity * price;
    });
    
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;
    
    const discountAmount = subtotal * (discount / 100);
    const taxAmount = (subtotal - discountAmount) * (taxRate / 100);
    const total = subtotal - discountAmount + taxAmount;
    
    document.getElementById('subtotal').textContent = formatCurrency(subtotal);
    document.getElementById('discountAmount').textContent = formatCurrency(discountAmount);
    document.getElementById('taxAmount').textContent = formatCurrency(taxAmount);
    document.getElementById('totalAmount').textContent = formatCurrency(total);
    document.getElementById('finalTotal').textContent = formatCurrency(total);
}

function formatCurrency(amount) {
    return '$' + amount.toFixed(2);
}

// Add event listeners for real-time calculation
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('invoiceItems').addEventListener('input', calculateTotals);
    document.getElementById('discount').addEventListener('input', calculateTotals);
    document.getElementById('tax_rate').addEventListener('input', calculateTotals);
    calculateTotals();
});

// Form validation
(function() {
    'use strict';
    
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>

<?php include '../includes/footer.php'; ?>
