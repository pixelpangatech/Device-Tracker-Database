<?php
session_start();
if (empty($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $role = $_SESSION['role'];
    $is_valid = false;
    $stored_hash = '';

    if ($role === 'admin') {
        $credentials_file = __DIR__ . '/admin_credentials.json';
        if (file_exists($credentials_file)) {
            $creds = json_decode(file_get_contents($credentials_file), true);
            $stored_hash = $creds['admin'] ?? '';
        } else {
            $stored_hash = '$2y$10$Nmkqbpoc9il9kpyNGn/tuOU1EmfS2LI7r2sMDmH2dyrnzKQOssgtW'; // default adminpassword123
        }
        $is_valid = password_verify($current, $stored_hash);
    } else {
        require_once 'db.php';
        $user_name = $_SESSION['user_name'];
        $stmt = $pdo->prepare("SELECT password FROM master_users WHERE name = ?");
        $stmt->execute([$user_name]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && $user['password'] !== null) {
            $is_valid = password_verify($current, $user['password']);
        }
    }

    if (!$is_valid) {
        $error = "Incorrect current password!";
    } elseif ($new !== $confirm) {
        $error = "New passwords do not match!";
    } elseif (strlen($new) < 6) {
        $error = "New password must be at least 6 characters long.";
    } else {
        $new_hash = password_hash($new, PASSWORD_DEFAULT);

        if ($role === 'admin') {
            $credentials_file = __DIR__ . '/admin_credentials.json';
            $new_creds = ['admin' => $new_hash];
            if (file_put_contents($credentials_file, json_encode($new_creds, JSON_PRETTY_PRINT))) {
                $success = "Password updated successfully!";
            } else {
                $error = "Failed to save new password. Check file permissions.";
            }
        } else {
            require_once 'db.php';
            $user_name = $_SESSION['user_name'];
            $stmt = $pdo->prepare("UPDATE master_users SET password = ? WHERE name = ?");
            if ($stmt->execute([$new_hash, $user_name])) {
                $success = "Password updated successfully!";
                if (isset($_SESSION['must_change_password'])) {
                    unset($_SESSION['must_change_password']);
                    $success .= " You can now return to the Dashboard.";
                }
            } else {
                $error = "Failed to save new password to database.";
            }
        }
    }
}

$page_title = "Change Password";
require_once 'header.php';
?>



<div class="change-password-card">
    <div class="text-center mb-4">
        <div class="icon-wrapper">
            <i class="fa-solid fa-shield-halved fa-3x"
                style="background: linear-gradient(135deg, #10b981, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
        </div>

        <?php if (!empty($_SESSION['must_change_password'])): ?>
            <div class="alert alert-warning mt-3" style="border-radius: 12px; font-size: 0.9rem;">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> Please change your password now.
            </div>
        <?php endif; ?>

        <h3 class="fw-bold text-adaptive mb-1 mt-3" style="letter-spacing: -0.5px;">Change Password</h3>
        <p class="text-muted small">Update your account password</p>
    </div>

    <?php if ($error): ?>
        <div class="alert py-2 px-3 text-center small border-0 mb-4"
            style="background: rgba(244, 63, 94, 0.15); color: #fb7185; border-radius: 10px;">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert py-2 px-3 text-center small border-0 mb-4"
            style="background: rgba(16, 185, 129, 0.15); color: #34d399; border-radius: 10px;">
            <i class="fa-solid fa-circle-check me-2"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <form action="change_password.php" method="POST">
        <div class="mb-3">
            <label class="form-label small text-uppercase fw-bold" style="color: #94a3b8; letter-spacing: 1px;">Current
                Password</label>
            <input type="password" name="current_password" class="form-control form-control-custom"
                placeholder="Enter current password" required>
        </div>
        <div class="mb-3">
            <label class="form-label small text-uppercase fw-bold" style="color: #94a3b8; letter-spacing: 1px;">New
                Password</label>
            <input type="password" name="new_password" class="form-control form-control-custom"
                placeholder="Enter new password" required>
        </div>
        <div class="mb-4">
            <label class="form-label small text-uppercase fw-bold" style="color: #94a3b8; letter-spacing: 1px;">Confirm
                New Password</label>
            <input type="password" name="confirm_password" class="form-control form-control-custom"
                placeholder="Confirm new password" required>
        </div>
        <button type="submit" class="btn btn-update">
            <i class="fa-solid fa-key me-2"></i> Update Password
        </button>
    </form>

    <div class="text-center mt-4">
        <?php if (!empty($_SESSION['must_change_password'])): ?>
            <a href="logout.php" class="text-decoration-none text-muted"
                style="font-size: 0.85rem; font-weight: 600; transition: color 0.2s;">
                <i class="fa-solid fa-user-ninja me-1"></i> Continue as Guest
            </a>
        <?php else: ?>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="admin.php" class="text-decoration-none"
                    style="color: #64748b; font-size: 0.85rem; font-weight: 600; transition: color 0.2s;">
                    <i class="fa-solid fa-arrow-left me-1"></i> Return to Admin Panel
                </a>
            <?php else: ?>
                <a href="index.php" class="text-decoration-none"
                    style="color: #64748b; font-size: 0.85rem; font-weight: 600; transition: color 0.2s;">
                    <i class="fa-solid fa-arrow-left me-1"></i> Return to Dashboard
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</main>
</body>

</html>