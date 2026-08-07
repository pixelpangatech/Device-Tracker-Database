<?php
require_once __DIR__ . '/db.php';

if (!empty($_SESSION['must_change_password'])) {
    header("Location: change_password.php");
    exit();
}

// Handle POST request for new allocation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

// Fetch today's devices
$stmt = $pdo->prepare("SELECT d.id, d.device_name, d.device_type, d.user_name, d.sim_no, d.assigned_date, d.status, COALESCE(m.is_permanent, 0) AS is_permanent 
    FROM devices d
    LEFT JOIN master_devices m ON LOWER(d.device_name) = LOWER(m.name)
    WHERE d.assigned_date LIKE ?
    ORDER BY d.id DESC");
$stmt->execute([$today_date . '%']);
$today_devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch preset devices
$stmt = $pdo->query("SELECT name FROM master_devices ORDER BY name ASC");
$preset_devices = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch preset users
$stmt = $pdo->query("SELECT name FROM master_users ORDER BY name ASC");
$preset_users = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch assigned device names
$stmt = $pdo->prepare("SELECT LOWER(device_name) FROM devices WHERE assigned_date LIKE ? AND status IN ('Issued', 'Permanent')");
$stmt->execute([$today_date . '%']);
$assigned_device_names = $stmt->fetchAll(PDO::FETCH_COLUMN);
$assigned_device_names = array_unique($assigned_device_names);

$issued_devices = [];
foreach ($today_devices as $device) {
    if (in_array(strtolower($device['status']), ['issued', 'permanent'])) {
        $issued_devices[] = strtolower(trim($device['device_name']));
    }
}
?>
<?php require_once 'header.php'; ?>


<div class="container-fluid" style="padding-left: 50px; padding-right: 50px;">
    <div class="mb-4 pt-2">
        <h1 class="header-title">Live Device Dashboard</h1>
        <p class="header-sub mt-2">Monitor daily allocations and device availability across the testing floor.</p>
    </div>

    <!-- STATS GRID -->
    <div class="stats-grid">
        <div class="stat-card glass-card total">
            <i class="fa-solid fa-server stat-icon"></i>
            <div class="stat-title">Total Active Devices</div>
            <div class="stat-number" id="stat-total">0</div>
        </div>
        <div class="stat-card glass-card android">
            <i class="fa-brands fa-android stat-icon"></i>
            <div class="stat-title">Android Devices</div>
            <div class="stat-number" id="stat-android-total">0</div>
            <div class="stat-pills">
                <div class="pill-item pill-allocated" id="stat-android-issued">Allocated: 0</div>
                <div class="pill-item pill-free" id="stat-android-free">Free: 0</div>
            </div>
        </div>
        <div class="stat-card glass-card iphone">
            <i class="fa-brands fa-apple stat-icon"></i>
            <div class="stat-title">iOS Devices</div>
            <div class="stat-number" id="stat-iphone-total">0</div>
            <div class="stat-pills">
                <div class="pill-item pill-allocated" id="stat-iphone-issued">Allocated: 0</div>
                <div class="pill-item pill-free" id="stat-iphone-free">Free: 0</div>
            </div>
        </div>
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
                        <label class="form-label-custom"><i class="fa-solid fa-layer-group me-1"></i> Platform</label>
                        <select name="device_type" id="typeSelect" class="form-select-custom" required>
                            <option value="Android">Android</option>
                            <option value="iPhone">iPhone</option>
                        </select>
                    </div>
                    <div class="form-col-cell">
                        <label class="form-label-custom"><i class="fa-solid fa-mobile-screen me-1"></i> Select
                            Device</label>
                        <input type="text" name="device_name" id="deviceInput" class="form-control-custom"
                            placeholder="Search device..." list="deviceList" required autocomplete="off">
                        <datalist id="deviceList"></datalist>
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
                        <input type="text" name="sim_no" class="form-control-custom" placeholder="e.g. Jio 5G, Airtel..."
                            autocomplete="off">
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
                    <option value="All">All Types</option>
                    <option value="Android">Android</option>
                    <option value="iPhone">iPhone</option>
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
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($today_devices) > 0): ?>
                        <?php foreach ($today_devices as $row): ?>
                            <tr>
                                <td class="fw-bold">
                                    <?php if (strtolower($row['device_type']) == 'iphone'): ?>
                                        <i class="fa-brands fa-apple me-2" style="color: #e2e8f0; font-size: 1.1em;"></i>
                                    <?php else: ?>
                                        <i class="fa-brands fa-android me-2" style="color: #22c55e; font-size: 1.1em;"></i>
                                    <?php endif; ?>
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
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
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
    const masterDevices = <?php echo json_encode($preset_devices); ?>;
    const issuedDevices = <?php echo json_encode($issued_devices); ?>;

    function filterDeviceDropdown() {
        const typeSelect = document.getElementById('typeSelect');
        const dataList = document.getElementById('deviceList');
        const input = document.getElementById('deviceInput');

        if (!typeSelect || !dataList || !input) return;

        const type = typeSelect.value.toLowerCase();
        dataList.innerHTML = '';
        input.value = '';

        let availableCount = 0;
        masterDevices.forEach(device => {
            const lowerDevice = device.toLowerCase();
            const isIphone = lowerDevice.includes('iphone') || lowerDevice.includes('ipad') || lowerDevice.includes('apple') || lowerDevice.includes('mac');

            if ((type === 'iphone' && isIphone) || (type === 'android' && !isIphone)) {
                if (!issuedDevices.includes(lowerDevice)) {
                    availableCount++;
                    const option = document.createElement('option');
                    option.value = device;
                    dataList.appendChild(option);
                }
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

    function calculateStats() {
        let androidMaster = 0, iphoneMaster = 0;
        let androidIssued = 0, iphoneIssued = 0;

        masterDevices.forEach(d => {
            const lower = d.toLowerCase();
            const isIphone = lower.includes('iphone') || lower.includes('ipad') || lower.includes('apple') || lower.includes('mac') || lower.includes('ios');
            if (isIphone) iphoneMaster++;
            else androidMaster++;
        });

        issuedDevices.forEach(d => {
            const lower = d.toLowerCase();
            const isIphone = lower.includes('iphone') || lower.includes('ipad') || lower.includes('apple') || lower.includes('mac') || lower.includes('ios');
            if (isIphone) iphoneIssued++;
            else androidIssued++;
        });

        const totalMaster = masterDevices.length;
        const totalIssued = issuedDevices.length;

        // Show the total number of currently active (issued) devices
        document.getElementById('stat-total').innerText = totalIssued;

        document.getElementById('stat-android-total').innerText = androidMaster;
        document.getElementById('stat-android-issued').innerText = `Allocated: ${androidIssued}`;
        document.getElementById('stat-android-free').innerText = `Free: ${Math.max(0, androidMaster - androidIssued)}`;

        document.getElementById('stat-iphone-total').innerText = iphoneMaster;
        document.getElementById('stat-iphone-issued').innerText = `Allocated: ${iphoneIssued}`;
        document.getElementById('stat-iphone-free').innerText = `Free: ${Math.max(0, iphoneMaster - iphoneIssued)}`;
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
        calculateStats();
    });
</script>
<?php require_once 'footer.php'; ?>