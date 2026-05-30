<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

$invoice_id = intval($_GET['id'] ?? 0);
if ($invoice_id <= 0) {
    redirect('billing/index.php?error=Invalid invoice ID');
}

// Fetch invoice details
$stmt = prepare("SELECT i.*, p.first_name, p.last_name, p.patient_id, p.phone as patient_phone, p.address as patient_address 
                FROM invoices i 
                JOIN patients p ON i.patient_id = p.id 
                WHERE i.id = ?");
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();

if (!$invoice) {
    redirect('billing/index.php?error=Invoice not found');
}

$page_title = "Invoice Details " . $invoice['invoice_id'] . " - Smart Hospital Management System";
$page_heading = "Invoice Details";

// Fetch invoice items
$items_stmt = prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$items_stmt->bind_param("i", $invoice_id);
$items_stmt->execute();
$items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch payments recorded for this invoice
$payments_stmt = prepare("SELECT * FROM payments WHERE invoice_id = ? AND status = 'completed' ORDER BY payment_date DESC");
$payments_stmt->bind_param("i", $invoice_id);
$payments_stmt->execute();
$payments = $payments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="row">
    <!-- Invoice Panel -->
    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm border-0" id="invoicePrintArea">
            <!-- Header Block -->
            <div class="invoice-header">
                <div class="row align-items-center">
                    <div class="col-sm-6 text-start">
                        <h4 class="mb-0 fw-bold"><i class="fas fa-hospital-alt me-2"></i>SMART HOSPITAL</h4>
                        <small class="text-white-50">123 Hospital Street, Medical City</small>
                    </div>
                    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
                        <h3 class="mb-0 text-white-50 uppercase fw-bold">INVOICE</h3>
                        <span class="badge bg-white text-primary fw-bold px-3 py-2">ID: <?php echo htmlspecialchars($invoice['invoice_id']); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="invoice-body">
                <!-- Info Section -->
                <div class="row mb-4">
                    <div class="col-sm-6 text-start">
                        <h6 class="text-primary fw-bold mb-2">Billed To:</h6>
                        <h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?></h5>
                        <div class="text-muted small">Patient ID: <?php echo htmlspecialchars($invoice['patient_id']); ?></div>
                        <div class="text-muted small">Phone: <?php echo htmlspecialchars($invoice['patient_phone']); ?></div>
                        <div class="text-muted small"><?php echo htmlspecialchars($invoice['patient_address'] ?: 'No address specified'); ?></div>
                    </div>
                    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
                        <h6 class="text-primary fw-bold mb-2">Invoice Info:</h6>
                        <div class="text-muted small"><strong>Invoice Date:</strong> <?php echo formatDate($invoice['invoice_date']); ?></div>
                        <div class="text-muted small"><strong>Due Date:</strong> <?php echo $invoice['due_date'] ? formatDate($invoice['due_date']) : 'Upon Receipt'; ?></div>
                        <div class="text-muted small"><strong>Status:</strong> <?php echo getStatusBadge($invoice['status']); ?></div>
                    </div>
                </div>
                
                <!-- Items Table -->
                <div class="table-responsive mb-4">
                    <table class="table table-hover align-middle">
                        <thead class="table-light text-uppercase">
                            <tr>
                                <th>#</th>
                                <th>Service / Item</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $count = 1;
                            foreach ($items as $item): 
                            ?>
                                <tr>
                                    <td><?php echo $count++; ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($item['description']); ?></div>
                                        <small class="text-muted text-uppercase"><?php echo htmlspecialchars($item['item_type']); ?></small>
                                    </td>
                                    <td class="text-center"><?php echo $item['quantity']; ?></td>
                                    <td class="text-end"><?php echo formatCurrency($item['unit_price']); ?></td>
                                    <td class="text-end fw-semibold"><?php echo formatCurrency($item['total_price']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Summary Section -->
                <div class="row">
                    <div class="col-md-6 mb-3 text-start">
                        <?php if ($invoice['notes']): ?>
                            <h6 class="text-primary fw-bold mb-2">Terms & Notes:</h6>
                            <p class="text-muted small bg-light p-3 rounded"><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 text-sm-end">
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <span class="text-muted">Subtotal:</span>
                            <span class="fw-semibold"><?php echo formatCurrency($invoice['subtotal']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <span class="text-muted">Tax (<?php echo floatval($invoice['tax_amount']) > 0 ? 'Calculated' : '0%'; ?>):</span>
                            <span class="fw-semibold"><?php echo formatCurrency($invoice['tax_amount']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <span class="text-muted">Discount:</span>
                            <span class="text-danger fw-semibold">- <?php echo formatCurrency($invoice['discount_amount']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom mb-3 fs-5">
                            <span class="fw-bold text-dark">Grand Total:</span>
                            <span class="fw-bold text-primary"><?php echo formatCurrency($invoice['total_amount']); ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between py-1 small">
                            <span class="text-success">Paid Amount:</span>
                            <span class="fw-semibold text-success"><?php echo formatCurrency($invoice['paid_amount']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-1 fs-6">
                            <span class="fw-bold text-dark">Balance Due:</span>
                            <span class="fw-bold text-danger"><?php echo formatCurrency($invoice['balance_amount']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="invoice-footer bg-light p-4 text-center">
                <small class="text-muted"><i class="fas fa-heart text-danger me-1"></i>Thank you for choosing Smart Hospital. Wishing you a swift recovery.</small>
            </div>
        </div>
    </div>
    
    <!-- Sidebar Panel: Actions and Payments -->
    <div class="col-lg-4 mb-4">
        <!-- Actions Card -->
        <div class="card shadow-sm border-0 mb-4 no-print">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="card-title mb-0 fw-bold"><i class="fas fa-toolbox text-primary me-2"></i>Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Invoice Receipt
                    </button>
                    
                    <?php if (floatval($invoice['balance_amount']) > 0 && (hasRole('admin') || hasRole('receptionist'))): ?>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#paymentModal">
                            <i class="fas fa-cash-register me-2"></i>Record New Payment
                        </button>
                    <?php endif; ?>
                    
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Invoices
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Payments History Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="card-title mb-0 fw-bold"><i class="fas fa-receipt text-primary me-2"></i>Completed Payments</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($payments)): ?>
                    <p class="text-muted text-center py-4 mb-0 small">No payments captured yet for this invoice.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($payments as $pay): ?>
                            <li class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle uppercase"><?php echo htmlspecialchars($pay['payment_method']); ?></span>
                                    <span class="fw-bold text-success"><?php echo formatCurrency($pay['amount']); ?></span>
                                </div>
                                <div class="text-muted small"><strong>ID:</strong> <?php echo htmlspecialchars($pay['payment_id']); ?></div>
                                <div class="text-muted small"><strong>Date:</strong> <?php echo formatDateTime($pay['payment_date']); ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Record Payment Modal -->
<?php if (floatval($invoice['balance_amount']) > 0 && (hasRole('admin') || hasRole('receptionist'))): ?>
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-cash-register me-2 text-primary"></i>Record Transaction Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="record_payment.php" method="POST" class="needs-validation" novalidate>
                    <?php echo getCSRFInput(); ?>
                    <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Outstanding Balance</label>
                            <input type="text" class="form-control bg-light" value="<?php echo formatCurrency($invoice['balance_amount']); ?>" readonly disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label for="amount" class="form-label">Payment Amount (<?php echo $settings['currency'] ?? '$'; ?>) *</label>
                            <input type="number" class="form-control" id="amount" name="amount" 
                                   value="<?php echo htmlspecialchars($invoice['balance_amount']); ?>" 
                                   min="0.01" max="<?php echo htmlspecialchars($invoice['balance_amount']); ?>" step="0.01" required>
                            <div class="invalid-feedback">Please enter a valid payment amount (max: <?php echo htmlspecialchars($invoice['balance_amount']); ?>).</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="payment_method" class="form-label">Payment Method *</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="cash">Cash</option>
                                <option value="card">Credit/Debit Card</option>
                                <option value="insurance">Insurance Claim</option>
                                <option value="online">Online Payment</option>
                                <option value="bank_transfer">Bank Transfer</option>
                            </select>
                            <div class="invalid-feedback">Please select a payment method.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="transaction_id" class="form-label">Reference / Transaction ID</label>
                            <input type="text" class="form-control" id="transaction_id" name="transaction_id" placeholder="e.g. TXN9481940">
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Payment Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Any additional notes..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Capture Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
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
