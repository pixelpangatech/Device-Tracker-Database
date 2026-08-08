<?php
session_start();

if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin.php");
    } else {
        header("Location: index.php");
    }
    exit();
}

require_once 'db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_type = $_POST['login_type'] ?? 'admin';

    if ($login_type === 'admin') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        // Read Admin Credentials from JSON
        $credentials_file = __DIR__ . '/admin_credentials.json';
        $stored_hash = '';
        
        if (file_exists($credentials_file)) {
            $creds = json_decode(file_get_contents($credentials_file), true);
            $stored_hash = $creds['admin'] ?? '';
        } else {
            $stored_hash = '$2y$10$Nmkqbpoc9il9kpyNGn/tuOU1EmfS2LI7r2sMDmH2dyrnzKQOssgtW'; // default: adminpassword123
        }

        if ($username === 'admin' && password_verify($password, $stored_hash)) {
            $_SESSION['is_admin_logged_in'] = true;
            $_SESSION['role'] = 'admin';
            $_SESSION['user_name'] = 'Admin';
            header("Location: admin.php");
            exit();
        } else {
            $error = "Invalid Admin Username or Password!";
        }
    } elseif ($login_type === 'user') {
        $user_name = $_POST['user_name'] ?? '';
        $password = $_POST['user_password'] ?? '';
        
        $stmt = $pdo->prepare("SELECT name, password, is_admin FROM master_users WHERE name = ?");
        $stmt->execute([$user_name]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            if ($user['password'] !== null && password_verify($password, $user['password'])) {
                $_SESSION['role'] = $user['is_admin'] ? 'admin' : 'user';
                $_SESSION['user_name'] = $user_name;
                if ($user['is_admin']) {
                    $_SESSION['is_admin_logged_in'] = true;
                }
                
                if ($password === '123456') {
                    $_SESSION['must_change_password'] = true;
                    header("Location: change_password.php");
                } else {
                    if ($user['is_admin']) {
                        header("Location: admin.php");
                    } else {
                        header("Location: index.php");
                    }
                }
                exit();
            } else {
                $error = "Incorrect Password!";
            }
        } else {
            $error = "Please select a valid user!";
        }
    }
}

// Fetch users for dropdown
$stmt = $pdo->query("SELECT name FROM master_users ORDER BY name ASC");
$users = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gateway - TechVault</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .role-btn {
            background: var(--input-bg-dark);
            border: 1px solid var(--dark-border);
            color: var(--text-muted);
            transition: all 0.3s;
        }
        .role-btn.active {
            background: linear-gradient(135deg, #06b6d4, #8b5cf6);
            color: #fff;
            border-color: transparent;
            font-weight: bold;
        }
    </style>
</head>
<body class="login-page">

<div class="login-card">
    <div class="text-center mb-4">
        <div class="icon-wrapper">
            <i class="fa-solid fa-fingerprint fa-3x" style="background: linear-gradient(135deg, #06b6d4, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
        </div>
        <h3 class="fw-bold text-adaptive mb-1" style="letter-spacing: -0.5px;">Portal Gateway</h3>
        <p class="text-muted small">Select your role to authenticate</p>
    </div>

    <div class="d-flex justify-content-center gap-2 mb-4">
        <button class="btn role-btn active px-4 py-2" id="btnAdmin" onclick="setRole('admin')">
            <i class="fa-solid fa-shield-halved me-1"></i> Admin
        </button>
        <button class="btn role-btn px-4 py-2" id="btnUser" onclick="setRole('user')">
            <i class="fa-solid fa-user me-1"></i> Employee
        </button>
    </div>

    <?php if ($error): ?>
        <div class="alert py-2 px-3 text-center small border-0 mb-4" style="background: rgba(244, 63, 94, 0.15); color: #fb7185; border-radius: 10px;">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form action="login.php" method="POST" id="loginForm">
        <input type="hidden" name="login_type" id="loginType" value="admin">
        
        <!-- Admin Fields -->
        <div id="adminFields">
            <div class="mb-3">
                <label class="form-label small text-uppercase fw-bold" style="color: #94a3b8; letter-spacing: 1px;">Username</label>
                <input type="text" id="adminUsername" name="username" class="form-control form-control-custom" placeholder="Enter Username" required>
            </div>
            <div class="mb-4">
                <label class="form-label small text-uppercase fw-bold" style="color: #94a3b8; letter-spacing: 1px;">Password</label>
                <input type="password" id="adminPassword" name="password" class="form-control form-control-custom" placeholder="Enter Password" required>
            </div>
        </div>

        <!-- User Fields -->
        <div id="userFields" style="display: none;">
            <div class="mb-4">
                <label class="form-label small text-uppercase fw-bold" style="color: #94a3b8; letter-spacing: 1px;">Select Employee</label>
                <select name="user_name" id="userNameSelect" class="form-control form-control-custom form-select">
                    <option value="">-- Choose Name --</option>
                    <?php foreach($users as $u): ?>
                        <option value="<?php echo htmlspecialchars($u); ?>"><?php echo htmlspecialchars($u); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="form-label small text-uppercase fw-bold" style="color: #94a3b8; letter-spacing: 1px;">Password</label>
                <input type="password" id="userPassword" name="user_password" class="form-control form-control-custom" placeholder="Enter Password">
            </div>
        </div>

        <button type="submit" class="btn btn-login">
            <i class="fa-solid fa-unlock-keyhole me-2"></i> Authenticate
        </button>
    </form>
    
    <div class="text-center mt-4">
        <a href="index.php" class="text-decoration-none" style="color: #64748b; font-size: 0.85rem; font-weight: 600; transition: color 0.2s;">
            <i class="fa-solid fa-arrow-left me-1"></i> Return to Dashboard
        </a>
    </div>
</div>

<script>
    function setRole(role) {
        document.getElementById('loginType').value = role;
        
        // Toggle buttons
        document.getElementById('btnAdmin').classList.toggle('active', role === 'admin');
        document.getElementById('btnUser').classList.toggle('active', role === 'user');
        
        // Toggle fields
        if (role === 'admin') {
            document.getElementById('adminFields').style.display = 'block';
            document.getElementById('userFields').style.display = 'none';
            document.getElementById('adminUsername').required = (role === 'admin');
            document.getElementById('adminPassword').required = (role === 'admin');
            document.getElementById('userNameSelect').required = (role === 'user');
            document.getElementById('userPassword').required = (role === 'user');
        } else {
            document.getElementById('adminFields').style.display = 'none';
            document.getElementById('userFields').style.display = 'block';
            document.getElementById('adminUsername').required = false;
            document.getElementById('adminPassword').required = false;
            document.getElementById('userNameSelect').required = true;
            document.getElementById('userPassword').required = true;
        }
    }
</script>

</body>
</html>
