<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'lab_technician', 'radiologist']);

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    setNotification('Invalid scan request ID.', 'danger');
    redirect('index.php');
}

// Fetch request details
$res = query("SELECT rr.*, rt.name as test_name, p.first_name, p.last_name, p.patient_id 
              FROM radiology_requests rr
              JOIN patients p ON rr.patient_id = p.id
              JOIN radiology_tests rt ON rr.test_id = rt.id
              WHERE rr.id = $id LIMIT 1");

if (!$res || numRows($res) === 0) {
    setNotification('Radiology request not found.', 'danger');
    redirect('index.php');
}

$request = $res->fetch_assoc();

$error = '';
$success = '';

// Handle Image Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request token.';
    } else {
        $descriptions = $_POST['descriptions'] ?? [];
        
        // Re-structure $_FILES['images'] for easier looping if multiple files
        $uploaded_count = 0;
        $files = [];
        if (isset($_FILES['images'])) {
            $file_post = $_FILES['images'];
            $file_count = is_array($file_post['name']) ? count($file_post['name']) : 0;
            $file_keys = array_keys($file_post);

            for ($i=0; $i<$file_count; $i++) {
                foreach ($file_keys as $key) {
                    $files[$i][$key] = $file_post[$key][$i];
                }
            }
        }

        if (empty($files) || $files[0]['error'] === UPLOAD_ERR_NO_FILE) {
            $error = 'Please select at least one image to upload.';
        } else {
            $conn->begin_transaction();
            try {
                $dest_dir = '../uploads/radiology';
                
                // Allow standard images
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                foreach ($files as $index => $file) {
                    if ($file['error'] === UPLOAD_ERR_OK) {
                        $up_res = uploadFile($file, $dest_dir, $allowed);
                        if ($up_res['success']) {
                            // Extract relative path from root to store in database
                            // uploadPath is e.g. ../uploads/radiology/abc.png -> uploads/radiology/abc.png
                            $db_path = str_replace('../', '', $up_res['path']);
                            $desc = sanitizeInput($descriptions[$index] ?? '');

                            $sql = "INSERT INTO radiology_images (request_id, image_path, image_type, image_description, upload_date, uploaded_by, status) 
                                    VALUES (?, ?, 'original', ?, NOW(), ?, 'pending_review')";
                            $stmt = prepare($sql);
                            $uid = $_SESSION['user_id'];
                            $stmt->bind_param("issi", $id, $db_path, $desc, $uid);
                            $stmt->execute();
                            $uploaded_count++;
                        } else {
                            throw new Exception("Upload failed for file " . ($index + 1) . ": " . $up_res['message']);
                        }
                    }
                }

                if ($uploaded_count > 0) {
                    // Update request status to in_progress if it was scheduled or pending
                    if ($request['status'] === 'scheduled' || $request['status'] === 'pending') {
                        query("UPDATE radiology_requests SET status = 'in_progress' WHERE id = $id");
                    }

                    $conn->commit();
                    logActivity('upload_radiology_images', "Uploaded $uploaded_count images for radiology request ID $id");
                    setNotification("Successfully uploaded $uploaded_count scan image(s).", 'success');
                    redirect("view.php?id=" . $id);
                } else {
                    $conn->rollback();
                    $error = 'No files were uploaded successfully.';
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

$page_title = "Upload Scan Images - Smart Hospital Management System";
$page_heading = "Upload Scan Images";
include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-upload me-2"></i>Upload Radiology Scans</h5>
                <a href="view.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-light"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info border-0 shadow-xs mb-4">
                    <h6 class="alert-heading fw-bold mb-1"><i class="fas fa-info-circle me-1"></i>Request Details</h6>
                    <div class="row small mt-2">
                        <div class="col-sm-4"><strong>Patient:</strong> <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></div>
                        <div class="col-sm-4"><strong>Scan Type:</strong> <?php echo htmlspecialchars($request['test_name']); ?></div>
                        <div class="col-sm-4"><strong>Current Status:</strong> <?php echo getStatusBadge($request['status']); ?></div>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <?php echo getCSRFInput(); ?>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Select Scan Images (Multiple Allowed)</label>
                        <input type="file" class="form-control" name="images[]" id="imageInput" multiple accept="image/*" required>
                        <small class="text-muted">Allowed formats: JPG, JPEG, PNG, WEBP, GIF. Max file size: 5MB.</small>
                    </div>

                    <div id="filePreviewContainer" class="mb-4" style="display: none;">
                        <h6 class="fw-bold mb-3">Selected Scans & Descriptions:</h6>
                        <div id="previewsList" class="row g-3">
                            <!-- Dynamic preview rows -->
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary me-md-2">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-cloud-upload-alt me-1"></i>Upload & Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('imageInput').addEventListener('change', function(e) {
    const files = e.target.files;
    const container = document.getElementById('filePreviewContainer');
    const list = document.getElementById('previewsList');
    
    list.innerHTML = '';
    
    if (files.length > 0) {
        container.style.display = 'block';
        Array.from(files).forEach((file, index) => {
            const col = document.createElement('div');
            col.className = 'col-12';
            
            const reader = new FileReader();
            reader.onload = function(e) {
                col.innerHTML = `
                    <div class="card border">
                        <div class="card-body p-3">
                            <div class="row align-items-center">
                                <div class="col-md-2 col-4">
                                    <img src="${e.target.result}" class="img-fluid rounded border" style="max-height: 80px; width: auto;" alt="Preview">
                                </div>
                                <div class="col-md-10 col-8">
                                    <div class="mb-1 fw-bold text-truncate">${file.name} <small class="text-muted">(${(file.size / (1024 * 1024)).toFixed(2)} MB)</small></div>
                                    <input type="text" class="form-control form-control-sm" name="descriptions[${index}]" placeholder="Enter image description (e.g. Frontal view, Lateral view)...">
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
            reader.readAsDataURL(file);
            list.appendChild(col);
        });
    } else {
        container.style.display = 'none';
    }
});
</script>

<?php include '../includes/footer.php'; ?>
