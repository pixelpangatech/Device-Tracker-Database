<?php
require_once __DIR__ . '/db.php';

if (!empty($_SESSION['must_change_password'])) {
    header("Location: change_password.php");
    exit();
}

// Handle POST request for new allocation or deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete_device_log' && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $id = $_POST['id'] ?? '';
        $stmt = $pdo->prepare("SELECT device_name, device_type, user_name, sim_no FROM devices WHERE id = ?");
        $stmt->execute([$id]);
        $deleted_row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($deleted_row) {
            $del_stmt = $pdo->prepare("DELETE FROM devices WHERE id = ?");
            $del_stmt->execute([$id]);

            log_daily_history(
                'DELETED_ENTRY',
                $deleted_row['device_name'],
                $deleted_row['device_type'],
                $deleted_row['user_name'],
                $deleted_row['sim_no'] !== 'No SIM' ? $deleted_row['sim_no'] : '',
                'Deleted'
            );
        }
        header("Location: index.php");
        exit();
    }

    $dev_type = $_POST['device_type'] ?? '';
    $dev_name = $_POST['device_name'] ?? '';
    $user_name = $_POST['user_name'] ?? '';
    $sim_no = $_POST['sim_no'] ?? '';
    if (empty($sim_no))
        $sim_no = "No SIM";

    $today_date = date('d-m-Y');
    $now_time = date('d-m-Y h:i A');
    $user_ip = get_client_ip();

    // Validation
    $stmt = $pdo->prepare("SELECT name FROM master_devices WHERE LOWER(name) = LOWER(?)");
    $stmt->execute([$dev_name]);
    $valid_device = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt2 = $pdo->prepare("SELECT name FROM master_users WHERE LOWER(name) = LOWER(?)");
    $stmt2->execute([$user_name]);
    $valid_user = $stmt2->fetch(PDO::FETCH_ASSOC);

    if ($valid_device && $valid_user) {
        $exact_dev_name = $valid_device['name'];
        $exact_user_name = $valid_user['name'];

        // Automatic Submitted mark purani active entry
        $update_stmt = $pdo->prepare("UPDATE devices SET status = 'Submitted' WHERE LOWER(device_name) = LOWER(?) AND assigned_date LIKE ? AND status IN ('Issued', 'Permanent')");
        $update_stmt->execute([$exact_dev_name, $today_date . '%']);

        // Nayi entry insert karein
        $insert_stmt = $pdo->prepare("INSERT INTO devices (device_name, device_type, user_name, sim_no, assigned_date, status, system_ip) VALUES (?, ?, ?, ?, ?, 'Issued', ?)");
        $insert_stmt->execute([$exact_dev_name, $dev_type, $exact_user_name, $sim_no, $now_time, $user_ip]);

        // Update last_assigned tracking in master_devices
        $master_upd_stmt = $pdo->prepare("UPDATE master_devices SET last_assigned_to = ?, last_assigned_date = ? WHERE LOWER(name) = LOWER(?)");
        $master_upd_stmt->execute([$exact_user_name, $now_time, $exact_dev_name]);

        // Dynamic Daily Backup File Writer Trigger
        log_daily_history(
            'NEW_ENTRY',
            $exact_dev_name,
            $dev_type,
            $exact_user_name,
            $sim_no !== 'No SIM' ? $sim_no : '',
            'Issued'
        );
    }
    header("Location: index.php");
    exit();
}

auto_log_permanent_devices($pdo);

$today_date = date('d-m-Y');

// Fetch today's devices (or permanent devices which are active today)
$stmt = $pdo->prepare("
    SELECT d.id, d.device_name, d.device_type, d.user_name, d.sim_no, d.assigned_date, d.status, COALESCE(m.is_permanent, 0) AS is_permanent 
    FROM devices d
    LEFT JOIN master_devices m ON LOWER(d.device_name) = LOWER(m.name)
    WHERE d.assigned_date LIKE ? 
    ORDER BY d.id DESC
");
$stmt->execute([$today_date . '%']);
$today_devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch preset data for dropdowns
$stmt = $pdo->query("SELECT name, category FROM master_devices ORDER BY name ASC");
$preset_devices_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

$preset_categories = [];
foreach ($preset_devices_raw as $pd) {
    if (!empty($pd['category']) && !in_array($pd['category'], $preset_categories)) {
        $preset_categories[] = $pd['category'];
    }
}
if (empty($preset_categories)) {
    $preset_categories = ['Phone', 'Laptop', 'External HD', 'Headphone', 'Other'];
}

$stmt = $pdo->query("SELECT name FROM master_users ORDER BY name ASC");
$preset_users = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->query("SELECT sim_number FROM master_sims ORDER BY sim_number ASC");
$preset_sims = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch only currently issued devices to accurately show Free/Allocatedst data (for stats calculation)
$issued_devices = [];
foreach ($today_devices as $device) {
    if (in_array(strtolower($device['status']), ['issued', 'permanent'])) {
        $issued_devices[] = strtolower(trim($device['device_name']));
    }
}
$issued_devices = array_values(array_unique($issued_devices));

// Calculate Stats dynamically based on category
$stats = [];
foreach ($preset_categories as $cat) {
    $stats[$cat] = ['total' => 0, 'issued' => 0];
}
foreach ($preset_devices_raw as $pd) {
    $cat = $pd['category'] ?: 'Phone';
    if (!isset($stats[$cat])) {
        $stats[$cat] = ['total' => 0, 'issued' => 0];
    }
    $stats[$cat]['total']++;
}
foreach ($issued_devices as $issued_name) {
    foreach ($preset_devices_raw as $pd) {
        if (strtolower($pd['name']) === $issued_name) {
            $cat = $pd['category'] ?: 'Phone';
            $stats[$cat]['issued']++;
            break;
        }
    }
}
?>
<?php require_once 'header.php'; ?>


<div class="container-fluid" style="padding-left: 50px; padding-right: 50px;">
    <div class="mb-4 pt-2 d-flex justify-content-between align-items-end">
        <div>
            <h1 class="header-title">Live Device Dashboard</h1>
            <p class="header-sub mt-2 mb-0">Monitor daily allocations and device availability across the testing floor.</p>
        </div>
        <div class="text-end" style="color: rgba(255,255,255,0.7); font-weight: 500; font-size: 1.1rem; background: rgba(255,255,255,0.05); padding: 8px 15px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
            <i class="fa-regular fa-calendar-alt me-2"></i> <?php echo date('d M Y, l'); ?>
        </div>
    </div>

    <!-- STATS GRID -->
    <div class="stats-grid">
        <div class="stat-card glass-card total">
            <i class="fa-solid fa-server stat-icon"></i>
            <div class="stat-title">Total Active Devices</div>
            <div class="stat-number"><?php echo count($issued_devices); ?></div>
        </div>
        <?php foreach ($stats as $cat => $data): ?>
            <?php 
                if ($data['total'] == 0) continue;

                $icon = 'fa-mobile-screen';
                $card_class = 'android'; // default color theme
                $cat_lower = strtolower($cat);
                if (str_contains($cat_lower, 'phone')) { $icon = 'fa-mobile-screen'; $card_class = 'android'; }
                if (str_contains($cat_lower, 'android')) { $icon = 'fa-android'; $card_class = 'android'; }
                if (str_contains($cat_lower, 'iphone') || str_contains($cat_lower, 'ios') || str_contains($cat_lower, 'mac')) { $icon = 'fa-apple'; $card_class = 'iphone'; }
                if (str_contains($cat_lower, 'laptop') || str_contains($cat_lower, 'windows')) { $icon = 'fa-laptop'; $card_class = 'iphone'; }
                if (str_contains($cat_lower, 'hd') || str_contains($cat_lower, 'hard drive')) { $icon = 'fa-hard-drive'; $card_class = 'total'; }
                if (str_contains($cat_lower, 'headphone')) { $icon = 'fa-headphones'; $card_class = 'android'; }
            ?>
            <div class="stat-card glass-card <?php echo $card_class; ?>">
                <i class="fa-solid <?php echo $icon; ?> stat-icon"></i>
                <div class="stat-title"><?php echo htmlspecialchars($cat); ?></div>
                <div class="stat-number"><?php echo $data['total']; ?></div>
                <div class="stat-pills">
                    <div class="pill-item pill-allocated">Allocated: <?php echo $data['issued']; ?></div>
                    <div class="pill-item pill-free">Free: <?php echo max(0, $data['total'] - $data['issued']); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ALLOCATION FORM -->
    <?php if (!empty($_SESSION['role'])): ?>
        <div class="glass-card mb-5">
            <div class="card-header-custom">
                <i class="fa-solid fa-mobile-retro"></i> New Device Allocation
            </div>
            <form action="index.php" method="POST" id="allocationForm">
                <div class="form-grid-4col">
                    <div class="form-col-cell">
                        <label class="form-label-custom"><i class="fa-solid fa-layer-group me-1"></i> Category</label>
                        <select name="device_type" id="typeSelect" class="form-select-custom" required>
                            <?php foreach ($preset_categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-col-cell">
                        <label class="form-label-custom"><i class="fa-solid fa-mobile-screen me-1"></i> Select
                            Device</label>
                        <select name="device_name" id="deviceInput" class="form-select-custom" required>
                            <option value="" disabled selected>Search / Select device...</option>
                        </select>
                    </div>
                    <?php if ($_SESSION['role'] === 'user'): ?>
                        <input type="hidden" name="user_name" value="<?php echo htmlspecialchars($_SESSION['user_name']); ?>">
                    <?php else: ?>
                        <div class="form-col-cell">
                            <label class="form-label-custom"><i class="fa-solid fa-user me-1"></i> Assign To</label>
                            <input type="text" name="user_name" class="form-control-custom" placeholder="Search employee..."
                                list="userList" required autocomplete="off">
                            <datalist id="userList">
                                <?php foreach ($preset_users as $u): ?>
                                    <option value="<?php echo htmlspecialchars($u); ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    <?php endif; ?>
                    <div class="form-col-cell">
                        <label class="form-label-custom"><i class="fa-solid fa-sim-card me-1"></i> Assigned SIM
                            (Optional)</label>
                        <select name="sim_no" class="form-select-custom">
                            <option value="">-- No SIM / Skip --</option>
                            <?php foreach ($preset_sims as $sim): ?>
                                <option value="<?php echo htmlspecialchars($sim); ?>">
                                    <?php echo htmlspecialchars($sim); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-submit-colorful"><i class="fa-solid fa-check-circle me-2"></i> Confirm
                    Allocation</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- TODAY'S ALLOCATIONS TABLE -->
    <div class="glass-card mb-4">
        <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div><i class="fa-solid fa-list-check me-2"></i> Today's Allocations</div>
            <div class="d-flex gap-2 flex-wrap">
                <select id="tableStatusFilter" class="filter-input" style="width: 140px; flex-grow: 1;">
                    <option value="All">All Status</option>
                    <option value="Issued">Issued</option>
                    <option value="Permanent">Permanent</option>
                    <option value="Returned">Returned</option>
                </select>
                <select id="tableTypeFilter" class="filter-input" style="width: 140px; flex-grow: 1;">
                    <option value="All">All Categories</option>
                    <?php foreach ($preset_categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" id="searchInput" class="filter-input" placeholder="Search table..."
                    style="width: 200px; flex-grow: 1;">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-custom w-100" id="allocationTable">
                <thead>
                    <tr>
                        <th style="width: 25%;">Device Name</th>
                        <th style="width: 15%;">Type</th>
                        <th style="width: 20%; cursor: pointer;" onclick="sortTable(2)" title="Sort by Assigned To">
                            Assigned To <i class="fa-solid fa-sort ms-1" style="opacity: 0.5;"></i></th>
                        <th style="width: 15%; cursor: pointer;" onclick="sortTable(3)" title="Sort by SIM Info">SIM
                            Info <i class="fa-solid fa-sort ms-1" style="opacity: 0.5;"></i></th>
                        <th style="width: 15%;">Assigned Time</th>
                        <th style="width: 10%; cursor: pointer;" onclick="sortTable(5)" title="Sort by Status">Status <i
                                class="fa-solid fa-sort ms-1" style="opacity: 0.5;"></i></th>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <th style="width: 5%;" class="text-center">Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($today_devices) > 0): ?>
                        <?php foreach ($today_devices as $row): ?>
                            <tr>
                                <td class="fw-bold">
                                    <?php 
                                        $t = strtolower($row['device_type']);
                                        $ico = 'fa-mobile-screen';
                                        if (str_contains($t, 'android')) $ico = 'fa-android';
                                        elseif (str_contains($t, 'iphone') || str_contains($t, 'ios') || str_contains($t, 'mac')) $ico = 'fa-apple';
                                        elseif (str_contains($t, 'laptop') || str_contains($t, 'windows')) $ico = 'fa-laptop';
                                        elseif (str_contains($t, 'hd') || str_contains($t, 'hard drive')) $ico = 'fa-hard-drive';
                                        elseif (str_contains($t, 'headphone')) $ico = 'fa-headphones';
                                    ?>
                                    <i class="fa-solid <?php echo $ico; ?> me-2" style="color: #e2e8f0; font-size: 1.1em;"></i>
                                    <?php echo htmlspecialchars($row['device_name']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['device_type']); ?></td>
                                <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['sim_no'] ?: 'No SIM'); ?></td>
                                <td><i class="fa-regular fa-clock me-1" style="color: var(--accent-cyan); opacity: 0.8;"></i>
                                    <?= htmlspecialchars(date('h:i A', strtotime($row['assigned_date']))) ?>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = 'badge-returned';
                                    if ($row['status'] == 'Issued')
                                        $statusClass = 'badge-issued';
                                    if ($row['status'] == 'Permanent')
                                        $statusClass = 'badge-permanent';
                                    ?>
                                    <span class="badge-status <?php echo $statusClass; ?>">
                                        <?php echo $row['status'] == 'Permanent' ? '<i class="fa-solid fa-lock me-1"></i> Perm' : htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                    <td class="text-center">
                                        <form action="index.php" method="POST" class="m-0"
                                            onsubmit="return confirm('Are you sure you want to delete this log entry permanently?');">
                                            <input type="hidden" name="action" value="delete_device_log">
                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Log">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? '7' : '6'; ?>" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-inbox fs-1 mb-3 d-block opacity-25"></i>
                                No devices allocated yet today.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const masterDevices = <?php echo json_encode($preset_devices_raw); ?>;
    const issuedDevices = <?php echo json_encode($issued_devices); ?>;

    function filterDeviceDropdown() {
        const typeSelect = document.getElementById('typeSelect');
        const select = document.getElementById('deviceInput');

        if (!typeSelect || !select) return;

        const type = typeSelect.value.toLowerCase();
        
        // Reset dropdown to default option
        select.innerHTML = '<option value="" disabled selected>Search / Select device...</option>';

        masterDevices.forEach(device => {
            const lowerCat = (device.category || 'phone').toLowerCase();
            if (lowerCat === type) {
                const lowerDeviceName = device.name.toLowerCase();
                const isIssued = issuedDevices.includes(lowerDeviceName);
                
                const option = document.createElement('option');
                option.value = device.name;
                
                if (isIssued) {
                    option.innerHTML = '🟡 [Allocated] ' + device.name;
                    option.style.color = '#f39c12';
                    option.style.backgroundColor = 'rgba(0,0,0,0.8)';
                } else {
                    option.innerHTML = '🟢 [Free] ' + device.name;
                    option.style.color = '#2ecc71';
                    option.style.backgroundColor = 'rgba(0,0,0,0.8)';
                }
                
                select.appendChild(option);
            }
        });
    }

    function applyCombinedFilter() {
        const selectedStatus = document.getElementById('tableStatusFilter').value.toLowerCase();
        const selectedType = document.getElementById('tableTypeFilter').value.toLowerCase();
        const searchQuery = document.getElementById('searchInput').value.toLowerCase();
        const table = document.getElementById('allocationTable');
        const tr = table.getElementsByTagName('tr');

        for (let i = 1; i < tr.length; i++) {
            const tds = tr[i].getElementsByTagName('td');
            if (tds.length > 0) {
                const typeCell = tds[1].innerText.toLowerCase();
                const statusCell = tds[5].innerText.toLowerCase(); // Status is the 6th column

                let rowTextContent = "";
                for (let j = 0; j < tds.length; j++) {
                    rowTextContent += tds[j].innerText.toLowerCase() + " ";
                }

                const matchesStatus = (selectedStatus === 'all' || statusCell.includes(selectedStatus));
                const matchesType = (selectedType === 'all' || typeCell === selectedType);
                const matchesSearch = rowTextContent.includes(searchQuery);

                if (matchesStatus && matchesType && matchesSearch) {
                    tr[i].style.display = '';
                } else {
                    tr[i].style.display = 'none';
                }
            }
        }
    }

    let currentSort = { column: -1, asc: true };
    function sortTable(columnIndex) {
        const table = document.getElementById('allocationTable');
        const tbody = table.getElementsByTagName('tbody')[0];
        const rows = Array.from(tbody.getElementsByTagName('tr'));

        if (rows.length === 0 || (rows.length === 1 && rows[0].innerText.includes('No allocations'))) return;

        const isAsc = currentSort.column === columnIndex ? !currentSort.asc : true;
        currentSort = { column: columnIndex, asc: isAsc };

        rows.sort((a, b) => {
            let valA = a.getElementsByTagName('td')[columnIndex].innerText.trim().toLowerCase();
            let valB = b.getElementsByTagName('td')[columnIndex].innerText.trim().toLowerCase();

            if (valA < valB) return isAsc ? -1 : 1;
            if (valA > valB) return isAsc ? 1 : -1;
            return 0;
        });

        rows.forEach(row => tbody.appendChild(row));
    }

    const typeSelect = document.getElementById('typeSelect');
    if (typeSelect) {
        typeSelect.addEventListener('change', filterDeviceDropdown);
    }

    document.getElementById('tableTypeFilter').addEventListener('change', applyCombinedFilter);
    document.getElementById('tableStatusFilter').addEventListener('change', applyCombinedFilter);
    document.getElementById('searchInput').addEventListener('keyup', applyCombinedFilter);

    window.addEventListener('DOMContentLoaded', () => {
        filterDeviceDropdown();
    });
</script>
<?php require_once 'footer.php'; ?>