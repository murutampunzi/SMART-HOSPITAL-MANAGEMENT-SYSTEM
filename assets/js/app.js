// Smart Hospital Management System - Main JavaScript

// Global variables
let currentUser = null;
let notifications = [];
let socket = null;

// Initialize application
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

function initializeApp() {
    // Check if user is logged in
    if (typeof isLoggedIn !== 'undefined' && isLoggedIn()) {
        loadCurrentUser();
        initializeNotifications();
        initializeRealTime();
        initializeTheme();
    }
    
    // Initialize tooltips
    initializeTooltips();
    
    // Initialize modals
    initializeModals();
    
    // Initialize forms
    initializeForms();
    
    // Initialize data tables
    initializeDataTables();
    
    // Initialize charts
    initializeCharts();
}

// User management
function loadCurrentUser() {
    fetch(BASE_PATH + 'api/user.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentUser = data.data;
                updateUserInterface();
            }
        })
        .catch(error => console.error('Error loading user:', error));
}

function updateUserInterface() {
    if (currentUser) {
        // Update user info in UI
        const userElements = document.querySelectorAll('[data-user-name]');
        userElements.forEach(el => {
            el.textContent = currentUser.name;
        });
        
        // Update role-based UI
        updateRoleBasedUI(currentUser.role);
    }
}

function updateRoleBasedUI(role) {
    // Hide/show elements based on user role
    const roleElements = document.querySelectorAll('[data-roles]');
    roleElements.forEach(el => {
        const allowedRoles = el.getAttribute('data-roles').split(',');
        if (allowedRoles.includes(role)) {
            el.style.display = '';
        } else {
            el.style.display = 'none';
        }
    });
}

// Notification system
function initializeNotifications() {
    loadNotifications();
    
    // Auto-refresh notifications every 30 seconds
    setInterval(loadNotifications, 30000);
    
    // Mark notifications as read when clicked
    document.addEventListener('click', function(e) {
        if (e.target.closest('.notification-item')) {
            const notificationId = e.target.closest('.notification-item').getAttribute('data-notification-id');
            markNotificationAsRead(notificationId);
        }
    });
}

function loadNotifications() {
    fetch(BASE_PATH + 'api/notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                notifications = data.data;
                updateNotificationUI();
            }
        })
        .catch(error => console.error('Error loading notifications:', error));
}

function updateNotificationUI() {
    const countElement = document.getElementById('notificationCount');
    const dropdownElement = document.getElementById('notificationDropdown');
    
    if (countElement) {
        const unreadCount = notifications.filter(n => !n.read).length;
        countElement.textContent = unreadCount;
        countElement.style.display = unreadCount > 0 ? '' : 'none';
    }
    
    if (dropdownElement) {
        const html = notifications.slice(0, 5).map(notification => `
            <li>
                <a class="dropdown-item notification-item" href="${notification.link}" data-notification-id="${notification.id}">
                    <div class="d-flex align-items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-${getNotificationIcon(notification.type)} text-${getNotificationColor(notification.type)}"></i>
                        </div>
                        <div class="flex-grow-1 ms-2">
                            <div class="fw-bold">${notification.title}</div>
                            <small class="text-muted">${notification.message}</small>
                            <div><small class="text-muted">${timeAgo(notification.created_at)}</small></div>
                        </div>
                        ${!notification.read ? '<div class="flex-shrink-0"><span class="badge bg-primary">New</span></div>' : ''}
                    </div>
                </a>
            </li>
        `).join('');
        
        if (notifications.length === 0) {
            dropdownElement.innerHTML = '<li><h6 class="dropdown-header">Notifications</h6></li><li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-muted">No notifications</a></li>';
        } else {
            dropdownElement.innerHTML = '<li><h6 class="dropdown-header">Notifications</h6></li><li><hr class="dropdown-divider"></li>' + html + 
                                      '<li><hr class="dropdown-divider"></li><li><a class="dropdown-item" href="notifications/index.php">View All Notifications</a></li>';
        }
    }
}

function markNotificationAsRead(notificationId) {
    fetch(BASE_PATH + 'api/notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'mark_read',
            notification_id: notificationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
        }
    })
    .catch(error => console.error('Error marking notification as read:', error));
}

function getNotificationIcon(type) {
    const icons = {
        'appointment': 'calendar-check',
        'message': 'envelope',
        'lab_result': 'flask',
        'prescription': 'pills',
        'payment': 'credit-card',
        'system': 'cog',
        'emergency': 'ambulance',
        'reminder': 'bell'
    };
    return icons[type] || 'bell';
}

function getNotificationColor(type) {
    const colors = {
        'appointment': 'primary',
        'message': 'info',
        'lab_result': 'success',
        'prescription': 'warning',
        'payment': 'success',
        'system': 'secondary',
        'emergency': 'danger',
        'reminder': 'warning'
    };
    return colors[type] || 'primary';
}

// Real-time features
function initializeRealTime() {
    // WebSocket connection for real-time updates
    if (typeof WebSocket !== 'undefined') {
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${protocol}//${window.location.host}/ws`;
        
        try {
            socket = new WebSocket(wsUrl);
            
            socket.onopen = function() {
                console.log('WebSocket connected');
            };
            
            socket.onmessage = function(event) {
                const data = JSON.parse(event.data);
                handleRealTimeUpdate(data);
            };
            
            socket.onclose = function() {
                console.log('WebSocket disconnected');
                // Attempt to reconnect after 5 seconds
                setTimeout(initializeRealTime, 5000);
            };
            
            socket.onerror = function(error) {
                console.error('WebSocket error:', error);
            };
        } catch (error) {
            console.error('WebSocket initialization failed:', error);
        }
    }
}

function handleRealTimeUpdate(data) {
    switch (data.type) {
        case 'notification':
            notifications.unshift(data.notification);
            updateNotificationUI();
            showToast(data.notification.message, data.notification.type);
            break;
        case 'appointment_update':
            if (window.location.pathname.includes('appointments')) {
                refreshAppointments();
            }
            break;
        case 'message':
            if (window.location.pathname.includes('messages')) {
                refreshMessages();
            }
            break;
        case 'system_update':
            showToast(data.message, 'info');
            break;
    }
}

// Theme management
function initializeTheme() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    setTheme(savedTheme);
}

function toggleTheme() {
    const currentTheme = document.body.getAttribute('data-theme') || 'light';
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    setTheme(newTheme);
    localStorage.setItem('theme', newTheme);
}

function setTheme(theme) {
    document.body.setAttribute('data-theme', theme);
    
    const themeIcon = document.querySelector('[data-theme-icon]');
    if (themeIcon) {
        themeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
    
    // Update chart themes dynamically if Chart.js instances exist
    updateChartThemes(theme);
}

// Tooltips
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Modals
function initializeModals() {
    // Auto-focus first input in modal
    document.addEventListener('shown.bs.modal', function(e) {
        const firstInput = e.target.querySelector('input, textarea, select');
        if (firstInput) {
            firstInput.focus();
        }
    });
    
    // Clear form when modal is hidden
    document.addEventListener('hidden.bs.modal', function(e) {
        const form = e.target.querySelector('form');
        if (form && !form.hasAttribute('data-no-reset')) {
            form.reset();
            clearValidationErrors(form);
        }
    });
}

// Forms
function initializeForms() {
    // Add CSRF token to all forms
    document.querySelectorAll('form').forEach(form => {
        if (!form.querySelector('input[name="csrf_token"]')) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = getCSRFToken();
            form.appendChild(csrfInput);
        }
    });
    
    // Form validation
    document.querySelectorAll('form[data-validate]').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(form)) {
                e.preventDefault();
            }
        });
    });
    
    // Auto-save functionality
    document.querySelectorAll('form[data-auto-save]').forEach(form => {
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('input', debounce(() => autoSaveForm(form), 1000));
        });
    });
}

function validateForm(form) {
    let isValid = true;
    clearValidationErrors(form);
    
    // Required fields
    const requiredFields = form.querySelectorAll('[required]');
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            showFieldError(field, 'This field is required');
            isValid = false;
        }
    });
    
    // Email validation
    const emailFields = form.querySelectorAll('input[type="email"]');
    emailFields.forEach(field => {
        if (field.value && !isValidEmail(field.value)) {
            showFieldError(field, 'Please enter a valid email address');
            isValid = false;
        }
    });
    
    // Password validation
    const passwordFields = form.querySelectorAll('input[type="password"][data-validate-strength]');
    passwordFields.forEach(field => {
        if (field.value && !isValidPassword(field.value)) {
            showFieldError(field, 'Password must be at least 8 characters long');
            isValid = false;
        }
    });
    
    // Custom validation
    const customValidations = form.querySelectorAll('[data-validate-custom]');
    customValidations.forEach(field => {
        const validation = field.getAttribute('data-validate-custom');
        if (!customValidation(field, validation)) {
            isValid = false;
        }
    });
    
    return isValid;
}

function showFieldError(field, message) {
    const formGroup = field.closest('.form-group, .mb-3');
    if (formGroup) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback d-block';
        errorDiv.textContent = message;
        formGroup.appendChild(errorDiv);
        field.classList.add('is-invalid');
    }
}

function clearValidationErrors(form) {
    form.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function isValidPassword(password) {
    return password.length >= 8;
}

function customValidation(field, validation) {
    // Add custom validation logic here
    return true;
}

function autoSaveForm(form) {
    const formData = new FormData(form);
    const formId = form.id || 'auto-save-form';
    
    localStorage.setItem(formId, JSON.stringify(Object.fromEntries(formData)));
}

// Data tables
function initializeDataTables() {
    document.querySelectorAll('table[data-datatable]').forEach(table => {
        initializeDataTable(table);
    });
}

function initializeDataTable(table) {
    const options = {
        pageLength: 10,
        responsive: true,
        order: [[0, 'desc']],
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        }
    };
    
    // Initialize DataTable if jQuery and DataTable are available
    if (typeof $ !== 'undefined' && $.fn.DataTable) {
        $(table).DataTable(options);
    }
}

// Charts Management
window.activeCharts = [];

function initializeCharts() {
    window.activeCharts = [];
    document.querySelectorAll('canvas[data-chart]').forEach(canvas => {
        initializeChart(canvas);
    });
}

function initializeChart(canvas) {
    const chartType = canvas.getAttribute('data-chart');
    const chartData = JSON.parse(canvas.getAttribute('data-chart-data') || '{}');
    const activeTheme = document.body.getAttribute('data-theme') || 'light';
    
    // Sleek HSL styled chart color systems matching active theme
    const gridColor = activeTheme === 'dark' ? '#1f2937' : '#e2e8f0';
    const textColor = activeTheme === 'dark' ? '#9ca3af' : '#64748b';
    
    const options = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    color: textColor,
                    font: { family: 'Inter', weight: '500' }
                }
            }
        },
        scales: {
            x: {
                grid: { color: gridColor },
                ticks: { color: textColor, font: { family: 'Inter' } }
            },
            y: {
                grid: { color: gridColor },
                ticks: { color: textColor, font: { family: 'Inter' } }
            }
        }
    };
    
    // Tweak dataset colors based on theme and chart logic for stunning visuals
    if (chartData.datasets && chartData.datasets[0]) {
        if (chartType === 'line') {
            chartData.datasets[0].borderColor = '#3b82f6';
            chartData.datasets[0].backgroundColor = 'rgba(59, 130, 246, 0.08)';
            chartData.datasets[0].fill = true;
            chartData.datasets[0].pointBackgroundColor = '#3b82f6';
            chartData.datasets[0].pointBorderColor = '#ffffff';
            chartData.datasets[0].pointHoverRadius = 6;
        } else if (chartType === 'bar') {
            chartData.datasets[0].backgroundColor = 'rgba(99, 102, 241, 0.8)';
            chartData.datasets[0].hoverBackgroundColor = '#4f46e5';
            chartData.datasets[0].borderColor = '#6366f1';
            chartData.datasets[0].borderWidth = 1.5;
            chartData.datasets[0].borderRadius = 6;
        }
    }
    
    try {
        const chartInstance = new Chart(canvas, {
            type: chartType,
            data: chartData,
            options: options
        });
        window.activeCharts.push(chartInstance);
    } catch (error) {
        console.error('Error initializing chart:', error);
    }
}

// Dynamically refresh Chart themes
function updateChartThemes(theme) {
    if (!window.activeCharts || !window.activeCharts.length) return;
    
    const gridColor = theme === 'dark' ? '#1f2937' : '#e2e8f0';
    const textColor = theme === 'dark' ? '#9ca3af' : '#64748b';
    
    window.activeCharts.forEach(chart => {
        if (chart.options.scales) {
            if (chart.options.scales.x) {
                chart.options.scales.x.grid.color = gridColor;
                chart.options.scales.x.ticks.color = textColor;
            }
            if (chart.options.scales.y) {
                chart.options.scales.y.grid.color = gridColor;
                chart.options.scales.y.ticks.color = textColor;
            }
        }
        if (chart.options.plugins && chart.options.plugins.legend) {
            chart.options.plugins.legend.labels.color = textColor;
        }
        chart.update();
    });
}

// Utility functions
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toastContainer') || createToastContainer();
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', () => {
        toast.remove();
    });
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '1050';
    document.body.appendChild(container);
    return container;
}

function timeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
    if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
    if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
    return date.toLocaleDateString();
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function formatCurrency(amount, currency = '$') {
    return currency + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function formatDate(date, format = 'YYYY-MM-DD') {
    const d = new Date(date);
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    
    return format
        .replace('YYYY', year)
        .replace('MM', month)
        .replace('DD', day);
}

function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// File upload
function initializeFileUpload() {
    document.querySelectorAll('.file-upload').forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const maxSize = parseInt(input.getAttribute('data-max-size')) || 5 * 1024 * 1024; // 5MB
                const allowedTypes = (input.getAttribute('data-allowed-types') || '').split(',');
                
                if (file.size > maxSize) {
                    showToast('File size exceeds maximum limit', 'danger');
                    input.value = '';
                    return;
                }
                
                if (allowedTypes.length && !allowedTypes.includes(file.type)) {
                    showToast('File type not allowed', 'danger');
                    input.value = '';
                    return;
                }
                
                // Show file preview
                showFilePreview(file, input);
            }
        });
    });
}

function showFilePreview(file, input) {
    const preview = document.getElementById(input.getAttribute('data-preview'));
    if (preview) {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = `<img src="${e.target.result}" class="img-thumbnail" style="max-height: 200px;">`;
            };
            reader.readAsDataURL(file);
        } else {
            preview.innerHTML = `<div class="alert alert-info"><i class="fas fa-file me-2"></i>${file.name}</div>`;
        }
    }
}

// Search functionality
function initializeSearch() {
    const searchInput = document.getElementById('globalSearch');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function(e) {
            const query = e.target.value;
            if (query.length >= 3) {
                performGlobalSearch(query);
            }
        }, 300));
    }
}

function performGlobalSearch(query) {
    fetch(BASE_PATH + `api/search.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displaySearchResults(data.data);
            }
        })
        .catch(error => console.error('Search error:', error));
}

function displaySearchResults(results) {
    const resultsContainer = document.getElementById('searchResults');
    if (resultsContainer) {
        const html = results.map(result => `
            <div class="search-result-item">
                <h6><a href="${result.url}">${result.title}</a></h6>
                <p class="text-muted">${result.description}</p>
            </div>
        `).join('');
        
        resultsContainer.innerHTML = html;
        resultsContainer.style.display = results.length ? 'block' : 'none';
    }
}

// Print functionality
function printElement(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Print</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                    <link href="assets/css/style.css" rel="stylesheet">
                </head>
                <body>
                    ${element.innerHTML}
                </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }
}

// Export functionality
function exportData(format, url) {
    const exportUrl = `${url}&export=${format}`;
    window.open(exportUrl, '_blank');
}

// Keyboard shortcuts
function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + K for search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.getElementById('globalSearch');
            if (searchInput) {
                searchInput.focus();
            }
        }
        
        // Escape to close modals
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal.show');
            if (openModal) {
                bootstrap.Modal.getInstance(openModal).hide();
            }
        }
    });
}

// Check if user is logged in (PHP function should be available globally)
function isLoggedIn() {
    return typeof window.isLoggedIn !== 'undefined' ? window.isLoggedIn : false;
}

// Get CSRF token
function getCSRFToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

// Initialize everything when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeKeyboardShortcuts();
    initializeFileUpload();
    initializeSearch();
});

// Global error handler
window.addEventListener('error', function(e) {
    console.error('Global error:', e.error);
    // Log to server in production
    if (window.location.hostname !== 'localhost') {
        fetch(BASE_PATH + 'api/log-error.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                message: e.error.message,
                stack: e.error.stack,
                url: window.location.href
            })
        });
    }
});

// Service Worker for offline support
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js')
        .then(registration => {
            console.log('ServiceWorker registration successful');
        })
        .catch(error => {
            console.log('ServiceWorker registration failed:', error);
        });
}
