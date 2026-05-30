<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid request ID.");
}

// Fetch prescription details
$stmt = prepare("SELECT pr.*, 
                p.first_name as patient_first_name, p.last_name as patient_last_name, p.patient_id, p.date_of_birth, p.gender,
                d.first_name as doctor_first_name, d.last_name as doctor_last_name, d.specialization, d.license_number
                FROM prescriptions pr
                JOIN patients p ON pr.patient_id = p.id
                JOIN doctors d ON pr.doctor_id = d.id
                WHERE pr.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$prescription = $stmt->get_result()->fetch_assoc();

if (!$prescription) {
    die("Prescription not found.");
}

// Fetch prescribed medicines
$med_stmt = prepare("SELECT pm.*, m.name as medicine_name, m.generic_name, m.unit
                     FROM prescription_medicines pm
                     JOIN medicines m ON pm.medicine_id = m.id
                     WHERE pm.prescription_id = ?");
$med_stmt->bind_param("i", $id);
$med_stmt->execute();
$prescription_medicines = $med_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Prescription Print - <?php echo htmlspecialchars($prescription['prescription_id']); ?></title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; margin: 30px; line-height: 1.5; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #0056b3; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { font-size: 24px; font-weight: bold; color: #0056b3; }
        .hospital-info { text-align: right; font-size: 12px; color: #666; }
        .title { text-align: center; font-size: 20px; font-weight: bold; margin-bottom: 30px; text-transform: uppercase; letter-spacing: 1px; }
        .details-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .details-table td { padding: 8px; border: 1px solid #ddd; font-size: 14px; }
        .details-table td.label { font-weight: bold; background-color: #f9f9f9; width: 15%; }
        .rx-section { font-size: 32px; font-weight: bold; color: #0056b3; margin-bottom: 15px; }
        .medicines-table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        .medicines-table th, .medicines-table td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; font-size: 14px; }
        .medicines-table th { background-color: #0056b3; color: white; }
        .notes-section { background-color: #f9f9f9; border-left: 4px solid #0056b3; padding: 15px; margin-bottom: 50px; font-size: 14px; }
        .signature-section { display: flex; justify-content: space-between; margin-top: 50px; }
        .signature-box { text-align: center; width: 200px; }
        .signature-line { border-top: 1px solid #333; margin-top: 50px; padding-top: 5px; font-size: 12px; }
        @media print {
            .no-print { display: none; }
            body { margin: 15px; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print();" style="padding: 10px 20px; font-size: 14px; background-color: #0056b3; color: white; border: none; cursor: pointer; border-radius: 4px;">Print</button>
        <button onclick="window.close();" style="padding: 10px 20px; font-size: 14px; background-color: #666; color: white; border: none; cursor: pointer; border-radius: 4px; margin-left: 10px;">Close</button>
    </div>

    <div class="header">
        <div>
            <div class="logo">SMART HOSPITAL</div>
            <div style="font-size: 12px; color: #666;">Quality Care Close To Home</div>
        </div>
        <div class="hospital-info">
            <strong>Address:</strong> 123 Hospital Street, Medical City<br>
            <strong>Phone:</strong> +1 234 567 8900 | <strong>Email:</strong> info@shms.com
        </div>
    </div>

    <div class="title">Medical Prescription</div>

    <table class="details-table">
        <tr>
            <td class="label">Patient:</td>
            <td><?php echo htmlspecialchars($prescription['patient_first_name'] . ' ' . $prescription['patient_last_name']); ?></td>
            <td class="label">Patient ID:</td>
            <td><?php echo htmlspecialchars($prescription['patient_id']); ?></td>
        </tr>
        <tr>
            <td class="label">Gender:</td>
            <td><?php echo ucfirst($prescription['gender']); ?></td>
            <td class="label">DOB:</td>
            <td><?php echo formatDate($prescription['date_of_birth']); ?></td>
        </tr>
        <tr>
            <td class="label">Doctor:</td>
            <td>Dr. <?php echo htmlspecialchars($prescription['doctor_first_name'] . ' ' . $prescription['doctor_last_name']); ?></td>
            <td class="label">Specialty:</td>
            <td><?php echo htmlspecialchars($prescription['specialization']); ?></td>
        </tr>
        <tr>
            <td class="label">Prescription ID:</td>
            <td><?php echo htmlspecialchars($prescription['prescription_id']); ?></td>
            <td class="label">Date:</td>
            <td><?php echo formatDate($prescription['created_at']); ?></td>
        </tr>
    </table>

    <div class="rx-section">R<sub>x</sub></div>

    <table class="medicines-table">
        <thead>
            <tr>
                <th>Medicine & Generic Name</th>
                <th>Dosage</th>
                <th>Frequency</th>
                <th>Duration</th>
                <th>Quantity</th>
                <th>Instructions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($prescription_medicines as $med): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($med['medicine_name']); ?></strong>
                        <?php if ($med['generic_name']): ?>
                            <br><span style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($med['generic_name']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($med['dosage']); ?></td>
                    <td><?php echo htmlspecialchars($med['frequency']); ?></td>
                    <td><?php echo htmlspecialchars($med['duration']); ?></td>
                    <td><?php echo htmlspecialchars($med['quantity']); ?> <?php echo htmlspecialchars($med['unit']); ?></td>
                    <td><?php echo htmlspecialchars($med['instructions'] ?: 'Take as directed'); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($prescription['notes']): ?>
        <div class="notes-section">
            <strong>Diagnosis & Notes:</strong><br>
            <?php echo nl2br(htmlspecialchars($prescription['notes'])); ?>
        </div>
    <?php endif; ?>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">
                Patient's Signature
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                Dr. <?php echo htmlspecialchars($prescription['doctor_first_name'] . ' ' . $prescription['doctor_last_name']); ?><br>
                License No: <?php echo htmlspecialchars($prescription['license_number'] ?: 'N/A'); ?>
            </div>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>
