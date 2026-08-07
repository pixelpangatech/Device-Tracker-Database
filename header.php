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
    <title>Device Tracker Database</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts & Font Awesome Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="style.css">
</head>

<?php $page_class = basename($_SERVER['PHP_SELF'], '.php') . "-page"; ?>
<body class="<?php echo htmlspecialchars($page_class); ?>">

    <header class="premium-header">
        <div class="container-fluid px-lg-5" style="padding-left: 50px; padding-right: 50px;">
            <div class="header-layout">
                <a href="index.php" class="navbar-brand-custom">
                    <div class="logo-icon-wrapper">
                        <i class="fa-solid fa-mobile-screen text-white fs-5"
                            style="position: relative; z-index: 2;"></i>
                    </div>
                    Device Tracker Database
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
    <main class="main-content-wrapper">