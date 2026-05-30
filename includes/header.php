<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Smart Hospital Management System'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <link href="<?php echo BASE_PATH; ?>assets/css/style.css?v=2.2" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_PATH; ?>assets/images/favicon.ico">
    
    <!-- Meta Tags -->
    <meta name="description" content="Smart Hospital Management System - Complete healthcare solution">
    <meta name="keywords" content="hospital, management, healthcare, patient, doctor, appointment">
    <meta name="author" content="Smart Hospital Team">
    <meta name="csrf-token" content="<?php echo isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : ''; ?>">
    
    <?php if (isset($custom_css)): ?>
        <style><?php echo $custom_css; ?></style>
    <?php endif; ?>
    <script>
        const BASE_PATH = '<?php echo BASE_PATH; ?>';
    </script>
</head>
<body>
    <script>
        // Apply theme immediately before render to prevent flashing
        const storedTheme = localStorage.getItem('theme') || 'light';
        document.body.setAttribute('data-theme', storedTheme);
    </script>

    <?php if (isLoggedIn()): ?>
        <!-- Modern Glassmorphic Top Navbar -->
        <nav class="navbar navbar-expand-lg fixed-top">
            <div class="container-fluid">
                <!-- Global Sidebar Toggler -->
                <button class="btn btn-link text-white me-2" type="button" id="sidebarToggle" aria-label="Toggle Sidebar Menu">
                    <i class="fas fa-bars fa-lg"></i>
                </button>

                <a class="navbar-brand me-4" href="<?php echo BASE_PATH; ?>dashboard.php">
                    <i class="fas fa-hospital-alt me-2 text-primary"></i>
                    <strong>SHMS</strong>
                </a>
                
                <!-- Quick Search Bar inside Navbar (Accessible) -->
                <div class="d-none d-md-flex align-items-center ms-2 flex-grow-1" style="max-width: 320px;">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text border-end-0 bg-transparent text-muted"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control border-start-0 bg-transparent" id="globalSearch" placeholder="Quick search... (Ctrl + K)" aria-label="Quick Search">
                    </div>
                    <div id="searchResults" class="dropdown-menu shadow-lg p-2" style="position: absolute; top: 60px; width: 320px; display: none;"></div>
                </div>
                
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle Navigation">
                    <i class="fas fa-ellipsis-v text-secondary"></i>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto align-items-lg-center">
                        <!-- System Settings dropdown for admin, simplified layout -->
                        <?php if (hasRole('admin')): ?>
                            <li class="nav-item me-2">
                                <a class="nav-link" href="<?php echo BASE_PATH; ?>settings/system.php" title="System Settings">
                                    <i class="fas fa-sliders-h d-lg-none me-2"></i><span class="d-none d-lg-inline"><i class="fas fa-sliders-h"></i></span> Settings
                                </a>
                            </li>
                        <?php endif; ?>

                        <!-- Notifications Dropdown -->
                        <li class="nav-item dropdown me-2">
                            <a class="nav-link dropdown-toggle position-relative" href="#" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell d-lg-none me-2"></i><span class="d-none d-lg-inline"><i class="fas fa-bell"></i></span> Notifications
                                <span class="position-absolute top-1 start-100 translate-middle badge rounded-pill bg-danger" id="notificationCount" style="display: none; font-size: 0.65rem;">0</span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg py-0 border-0" id="notificationDropdown" aria-labelledby="notifDropdown" style="width: 320px;">
                                <li class="p-3 border-bottom"><h6 class="mb-0 fw-bold">Notifications</h6></li>
                                <li class="text-center p-3 text-muted"><small>No new notifications</small></li>
                            </ul>
                        </li>
                        
                        <!-- Theme Toggle Button Widget -->
                        <li class="nav-item me-3">
                            <button class="theme-switch-btn" id="themeToggleBtn" aria-label="Toggle Light/Dark Theme">
                                <i class="fas fa-moon" data-theme-icon></i>
                            </button>
                        </li>

                        <!-- Profile User Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="avatar-circle bg-primary-gradient text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 0.85rem; font-weight: 700;">
                                    <?php echo strtoupper(substr($_SESSION['user_name'], 0, 2)); ?>
                                </div>
                                <span class="d-lg-inline d-none"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 py-2" aria-labelledby="userDropdown" style="width: 220px;">
                                <li class="px-3 py-2 border-bottom">
                                    <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                                    <small class="text-success fw-semibold"><i class="fas fa-shield-alt me-1"></i><?php echo ucfirst($_SESSION['user_role']); ?></small>
                                </li>
                                <li><a class="dropdown-item py-2" href="<?php echo BASE_PATH; ?>profile.php">
                                    <i class="fas fa-user-circle me-2 text-muted"></i> My Profile
                                </a></li>
                                <li><a class="dropdown-item py-2" href="<?php echo BASE_PATH; ?>messages/index.php">
                                    <i class="fas fa-envelope me-2 text-muted"></i> Messages
                                </a></li>
                                <li><a class="dropdown-item py-2" href="<?php echo BASE_PATH; ?>settings/account.php">
                                    <i class="fas fa-user-cog me-2 text-muted"></i> Account Settings
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item py-2 text-danger" href="<?php echo BASE_PATH; ?>auth/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                                </a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        
        <!-- Modern Slate Sidebar Navigation -->
        <aside class="sidebar" id="appSidebar">
            <div class="position-sticky pt-2">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo BASE_PATH; ?>dashboard.php">
                            <i class="fas fa-chart-pie"></i> <span>Dashboard</span>
                        </a>
                    </li>
                    
                    <?php if (hasRole('admin')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/patients/') !== false ? 'active' : ''; ?>" href="<?php echo BASE_PATH; ?>patients/index.php">
                                <i class="fas fa-user-injured"></i> <span>Patients</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/doctors/') !== false ? 'active' : ''; ?>" href="<?php echo BASE_PATH; ?>doctors/index.php">
                                <i class="fas fa-user-md"></i> <span>Doctors</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/users/') !== false ? 'active' : ''; ?>" href="<?php echo BASE_PATH; ?>users/index.php">
                                <i class="fas fa-users-cog"></i> <span>All Users</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if (in_array($_SESSION['user_role'], ['admin', 'doctor', 'receptionist'])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/appointments/') !== false ? 'active' : ''; ?>" href="<?php echo BASE_PATH; ?>appointments/index.php">
                                <i class="fas fa-calendar-check"></i> <span>Appointments</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if (in_array($_SESSION['user_role'], ['admin', 'pharmacist'])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/pharmacy/') !== false ? 'active' : ''; ?>" href="<?php echo BASE_PATH; ?>pharmacy/index.php">
                                <i class="fas fa-pills"></i> <span>Pharmacy</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if (in_array($_SESSION['user_role'], ['admin', 'lab_technician'])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/laboratory/') !== false ? 'active' : ''; ?>" href="<?php echo BASE_PATH; ?>laboratory/index.php">
                                <i class="fas fa-flask"></i> <span>Laboratory</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if (in_array($_SESSION['user_role'], ['admin', 'doctor', 'lab_technician', 'radiologist'])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/radiology/') !== false ? 'active' : ''; ?>" href="<?php echo BASE_PATH; ?>radiology/index.php">
                                <i class="fas fa-x-ray"></i> <span>Radiology</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if (in_array($_SESSION['user_role'], ['admin', 'receptionist'])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/billing/') !== false ? 'active' : ''; ?>" href="<?php echo BASE_PATH; ?>billing/index.php">
                                <i class="fas fa-file-invoice-dollar"></i> <span>Billing</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/messages/') !== false ? 'active' : ''; ?>" href="<?php echo BASE_PATH; ?>messages/index.php">
                            <i class="fas fa-comments"></i> <span>Messages</span>
                        </a>
                    </li>
                </ul>
            </div>
        </aside>
        
        <!-- Premium Central Page Layout wrapper -->
        <main class="main-wrapper fade-in">
            <!-- Accessible Multi-Level Breadcrumbs & Dynamic Header Group -->
            <header class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-4 border-bottom no-print">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="custom-breadcrumb">
                            <li class="custom-breadcrumb-item"><a href="<?php echo BASE_PATH; ?>dashboard.php"><i class="fas fa-home me-1"></i>Home</a></li>
                            <?php
                            $path_parts = explode('/', trim($_SERVER['PHP_SELF'], '/'));
                            $filtered_parts = [];
                            $seen_smart = false;
                            foreach ($path_parts as $part) {
                                if (strtoupper($part) == 'SMART') {
                                    $seen_smart = true;
                                    continue;
                                }
                                if ($seen_smart || !in_array($part, ['XAAMP', 'htdocs'])) {
                                    $filtered_parts[] = $part;
                                }
                            }
                            
                            $current_uri = BASE_PATH;
                            $count = count($filtered_parts);
                            for ($i = 0; $i < $count; $i++) {
                                $part = $filtered_parts[$i];
                                if ($part == 'index.php' && $count == 1) continue;
                                $display_name = ucfirst(str_replace(['.php', '_', '-'], ['', ' ', ' '], $part));
                                if ($i === $count - 1) {
                                    echo '<li class="custom-breadcrumb-item active" aria-current="page">' . htmlspecialchars($display_name) . '</li>';
                                } else {
                                    $current_uri .= $part . '/';
                                    echo '<li class="custom-breadcrumb-item"><a href="' . $current_uri . 'index.php">' . htmlspecialchars($display_name) . '</a></li>';
                                }
                            }
                            ?>
                        </ol>
                    </nav>
                    <h1 class="h3 fw-bold mb-0 text-hospital-blue"><?php echo isset($page_heading) ? htmlspecialchars($page_heading) : 'Dashboard'; ?></h1>
                </div>
                
                <div class="btn-toolbar mb-2 mb-md-0 gap-2">
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()" aria-label="Print Current View">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportData()" aria-label="Download Page Data">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
            </header>
    <?php else: ?>
        <!-- Landing/Auth Page Header -->
        <?php 
        $current_page = basename($_SERVER['PHP_SELF']);
        $hide_header_pages = ['index.php', 'login.php', 'register.php', 'forgot-password.php', 'reset-password.php'];
        if (!in_array($current_page, $hide_header_pages)): 
        ?>
        <header class="bg-primary text-white py-3 no-print">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h1 class="h3 mb-0">
                            <i class="fas fa-hospital-alt me-2"></i>
                            Smart Hospital Management System
                        </h1>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <span class="me-3"><i class="fas fa-phone me-1"></i> +1 234 567 8900</span>
                        <span><i class="fas fa-envelope me-1"></i> info@shms.com</span>
                    </div>
                </div>
            </div>
        </header>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Flash Notifications Container -->
    <?php if (isLoggedIn()): ?>
        <div class="container-fluid px-0 mb-4 fade-in">
            <?php displayNotification(); ?>
        </div>
    <?php endif; ?>
