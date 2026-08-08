<?php
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/logo.png">
    <title>TechVault</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts & Font Awesome Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="style.css">
    
    <!-- Restore Scroll Position After Reload -->
    <script>
        document.addEventListener("DOMContentLoaded", function(event) {
            var scrollpos = sessionStorage.getItem('scrollpos');
            if (scrollpos) {
                window.scrollTo(0, scrollpos);
                sessionStorage.removeItem('scrollpos');
            }
        });
        window.addEventListener("beforeunload", function(e) {
            sessionStorage.setItem('scrollpos', window.scrollY);
        });
    </script>
</head>

<?php $page_class = basename($_SERVER['PHP_SELF'], '.php') . "-page"; ?>
<body class="<?php echo htmlspecialchars($page_class); ?>">

    <header class="premium-header">
        <div class="container-fluid px-lg-5" style="padding-left: 50px; padding-right: 50px;">
            <div class="header-layout">
                <a href="index.php" class="navbar-brand-custom">
                    <div class="brand-icon-wrapper" style="display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; background: linear-gradient(135deg, #0ea5e9, #10b981); border-radius: 8px; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);">
                        <i class="fa-solid fa-vault text-white" style="font-size: 14px;"></i>
                    </div>
                    TechVault
                </a>

                <nav class="header-nav">
                    <a href="index.php"
                        class="nav-link-custom <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-chart-line me-1"></i> Dashboard
                    </a>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="admin.php" class="nav-link-custom <?php echo basename($_SERVER['PHP_SELF']) == 'admin.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-screwdriver-wrench me-1"></i> Admin Panel
                    </a>
                    <a href="employees.php" class="nav-link-custom <?php echo basename($_SERVER['PHP_SELF']) == 'employees.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-users me-1"></i> Employees
                    </a>
                    <?php endif; ?>

                    <div class="dropdown ms-2">
                        <a href="#" class="nav-link-custom dropdown-toggle d-flex align-items-center justify-content-center" data-bs-toggle="dropdown" aria-expanded="false" style="padding: 0 15px; height: 42px; border-radius: 20px; background: var(--nav-link-hover-bg); border: 1px solid var(--dark-border);">
                            <i class="fa-solid fa-circle-user fs-5 text-cyan-400 me-2" style="color: var(--accent-cyan);"></i>
                            <span class="fw-bold text-adaptive" style="font-size: 0.9rem;">
                                <?php echo !empty($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Guest'; ?>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end glass-dropdown">
                            <?php if (empty($_SESSION['role'])): ?>
                                <li><a class="dropdown-item" href="login.php"><i class="fa-solid fa-right-to-bracket me-2 text-cyan-400" style="color: var(--accent-cyan);"></i> Login</a></li>
                            <?php else: ?>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                <li><a class="dropdown-item" href="change_password.php"><i class="fa-solid fa-key me-2 text-emerald-400" style="color: var(--accent-emerald);"></i> Change Password</a></li>
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#emailSettingsModal"><i class="fa-solid fa-envelope me-2 text-blue-400" style="color: var(--accent-blue);"></i> Email Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item text-danger fw-bold" href="logout.php"><i class="fa-solid fa-power-off me-2"></i> Logout</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </nav>
            </div>
        </div>
    </header>
    
    <?php
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $smtpStmt = $pdo->query("SELECT * FROM smtp_settings LIMIT 1");
        $smtpConfig = $smtpStmt->fetch(PDO::FETCH_ASSOC);
        if (!$smtpConfig) {
            $smtpConfig = ['smtp_host' => '', 'smtp_port' => '587', 'smtp_user' => '', 'smtp_pass' => ''];
        }
    ?>
    <!-- Email Settings Modal -->
    <div class="modal fade" id="emailSettingsModal" tabindex="-1" aria-labelledby="emailSettingsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-card" style="border: 1px solid var(--dark-border);">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-light" id="emailSettingsModalLabel"><i class="fa-solid fa-envelope me-2 text-primary"></i> Email Configuration (SMTP)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="admin.php" method="POST">
                    <input type="hidden" name="action" value="save_email_settings">
                    <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                    <div class="modal-body">
                        <p class="text-light opacity-75 small mb-4">Configure Gmail SMTP or another provider to send emails from the system. (Use an App Password if using Gmail)</p>
                        
                        <div class="mb-3">
                            <label class="form-label text-light fw-semibold">SMTP Host</label>
                            <input type="text" class="form-control glass-input" name="smtp_host" placeholder="e.g., smtp.gmail.com" value="<?php echo htmlspecialchars($smtpConfig['smtp_host']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-light fw-semibold">SMTP Port</label>
                            <input type="number" class="form-control glass-input" name="smtp_port" placeholder="e.g., 587" value="<?php echo htmlspecialchars($smtpConfig['smtp_port']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-light fw-semibold">SMTP Username (Email)</label>
                            <input type="email" class="form-control glass-input" name="smtp_user" placeholder="e.g., your-email@gmail.com" value="<?php echo htmlspecialchars($smtpConfig['smtp_user']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-light fw-semibold">SMTP Password (App Password)</label>
                            <input type="password" class="form-control glass-input" name="smtp_pass" placeholder="Enter password or app password" value="<?php echo htmlspecialchars($smtpConfig['smtp_pass']); ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="button" class="btn btn-secondary glass-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary glass-btn">Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php } ?>

    <main class="main-content-wrapper">