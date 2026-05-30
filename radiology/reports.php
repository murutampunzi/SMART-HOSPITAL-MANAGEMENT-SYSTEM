<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and radiologist role
requireLogin();
requireAnyRole(['radiologist']);
// Enable detailed error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

$page_title = "Radiology Reports - SMART Hospital Management System";
$page_heading = "Radiology Reports";

// Fetch completed reports (you can adjust status filter later)
$reports = getRadiologyReports('completed');

include '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="description" content="List of completed radiology reports for radiologists.">
    <link rel="stylesheet" href="../assets/css/style.css"> <!-- Assuming a central stylesheet -->
    <style>
        /* Premium table styling */
        .reports-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(5px);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .reports-table th, .reports-table td {
            padding: 12px 16px;
            text-align: left;
        }
        .reports-table thead {
            background: linear-gradient(135deg, #4e54c8, #8f94fb);
            color: #fff;
        }
        .reports-table tbody tr {
            transition: background-color 0.2s ease, transform 0.1s ease;
        }
        .reports-table tbody tr:hover {
            background-color: rgba(0,123,255,0.08);
            transform: translateX(4px);
        }
        .view-btn {
            display: inline-block;
            padding: 6px 12px;
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: #fff;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.3s, transform 0.1s;
        }
        .view-btn:hover {
            background: linear-gradient(135deg, #38ef7d, #11998e);
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mt-4 mb-3"><?php echo htmlspecialchars($page_heading); ?></h1>
        <?php if (empty($reports)): ?>
            <p class="alert alert-info">No completed reports found.</p>
        <?php else: ?>
            <table class="reports-table">
                <thead>
                    <tr>
                        <th>Report ID</th>
                        <th>Patient</th>
                        <th>Test</th>
                        <th>Modality</th>
                        <th>Requested</th>
                        <th>Report Date</th>
                        <th>Radiologist</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $r): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['request_id']); ?></td>
                            <td><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($r['test_name']); ?></td>
                            <td><?php echo htmlspecialchars($r['modality']); ?></td>
                            <td><?php echo htmlspecialchars(formatDateTime($r['requested_date'])); ?></td>
                            <td><?php echo htmlspecialchars($r['report_date'] ? formatDateTime($r['report_date']) : '—'); ?></td>
                            <td><?php echo htmlspecialchars($r['radiologist_name'] ?? '—'); ?></td>
                            <td><a class="view-btn" href="view.php?id=<?php echo urlencode($r['request_id']); ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
include '../includes/footer.php';
?>
