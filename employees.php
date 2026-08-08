<?php
require_once __DIR__ . '/db.php';

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$today_date = date('d-m-Y');
$view_date_ymd = $_GET['log_date'] ?? date('Y-m-d');
$view_date_dmy = date('d-m-Y', strtotime($view_date_ymd));

// Process POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_master_user') {
        $item_name = trim($_POST['item_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($item_name) {
            $defaultPassword = '123456';
            $defaultHash = password_hash($defaultPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT IGNORE INTO master_users (name, password, email) VALUES (?, ?, ?)");
            $stmt->execute([$item_name, $defaultHash, $email]);
            
            // Try sending welcome email
            if ($email) {
                sendWelcomeEmail($email, $item_name, $item_name, $defaultPassword);
            }
        }
        header("Location: employees.php");
        exit();
    } elseif ($action === 'delete_master_user') {
        $id = $_POST['id'] ?? '';
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM master_users WHERE id = ?");
            $stmt->execute([$id]);
        }
        header("Location: employees.php");
        exit();
    } elseif ($action === 'reset_master_user_password') {
        $id = $_POST['id'] ?? '';
        if ($id) {
            $defaultHash = password_hash('123456', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE master_users SET password = ? WHERE id = ?");
            $stmt->execute([$defaultHash, $id]);
        }
        header("Location: employees.php");
        exit();
    } elseif ($action === 'update_master_user_email') {
        $id = $_POST['id'] ?? '';
        $email = trim($_POST['email'] ?? '');
        if ($id) {
            $stmt = $pdo->prepare("UPDATE master_users SET email = ? WHERE id = ?");
            $stmt->execute([$email, $id]);
        }
        header("Location: admin.php");
        exit();
    } elseif ($action === 'toggle_user_role') {
        $id = $_POST['id'] ?? '';
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;
        if ($id) {
            $stmt = $pdo->prepare("UPDATE master_users SET is_admin = ? WHERE id = ?");
            $stmt->execute([$is_admin, $id]);
        }
        header("Location: admin.php");
        exit();
    }
}

// Pagination for master_users
$users_per_page = 10;
$current_user_page = isset($_GET['user_page']) ? (int) $_GET['user_page'] : 1;
if ($current_user_page < 1)
    $current_user_page = 1;
$user_offset = ($current_user_page - 1) * $users_per_page;

$total_users_stmt = $pdo->query("SELECT COUNT(*) FROM master_users");
$total_users = (int) $total_users_stmt->fetchColumn();
$total_user_pages = ceil($total_users / $users_per_page);

$stmt = $pdo->prepare("SELECT id, name, email, is_admin FROM master_users ORDER BY name ASC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $users_per_page, PDO::PARAM_INT);
$stmt->bindValue(2, $user_offset, PDO::PARAM_INT);
$stmt->execute();
$master_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM devices WHERE assigned_date LIKE ? ORDER BY id DESC");
$stmt->execute([$view_date_dmy . '%']);
$allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php require_once 'header.php'; ?>


<div class="container-fluid mt-4" style="padding-left: 50px; padding-right: 50px;">
    <h1 class="admin-title">Employees Management</h1>

    <div class="row">
        <!-- MASTER USERS MANAGEMENT -->
        <div class="col-12" id="employees-section">
            <div class="glass-card-admin">
                <div class="card-header-admin d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fa-solid fa-users text-violet-400" style="color: var(--accent-violet);"></i> Registered Employees
                    </div>
                    <input type="text" id="searchEmployees" class="form-control-admin form-control-sm w-auto" placeholder="Search Employees..." onkeyup="filterTable('searchEmployees', 'employeesTable')" style="font-size: 0.85rem; padding: 4px 10px; font-weight: normal;">
                </div>
                <div class="border-bottom border-secondary"
                    style="padding: 30px !important; border-color: var(--dark-border) !important;">
                    <form action="employees.php" method="POST" class="d-flex flex-column gap-3">
                        <input type="hidden" name="action" value="add_master_user">
                        <div class="d-flex gap-3">
                            <input type="text" name="item_name" class="form-control-admin w-100"
                                placeholder="e.g. John Doe (Default Pass: 123456)" required>
                            <input type="email" name="email" class="form-control-admin w-100"
                                placeholder="Email Address" required>
                            <button type="submit" class="btn-action btn-action-primary px-4"><i
                                    class="fa-solid fa-plus"></i></button>
                        </div>
                    </form>
                </div>
                <div class="table-responsive" style="min-height: 600px; padding: 15px;">
                    <table class="table table-admin align-middle mb-0" id="employeesTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th class="text-center">Role</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($master_users as $usr): ?>
                                <tr>
                                    <td class="fw-bold text-adaptive" style="width: 30%;">
                                        <?php echo htmlspecialchars($usr['name']); ?>
                                    </td>
                                    <td style="width: 40%;">
                                        <form action="employees.php" method="POST" class="m-0 d-flex gap-2">
                                            <input type="hidden" name="action" value="update_master_user_email">
                                            <input type="hidden" name="id" value="<?php echo $usr['id']; ?>">
                                            <input type="email" name="email" class="form-control-admin w-100"
                                                style="font-size: 0.8rem; padding: 4px 8px;" placeholder="Email"
                                                value="<?php echo htmlspecialchars($usr['email'] ?? ''); ?>"
                                                onchange="this.form.submit()" title="Changes are saved automatically">
                                        </form>
                                    </td>
                                    <td class="text-center">
                                        <form action="employees.php" method="POST" class="m-0 d-inline-block">
                                            <input type="hidden" name="action" value="toggle_user_role">
                                            <input type="hidden" name="id" value="<?php echo $usr['id']; ?>">
                                            <div class="form-check form-switch m-0 p-0 d-flex align-items-center justify-content-center" style="gap: 5px;">
                                                <input class="form-check-input m-0 shadow-none" type="checkbox" name="is_admin" value="1" 
                                                    onchange="this.form.submit()" <?php echo $usr['is_admin'] ? 'checked' : ''; ?> title="Toggle Admin Role">
                                                <label class="form-check-label small <?php echo $usr['is_admin'] ? 'text-success fw-bold' : 'text-muted'; ?>">
                                                    <?php echo $usr['is_admin'] ? 'Admin' : 'Employee'; ?>
                                                </label>
                                            </div>
                                        </form>
                                    </td>
                                    <td class="text-center" style="width: 30%;">
                                        <div class="d-flex justify-content-center gap-2">
                                            <form action="employees.php" method="POST" class="m-0"
                                                onsubmit="return confirm('Reset password to 123456?');">
                                                <input type="hidden" name="action" value="reset_master_user_password">
                                                <input type="hidden" name="id" value="<?php echo $usr['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-warning"
                                                    title="Reset Password">
                                                    <i class="fa-solid fa-key"></i> Reset
                                                </button>
                                            </form>
                                            <form action="employees.php" method="POST" class="m-0"
                                                onsubmit="return confirm('Remove user?');">
                                                <input type="hidden" name="action" value="delete_master_user">
                                                <input type="hidden" name="id" value="<?php echo $usr['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Controls for Users -->
                <?php if ($total_user_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center mt-3 px-4 pb-4">
                        <div class="text-muted small">
                            Showing page <?php echo $current_user_page; ?> of <?php echo $total_user_pages; ?>
                        </div>
                        <div class="btn-group">
                            <?php if ($current_user_page > 1): ?>
                                <a href="?user_page=<?php echo $current_user_page - 1; ?>"
                                    class="btn btn-sm btn-outline-secondary">Previous</a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary" disabled>Previous</button>
                            <?php endif; ?>

                            <?php if ($current_user_page < $total_user_pages): ?>
                                <a href="?user_page=<?php echo $current_user_page + 1; ?>"
                                    class="btn btn-sm btn-outline-secondary">Next</a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary" disabled>Next</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>


<script>
    let currentAdminSort = { column: -1, asc: true };
    function sortAdminTable(columnIndex) {
        const table = document.getElementById('permanentTable');
        const tbody = table.getElementsByTagName('tbody')[0];
        const rows = Array.from(tbody.getElementsByTagName('tr'));

        if (rows.length === 0) return;

        const isAsc = currentAdminSort.column === columnIndex ? !currentAdminSort.asc : true;
        currentAdminSort = { column: columnIndex, asc: isAsc };

        rows.sort((a, b) => {
            let valA = '';
            let valB = '';

            if (columnIndex === 0) {
                // First td contains the device name
                valA = a.getElementsByTagName('td')[0].innerText.trim().toLowerCase();
                valB = b.getElementsByTagName('td')[0].innerText.trim().toLowerCase();
            } else if (columnIndex === 1) {
                // Second td contains the checkbox
                const cbA = a.querySelector('input[type="checkbox"]');
                const cbB = b.querySelector('input[type="checkbox"]');
                valA = cbA && cbA.checked ? 1 : 0;
                valB = cbB && cbB.checked ? 1 : 0;
            }

            if (valA < valB) return isAsc ? -1 : 1;
            if (valA > valB) return isAsc ? 1 : -1;
            return 0;
        });

        rows.forEach(row => tbody.appendChild(row));
    }



    // Simple table filter
    function filterTable(inputId, tableId) {
        let input = document.getElementById(inputId);
        let filter = input.value.toLowerCase();
        let table = document.getElementById(tableId);
        let tr = table.getElementsByTagName("tr");
        
        for (let i = 1; i < tr.length; i++) {
            let rowText = tr[i].textContent || tr[i].innerText;
            if (rowText.toLowerCase().indexOf(filter) > -1) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }

    // Preserve scroll position across page reloads
    document.addEventListener("DOMContentLoaded", function(event) { 
        var scrollpos = localStorage.getItem('scrollpos');
        if (scrollpos) window.scrollTo(0, scrollpos);
    });

    window.onbeforeunload = function(e) {
        localStorage.setItem('scrollpos', window.scrollY);
    };
</script>

<?php require_once 'footer.php'; ?>