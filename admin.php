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

    if ($action === 'save_permanent_config') {
        $device_id = $_POST['device_id'] ?? '';
        $is_perm = isset($_POST['is_permanent']) ? 1 : 0;
        $assigned_user = $is_perm ? ($_POST['permanent_user'] ?? null) : null;
        $sim_no = $_POST['sim_no'] ?? 'No SIM';
        if (empty($sim_no))
            $sim_no = 'No SIM';

        $stmt = $pdo->prepare("UPDATE master_devices SET is_permanent = ?, permanent_user = ?, sim_no = ? WHERE id = ?");
        $stmt->execute([$is_perm, $assigned_user, $sim_no, $device_id]);

        $stmt2 = $pdo->prepare("SELECT name FROM master_devices WHERE id = ?");
        $stmt2->execute([$device_id]);
        $master_dev = $stmt2->fetch(PDO::FETCH_ASSOC);

        if ($master_dev && !$is_perm) {
            $stmt3 = $pdo->prepare("UPDATE devices SET status = 'Issued' WHERE LOWER(device_name) = LOWER(?) AND assigned_date LIKE ?");
            $stmt3->execute([$master_dev['name'], $today_date . '%']);
        }

        auto_log_permanent_devices($pdo);
        header("Location: admin.php");
        exit();
    } elseif ($action === 'update_device') {
        $id = $_POST['id'] ?? '';
        $user_name = $_POST['user_name'] ?? '';
        $sim_no = $_POST['sim_no'] ?? '';
        $status = $_POST['status'] ?? '';
        $now_time = date('d-m-Y h:i A');

        $stmt = $pdo->prepare("SELECT device_name, device_type FROM devices WHERE id = ?");
        $stmt->execute([$id]);
        $dev_info = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt2 = $pdo->prepare("SELECT name FROM master_users WHERE LOWER(name) = LOWER(?)");
        $stmt2->execute([$user_name]);
        $valid_user = $stmt2->fetch(PDO::FETCH_ASSOC);

        if ($valid_user && $dev_info) {
            $update_stmt = $pdo->prepare("UPDATE devices SET user_name = ?, sim_no = ?, status = ?, assigned_date = ? WHERE id = ?");
            $update_stmt->execute([$valid_user['name'], $sim_no, $status, $now_time, $id]);

            log_daily_history(
                'UPDATED_USER',
                $dev_info['device_name'],
                $dev_info['device_type'],
                $valid_user['name'],
                $sim_no !== 'No SIM' ? $sim_no : '',
                $status
            );
        }
        header("Location: admin.php");
        exit();
    } elseif ($action === 'delete_device_log') {
        $id = $_POST['id'] ?? '';
        $stmt = $pdo->prepare("SELECT device_name, device_type, user_name, sim_no FROM devices WHERE id = ?");
        $stmt->execute([$id]);
        $deleted_row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($deleted_row) {
            $del_stmt = $pdo->prepare("DELETE FROM devices WHERE id = ?");
            $del_stmt->execute([$id]);

            log_daily_history(
                'DELETED',
                $deleted_row['device_name'],
                $deleted_row['device_type'],
                $deleted_row['user_name'],
                $deleted_row['sim_no'],
                'Removed'
            );
        }
        header("Location: admin.php");
        exit();
    } elseif ($action === 'add_master_device') {
        $item_name = trim($_POST['item_name'] ?? '');
        if ($item_name) {
            $stmt = $pdo->prepare("INSERT OR IGNORE INTO master_devices (name) VALUES (?)");
            $stmt->execute([$item_name]);
        }
        header("Location: admin.php");
        exit();
    } elseif ($action === 'delete_master_device') {
        $id = $_POST['id'] ?? '';
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM master_devices WHERE id = ?");
            $stmt->execute([$id]);
        }
        header("Location: admin.php");
        exit();
    } elseif ($action === 'add_master_user') {
        $item_name = trim($_POST['item_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($item_name) {
            $defaultHash = password_hash('123456', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT IGNORE INTO master_users (name, password, email) VALUES (?, ?, ?)");
            $stmt->execute([$item_name, $defaultHash, $email]);
        }
        header("Location: admin.php");
        exit();
    } elseif ($action === 'delete_master_user') {
        $id = $_POST['id'] ?? '';
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM master_users WHERE id = ?");
            $stmt->execute([$id]);
        }
        header("Location: admin.php");
        exit();
    } elseif ($action === 'reset_master_user_password') {
        $id = $_POST['id'] ?? '';
        if ($id) {
            $defaultHash = password_hash('123456', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE master_users SET password = ? WHERE id = ?");
            $stmt->execute([$defaultHash, $id]);
        }
        header("Location: admin.php");
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
    }
}

// Fetch data for view
$stmt = $pdo->query("SELECT id, name, is_permanent, permanent_user, sim_no FROM master_devices ORDER BY name ASC");
$master_devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pagination for master_users
$users_per_page = 10;
$current_user_page = isset($_GET['user_page']) ? (int) $_GET['user_page'] : 1;
if ($current_user_page < 1)
    $current_user_page = 1;
$user_offset = ($current_user_page - 1) * $users_per_page;

$total_users_stmt = $pdo->query("SELECT COUNT(*) FROM master_users");
$total_users = (int) $total_users_stmt->fetchColumn();
$total_user_pages = ceil($total_users / $users_per_page);

$stmt = $pdo->prepare("SELECT id, name, email FROM master_users ORDER BY name ASC LIMIT ? OFFSET ?");
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
    <h1 class="admin-title">Admin Control Panel</h1>

    <!-- 1. PERMANENT DEVICE MAPPING CONFIGURATION -->
    <div class="glass-card-admin">
        <div class="card-header-admin text-warning"><i class="fa-solid fa-lock text-warning"></i> Permanent Device
            Allocation Settings</div>
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0" id="permanentTable">
                <thead>
                    <tr>
                        <th class="ps-4" style="cursor: pointer;" onclick="sortAdminTable(0)"
                            title="Sort by Device Name">Device Name <i class="fa-solid fa-sort ms-1"
                                style="opacity: 0.5;"></i></th>
                        <th style="cursor: pointer;" onclick="sortAdminTable(1)" title="Sort by Permanent Status">
                            Permanent? <i class="fa-solid fa-sort ms-1" style="opacity: 0.5;"></i></th>
                        <th>Assigned User</th>
                        <th>Default SIM</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($master_devices as $dev): ?>
                        <tr>
                            <form action="admin.php" method="POST">
                                <input type="hidden" name="action" value="save_permanent_config">
                                <input type="hidden" name="device_id" value="<?php echo $dev['id']; ?>">
                                <td class="ps-4 fw-bold text-adaptive"><?php echo htmlspecialchars($dev['name']); ?></td>
                                <td>
                                    <div class="form-check form-switch ms-2">
                                        <input class="form-check-input shadow-none" type="checkbox" name="is_permanent"
                                            <?php echo $dev['is_permanent'] == 1 ? 'checked' : ''; ?>>
                                    </div>
                                </td>
                                <td>
                                    <select name="permanent_user" class="form-select-admin" style="width: 200px;">
                                        <option value="">-- Select User --</option>
                                        <?php foreach ($master_users as $usr): ?>
                                            <option value="<?php echo htmlspecialchars($usr['name']); ?>" <?php echo $dev['permanent_user'] == $usr['name'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($usr['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="sim_no" class="form-control-admin" style="width: 150px;"
                                        value="<?php echo htmlspecialchars($dev['sim_no']); ?>" placeholder="SIM Details">
                                </td>
                                <td class="text-end pe-4">
                                    <button type="submit" class="btn-action btn-action-success"><i
                                            class="fa-solid fa-save me-1"></i> Save</button>
                                </td>
                            </form>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row">
        <!-- 2. MASTER DEVICES MANAGEMENT -->
        <div class="col-lg-6">
            <div class="glass-card-admin">
                <div class="card-header-admin"><i class="fa-solid fa-mobile text-cyan-400"
                        style="color: var(--accent-cyan);"></i> Master Devices List</div>
                <div class="border-bottom border-secondary"
                    style="padding: 30px !important; border-color: var(--dark-border) !important;">
                    <form action="admin.php" method="POST" class="d-flex gap-3">
                        <input type="hidden" name="action" value="add_master_device">
                        <input type="text" name="item_name" class="form-control-admin w-100"
                            placeholder="e.g. Android S23 Ultra" required>
                        <button type="submit" class="btn-action btn-action-primary px-4"><i
                                class="fa-solid fa-plus"></i></button>
                    </form>
                </div>
                <div class="d-flex flex-wrap" style="padding: 30px !important; gap: 15px;">
                    <?php foreach ($master_devices as $dev): ?>
                        <div class="master-badge-item">
                            <?php echo htmlspecialchars($dev['name']); ?>
                            <form action="admin.php" method="POST" class="m-0" onsubmit="return confirm('Remove device?');">
                                <input type="hidden" name="action" value="delete_master_device">
                                <input type="hidden" name="id" value="<?php echo $dev['id']; ?>">
                                <button type="submit" class="btn-remove" title="Remove"><i
                                        class="fa-solid fa-xmark"></i></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 3. MASTER USERS MANAGEMENT -->
        <div class="col-lg-6" id="employees-section">
            <div class="glass-card-admin">
                <div class="card-header-admin"><i class="fa-solid fa-users text-violet-400"
                        style="color: var(--accent-violet);"></i> Registered Employees</div>
                <div class="border-bottom border-secondary"
                    style="padding: 30px !important; border-color: var(--dark-border) !important;">
                    <form action="admin.php" method="POST" class="d-flex flex-column gap-3">
                        <input type="hidden" name="action" value="add_master_user">
                        <div class="d-flex gap-3">
                            <input type="text" name="item_name" class="form-control-admin w-100"
                                placeholder="e.g. John Doe (Default Pass: 123456)" required>
                            <input type="email" name="email" class="form-control-admin w-100"
                                placeholder="Email Address (Optional)">
                            <button type="submit" class="btn-action btn-action-primary px-4"><i
                                    class="fa-solid fa-plus"></i></button>
                        </div>
                    </form>
                </div>
                <div class="table-responsive" style="padding: 15px;">
                    <table class="table table-admin align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
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
                                        <form action="admin.php" method="POST" class="m-0 d-flex gap-2"
                                            onsubmit="return confirm('Update email?');">
                                            <input type="hidden" name="action" value="update_master_user_email">
                                            <input type="hidden" name="id" value="<?php echo $usr['id']; ?>">
                                            <input type="email" name="email" class="form-control-admin"
                                                style="font-size: 0.8rem; padding: 4px 8px;" placeholder="Email"
                                                value="<?php echo htmlspecialchars($usr['email'] ?? ''); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Save Email">
                                                <i class="fa-solid fa-save"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="text-center" style="width: 30%;">
                                        <div class="d-flex justify-content-center gap-2">
                                            <form action="admin.php" method="POST" class="m-0"
                                                onsubmit="return confirm('Reset password to 123456?');">
                                                <input type="hidden" name="action" value="reset_master_user_password">
                                                <input type="hidden" name="id" value="<?php echo $usr['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-warning"
                                                    title="Reset Password">
                                                    <i class="fa-solid fa-key"></i> Reset
                                                </button>
                                            </form>
                                            <form action="admin.php" method="POST" class="m-0"
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
                                <a href="?user_page=<?php echo $current_user_page - 1; ?>&view_date=<?php echo htmlspecialchars($view_date); ?>#employees-section"
                                    class="btn btn-sm btn-outline-secondary">Previous</a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary" disabled>Previous</button>
                            <?php endif; ?>

                            <?php if ($current_user_page < $total_user_pages): ?>
                                <a href="?user_page=<?php echo $current_user_page + 1; ?>&view_date=<?php echo htmlspecialchars($view_date); ?>#employees-section"
                                    class="btn btn-sm btn-outline-secondary">Next</a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary" disabled>Next</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 4. EDIT LOGS (WITH DATE FILTER) -->
    <div class="glass-card-admin">
        <div class="card-header-admin text-light d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <i class="fa-solid fa-pen-to-square text-danger"></i> Edit Logs (Emergency)
            </div>
            <div class="d-flex flex-row align-items-center gap-3">
                <form action="admin.php" method="GET" class="d-flex m-0 align-items-center rounded px-3 py-1"
                    style="background: var(--input-bg-dark); border: 1px solid var(--dark-border);">
                    <input type="date" name="log_date" value="<?php echo htmlspecialchars($view_date_ymd); ?>"
                        onchange="this.form.submit()" class="form-control shadow-none bg-transparent border-0 p-0 m-0"
                        style="width: 140px; font-size: 0.95rem; color: var(--input-text);">
                </form>
                <a href="export_excel.php?date=<?php echo urlencode($view_date_ymd); ?>"
                    class="btn-action btn-action-success text-decoration-none d-flex align-items-center px-3 py-2"
                    style="white-space: nowrap;">
                    <i class="fa-solid fa-file-excel me-2"></i> Export
                </a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-admin align-middle mb-0" id="logsTable">
                <thead>
                    <tr>
                        <th class="ps-4" style="cursor: pointer;" onclick="sortLogsTable(0)" title="Sort by Time">Time
                            <i class="fa-solid fa-sort ms-1" style="opacity: 0.5;"></i>
                        </th>
                        <th>Device Name</th>
                        <th style="cursor: pointer;" onclick="sortLogsTable(2)" title="Sort by Assigned To">Assigned To
                            <i class="fa-solid fa-sort ms-1" style="opacity: 0.5;"></i>
                        </th>
                        <th>SIM</th>
                        <th style="cursor: pointer;" onclick="sortLogsTable(4)" title="Sort by Status">Status <i
                                class="fa-solid fa-sort ms-1" style="opacity: 0.5;"></i></th>
                        <th class="text-center" colspan="2" style="width: 130px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allocations as $row): ?>
                        <tr>
                            <form action="admin.php" method="POST" class="m-0">
                                <input type="hidden" name="action" value="update_device">
                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                <td class="ps-4 text-adaptive" style="font-size: 0.85rem; font-weight: 500;">
                                    <?php echo date('h:i A', strtotime($row['assigned_date'])); ?>
                                </td>
                                <td class="fw-bold text-adaptive"><?php echo htmlspecialchars($row['device_name']); ?></td>
                                <td>
                                    <select name="user_name" class="form-select-admin" style="width: 160px;">
                                        <?php foreach ($master_users as $usr): ?>
                                            <option value="<?php echo htmlspecialchars($usr['name']); ?>" <?php echo $row['user_name'] == $usr['name'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($usr['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="text" name="sim_no" class="form-control-admin" style="width: 130px;"
                                        value="<?php echo htmlspecialchars($row['sim_no']); ?>"></td>
                                <td>
                                    <select name="status" class="form-select-admin" style="width: 130px;">
                                        <option value="Issued" <?php echo $row['status'] == 'Issued' ? 'selected' : ''; ?>>
                                            Issued</option>
                                        <option value="Returned" <?php echo $row['status'] == 'Returned' ? 'selected' : ''; ?>>Returned</option>
                                        <option value="Permanent" <?php echo $row['status'] == 'Permanent' ? 'selected' : ''; ?>>Permanent</option>
                                    </select>
                                </td>
                                <td class="text-end" style="width: 60px; padding-right: 8px !important;">
                                    <button type="submit" class="btn-action btn-action-success" title="Save Changes"><i
                                            class="fa-solid fa-save"></i></button>
                                </td>
                            </form>
                            <form action="admin.php" method="POST" class="m-0"
                                onsubmit="return confirm('Are you sure you want to delete this log entry permanently?');">
                                <input type="hidden" name="action" value="delete_device_log">
                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                <td class="text-start"
                                    style="width: 60px; padding-left: 0 !important; padding-right: 20px !important;">
                                    <button type="submit" class="btn-icon-danger" title="Delete Log"><i
                                            class="fa-solid fa-trash-can"></i></button>
                                </td>
                            </form>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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

    let currentLogsSort = { column: -1, asc: true };
    function sortLogsTable(columnIndex) {
        const table = document.getElementById('logsTable');
        if (!table) return;
        const tbody = table.getElementsByTagName('tbody')[0];
        const rows = Array.from(tbody.getElementsByTagName('tr'));

        if (rows.length === 0) return;

        const isAsc = currentLogsSort.column === columnIndex ? !currentLogsSort.asc : true;
        currentLogsSort = { column: columnIndex, asc: isAsc };

        rows.sort((a, b) => {
            let valA = '';
            let valB = '';

            const tdA = a.getElementsByTagName('td');
            const tdB = b.getElementsByTagName('td');

            if (columnIndex === 0) {
                // Time
                valA = tdA[0] ? tdA[0].innerText.trim().toLowerCase() : '';
                valB = tdB[0] ? tdB[0].innerText.trim().toLowerCase() : '';
            } else if (columnIndex === 2) {
                // Assigned To
                const selectA = tdA[2] ? tdA[2].querySelector('select') : null;
                const selectB = tdB[2] ? tdB[2].querySelector('select') : null;
                valA = selectA ? selectA.value.trim().toLowerCase() : '';
                valB = selectB ? selectB.value.trim().toLowerCase() : '';
            } else if (columnIndex === 4) {
                // Status
                const selectA = tdA[4] ? tdA[4].querySelector('select') : null;
                const selectB = tdB[4] ? tdB[4].querySelector('select') : null;
                valA = selectA ? selectA.value.trim().toLowerCase() : '';
                valB = selectB ? selectB.value.trim().toLowerCase() : '';
            }

            if (valA < valB) return isAsc ? -1 : 1;
            if (valA > valB) return isAsc ? 1 : -1;
            return 0;
        });

        rows.forEach(row => tbody.appendChild(row));
    }
</script>

<?php require_once 'footer.php'; ?>