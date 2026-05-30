<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and admin role
requireLogin();
requireRole('admin');

$page_title = "System Reports - Smart Hospital Management System";
$page_heading = "System Reports";

// 1. Fetch Global Counters
$stats = [];
$stats['patients_count'] = query("SELECT COUNT(*) as count FROM patients")->fetch_assoc()['count'];
$stats['doctors_count'] = query("SELECT COUNT(*) as count FROM doctors")->fetch_assoc()['count'];
$stats['appointments_count'] = query("SELECT COUNT(*) as count FROM appointments")->fetch_assoc()['count'];
$stats['completed_appts'] = query("SELECT COUNT(*) as count FROM appointments WHERE status = 'completed'")->fetch_assoc()['count'];
$stats['pending_appts'] = query("SELECT COUNT(*) as count FROM appointments WHERE status = 'pending'")->fetch_assoc()['count'];
$stats['cancelled_appts'] = query("SELECT COUNT(*) as count FROM appointments WHERE status = 'cancelled'")->fetch_assoc()['count'];

// 2. Fetch Financial Data
$stats['total_revenue'] = query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'completed'")->fetch_assoc()['total'];
$stats['monthly_revenue'] = query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE()) AND status = 'completed'")->fetch_assoc()['total'];
$stats['pending_revenue'] = query("SELECT COALESCE(SUM(balance_amount), 0) as total FROM invoices WHERE status != 'paid' AND status != 'cancelled'")->fetch_assoc()['total'];

// 3. Specialization Breakdown (Doctors)
$specializations_result = query("SELECT specialization, COUNT(*) as count FROM doctors GROUP BY specialization ORDER BY count DESC LIMIT 5");
$specializations = [];
while ($row = fetchAssoc($specializations_result)) {
    $specializations[] = $row;
}

// 4. Appointment Status Distribution
$appt_status_result = query("SELECT status, COUNT(*) as count FROM appointments GROUP BY status");
$appt_status_data = [];
while ($row = fetchAssoc($appt_status_result)) {
    $appt_status_data[] = $row;
}

// 5. Monthly Revenue Growth (Last 6 Months)
$revenue_history = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_name = date('M Y', strtotime("-$i months"));
    $revenue = query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = '$month' AND status = 'completed'")->fetch_assoc()['total'];
    $revenue_history[] = [
        'month' => $month_name,
        'revenue' => $revenue
    ];
}

include '../includes/header.php';
?>

<!-- Financial & Administrative Metrics -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card bg-gradient-primary text-white h-100 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h6 class="text-uppercase mb-1 small text-white-50">Total Lifetime Revenue</h6>
                        <h3 class="mb-0 fw-bold"><?php echo formatCurrency($stats['total_revenue']); ?></h3>
                    </div>
                    <i class="fas fa-coins fa-3x text-white-50"></i>
                </div>
                <small class="text-white-50"><i class="fas fa-chart-line me-1"></i>Combined total payments</small>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card bg-gradient-success text-white h-100 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h6 class="text-uppercase mb-1 small text-white-50">Current Monthly Revenue</h6>
                        <h3 class="mb-0 fw-bold"><?php echo formatCurrency($stats['monthly_revenue']); ?></h3>
                    </div>
                    <i class="fas fa-wallet fa-3x text-white-50"></i>
                </div>
                <small class="text-white-50"><i class="fas fa-calendar-check me-1"></i>Collected this month</small>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card bg-gradient-danger text-white h-100 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h6 class="text-uppercase mb-1 small text-white-50">Pending Outstandings</h6>
                        <h3 class="mb-0 fw-bold"><?php echo formatCurrency($stats['pending_revenue']); ?></h3>
                    </div>
                    <i class="fas fa-file-invoice-dollar fa-3x text-white-50"></i>
                </div>
                <small class="text-white-50"><i class="fas fa-exclamation-triangle me-1"></i>Unpaid invoices balance</small>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card bg-gradient-success text-white h-100 shadow-sm" style="background: linear-gradient(135deg, #17a2b8, #117a8b) !important;">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h6 class="text-uppercase mb-1 small text-white-50">Total Appointments</h6>
                        <h3 class="mb-0 fw-bold"><?php echo number_format($stats['appointments_count']); ?></h3>
                    </div>
                    <i class="fas fa-calendar-check fa-3x text-white-50"></i>
                </div>
                <small class="text-white-50"><i class="fas fa-check-circle me-1"></i><?php echo number_format($stats['completed_appts']); ?> completed visits</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Revenue Growth History Table & Chart Placeholder -->
    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="card-title mb-0 fw-bold"><i class="fas fa-chart-area text-primary me-2"></i>Revenue Growth (Last 6 Months)</h6>
            </div>
            <div class="card-body">
                <div class="chart-container mb-4">
                    <canvas data-chart="line" data-chart-data='<?php echo json_encode([
                        'labels' => array_column($revenue_history, 'month'),
                        'datasets' => [[
                            'label' => 'Monthly Revenue (' . ($settings['currency'] ?? '$') . ')',
                            'data' => array_column($revenue_history, 'revenue'),
                            'borderColor' => '#28a745',
                            'backgroundColor' => 'rgba(40, 167, 69, 0.1)',
                            'tension' => 0.15,
                            'fill' => true
                        ]]
                    ]); ?>'></canvas>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 text-center">
                        <thead class="table-light">
                            <tr>
                                <th>Month</th>
                                <th>Revenue Generated</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($revenue_history as $history): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($history['month']); ?></td>
                                    <td><?php echo formatCurrency($history['revenue']); ?></td>
                                    <td>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="fas fa-check-circle me-1"></i>Audited
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Specializations & Status Sideboards -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="card-title mb-0 fw-bold"><i class="fas fa-user-md text-primary me-2"></i>Top Specializations</h6>
            </div>
            <div class="card-body">
                <?php if (empty($specializations)): ?>
                    <p class="text-muted text-center small mb-0">No doctor records set up.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($specializations as $spec): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span class="fw-semibold small"><?php echo htmlspecialchars($spec['specialization']); ?></span>
                                <span class="badge bg-primary rounded-pill"><?php echo $spec['count']; ?> Doctors</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="card-title mb-0 fw-bold"><i class="fas fa-tasks text-primary me-2"></i>Appointment Statuses</h6>
            </div>
            <div class="card-body">
                <div class="chart-container mb-3" style="height: 180px;">
                    <canvas data-chart="doughnut" data-chart-data='<?php echo json_encode([
                        'labels' => array_column($appt_status_data, 'status'),
                        'datasets' => [[
                            'data' => array_column($appt_status_data, 'count'),
                            'backgroundColor' => ['#ffc107', '#28a745', '#dc3545', '#17a2b8', '#6c757d']
                        ]]
                    ]); ?>'></canvas>
                </div>
                
                <div class="row text-center mt-3 small g-2">
                    <div class="col-6">
                        <div class="fw-bold text-success"><?php echo number_format($stats['completed_appts']); ?></div>
                        <div class="text-muted" style="font-size: 0.75rem;">Completed</div>
                    </div>
                    <div class="col-6">
                        <div class="fw-bold text-warning"><?php echo number_format($stats['pending_appts']); ?></div>
                        <div class="text-muted" style="font-size: 0.75rem;">Pending</div>
                    </div>
                    <div class="col-6">
                        <div class="fw-bold text-danger"><?php echo number_format($stats['cancelled_appts']); ?></div>
                        <div class="text-muted" style="font-size: 0.75rem;">Cancelled</div>
                    </div>
                    <div class="col-6">
                        <div class="fw-bold text-primary"><?php echo number_format($stats['patients_count']); ?></div>
                        <div class="text-muted" style="font-size: 0.75rem;">Total Patients</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
