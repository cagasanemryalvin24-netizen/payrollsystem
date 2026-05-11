<?php
include '../config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header("Location: index.php"); exit; }

$result = $conn->query("
    SELECT e.*, d.department_name 
    FROM employees e 
    JOIN departments d ON e.department_id = d.department_id 
    WHERE e.employee_id = $id
");
$row = $result->fetch_assoc();
if (!$row) { header("Location: index.php"); exit; }

$success = '';
$error   = '';

if (isset($_POST['process_pay'])) {
    $amount     = (float)$_POST['amount'];
    $pay_date   = $conn->real_escape_string(trim($_POST['pay_date']));
    $pay_period = $conn->real_escape_string(trim($_POST['pay_period']));
    $status     = $conn->real_escape_string(trim($_POST['status']));

    if (empty($pay_date) || empty($pay_period) || $amount <= 0) {
        $error = "Please fill in all fields with valid values.";
    } else {
        $stmt = $conn->prepare("INSERT INTO payroll (employee_id, amount_paid, pay_date, pay_period, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("idsss", $id, $amount, $pay_date, $pay_period, $status);

        if ($stmt->execute()) {
            // Verify it was inserted
            $new_id = $conn->insert_id;
            if ($new_id > 0) {
                header("Location: index.php?paid=1");
                exit;
            } else {
                $error = "Insert appeared to succeed but no record was created. Please try again.";
            }
        } else {
            $error = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch last 5 payroll records for this employee
$history = $conn->query("SELECT * FROM payroll WHERE employee_id = $id ORDER BY pay_date DESC LIMIT 5");

$initials      = strtoupper(substr($row['first_name'],0,1) . substr($row['last_name'],0,1));
$current_month = date('F Y');
$current_date  = date('Y-m-d');
$colors        = ['#00e5a0','#4d94ff','#ff4d6d','#ffc247','#a78bfa','#fb923c'];
$color         = $colors[$id % count($colors)];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Payment — Payroll System</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg:       #0d0f14;
            --surface:  #13161e;
            --card:     #1a1e28;
            --border:   #1f2430;
            --accent:   #00e5a0;
            --danger:   #ff4d6d;
            --warn:     #ffc247;
            --text:     #e2e8f0;
            --text-dim: #718096;
        }
        body { background: var(--bg); color: var(--text); font-family: 'DM Mono', monospace; min-height: 100vh; }

        .topbar { display: flex; align-items: center; justify-content: space-between; padding: 20px 40px; border-bottom: 1px solid var(--border); background: var(--surface); position: sticky; top: 0; z-index: 50; }
        .topbar-brand { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.25rem; letter-spacing: .04em; color: var(--accent); text-decoration: none; }
        .topbar-brand span { color: var(--text-dim); font-weight: 400; }
        .topbar-nav { display: flex; gap: 24px; }
        .topbar-nav a { color: var(--text-dim); text-decoration: none; font-size: .78rem; letter-spacing: .08em; text-transform: uppercase; transition: color .2s; }
        .topbar-nav a:hover, .topbar-nav a.active { color: var(--accent); }

        .main { max-width: 680px; margin: 0 auto; padding: 48px 24px; }
        .page-label { font-size: .7rem; letter-spacing: .2em; text-transform: uppercase; color: var(--accent); margin-bottom: 8px; }
        .page-title { font-family: 'Syne', sans-serif; font-size: 2rem; font-weight: 800; margin-bottom: 28px; }

        .emp-badge { display: flex; align-items: center; gap: 16px; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 20px 24px; margin-bottom: 24px; }
        .avatar { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-family: 'Syne', sans-serif; font-size: 16px; font-weight: 800; flex-shrink: 0; }
        .emp-name-text { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 1.05rem; }
        .emp-meta { font-size: .75rem; color: var(--text-dim); margin-top: 3px; }
        .salary-tag { margin-left: auto; text-align: right; }
        .salary-tag .slabel { font-size: .65rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: .1em; margin-bottom: 2px; }
        .salary-tag .svalue { font-family: 'Syne', sans-serif; font-size: 1.3rem; font-weight: 800; color: var(--accent); }

        .alert-error   { background: rgba(255,77,109,.1); border: 1px solid rgba(255,77,109,.3); border-radius: 9px; padding: 13px 18px; margin-bottom: 20px; color: var(--danger); font-size: .82rem; }
        .alert-success { background: rgba(0,229,160,.1); border: 1px solid rgba(0,229,160,.3); border-radius: 9px; padding: 13px 18px; margin-bottom: 20px; color: var(--accent); font-size: .82rem; }

        .form-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 32px; margin-bottom: 24px; }
        .form-section-title { font-family: 'Syne', sans-serif; font-size: .9rem; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .form-section-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .form-group { display: flex; flex-direction: column; gap: 7px; }
        .form-group.full { grid-column: 1 / -1; }
        label { font-size: .68rem; letter-spacing: .1em; text-transform: uppercase; color: var(--text-dim); }

        input, select { background: var(--card); border: 1px solid var(--border); border-radius: 9px; padding: 11px 14px; color: var(--text); font-family: 'DM Mono', monospace; font-size: .85rem; outline: none; width: 100%; transition: border-color .2s, box-shadow .2s; }
        input:focus, select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(0,229,160,.08); }
        select option { background: var(--surface); }

        .status-row { display: flex; gap: 10px; }
        .status-btn { flex: 1; padding: 10px; border-radius: 9px; border: 1px solid var(--border); background: var(--card); color: var(--text-dim); font-family: 'DM Mono', monospace; font-size: .8rem; cursor: pointer; text-align: center; transition: all .2s; user-select: none; }
        .status-btn.active-paid    { background: rgba(0,229,160,.1); color: var(--accent); border-color: rgba(0,229,160,.4); }
        .status-btn.active-pending { background: rgba(255,194,71,.1); color: var(--warn); border-color: rgba(255,194,71,.4); }

        .form-footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; }
        .btn-pay { display: inline-flex; align-items: center; gap: 8px; padding: 12px 28px; background: var(--accent); color: #0d0f14; border: none; border-radius: 9px; font-family: 'DM Mono', monospace; font-size: .85rem; font-weight: 500; cursor: pointer; transition: background .2s; }
        .btn-pay:hover { background: #00c98d; }
        .btn-cancel { display: inline-flex; align-items: center; gap: 8px; padding: 12px 22px; background: transparent; color: var(--text-dim); border: 1px solid var(--border); border-radius: 9px; font-family: 'DM Mono', monospace; font-size: .85rem; text-decoration: none; transition: all .2s; }
        .btn-cancel:hover { border-color: var(--text-dim); color: var(--text); }

        .history-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; font-size: .8rem; }
        thead tr { background: var(--bg); border-bottom: 1px solid var(--border); }
        th { padding: 12px 18px; text-align: left; font-size: .62rem; letter-spacing: .12em; text-transform: uppercase; color: var(--text-dim); }
        tbody tr { border-bottom: 1px solid var(--border); }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: rgba(255,255,255,.02); }
        td { padding: 12px 18px; }
        .badge { display: inline-block; padding: 3px 9px; border-radius: 20px; font-size: .68rem; font-weight: 500; }
        .badge-paid    { background: rgba(0,229,160,.1); color: var(--accent); border: 1px solid rgba(0,229,160,.2); }
        .badge-pending { background: rgba(255,194,71,.1); color: var(--warn); border: 1px solid rgba(255,194,71,.2); }
        .no-history { padding: 28px; text-align: center; color: var(--text-dim); font-size: .82rem; }

        @media (max-width: 600px) { .form-grid { grid-template-columns: 1fr; } .form-group.full { grid-column: 1; } }
    </style>
</head>
<body>

<div class="topbar">
    <a href="../dashboard.php" class="topbar-brand">PAYROLL<span>.SYS</span></a>
    <nav class="topbar-nav">
        <a href="../dashboard.php">Dashboard</a>
        <a href="index.php" class="active">Employees</a>
        <a href="../payroll_history.php">History</a>
    </nav>
</div>

<div class="main">
    <div class="page-label">Payroll</div>
    <h1 class="page-title">Process Payment</h1>

    <!-- Employee Badge -->
    <div class="emp-badge">
        <div class="avatar" style="background:<?= $color ?>22;color:<?= $color ?>"><?= $initials ?></div>
        <div>
            <div class="emp-name-text"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
            <div class="emp-meta"><?= htmlspecialchars($row['position']) ?> · <?= htmlspecialchars($row['department_name']) ?></div>
        </div>
        <div class="salary-tag">
            <div class="slabel">Monthly Salary</div>
            <div class="svalue">₱<?= number_format($row['salary'], 2) ?></div>
        </div>
    </div>

    <?php if ($error):   echo "<div class='alert-error'>⚠ " . htmlspecialchars($error) . "</div>"; endif; ?>
    <?php if ($success): echo "<div class='alert-success'>✔ " . htmlspecialchars($success) . "</div>"; endif; ?>

    <!-- Form -->
    <div class="form-card">
        <div class="form-section-title">Payment Details</div>
        <form method="POST" action="pay.php?id=<?= $id ?>">
            <div class="form-grid">

                <div class="form-group">
                    <label>Pay Period *</label>
                    <input type="text" name="pay_period" required value="<?= htmlspecialchars($current_month) ?>" placeholder="e.g. May 2026">
                </div>

                <div class="form-group">
                    <label>Pay Date *</label>
                    <input type="date" name="pay_date" required value="<?= $current_date ?>">
                </div>

                <div class="form-group full">
                    <label>Amount (₱) *</label>
                    <input type="number" name="amount" step="0.01" min="0.01" required value="<?= htmlspecialchars($row['salary']) ?>">
                </div>

                <div class="form-group full">
                    <label>Status</label>
                    <input type="hidden" name="status" id="statusInput" value="Paid">
                    <div class="status-row">
                        <div class="status-btn active-paid" onclick="setStatus('Paid', this)">✔ Paid</div>
                        <div class="status-btn" onclick="setStatus('Pending', this)">⏳ Pending</div>
                    </div>
                </div>

            </div>
            <div class="form-footer">
                <a href="index.php" class="btn-cancel">✕ Cancel</a>
                <button type="submit" name="process_pay" class="btn-pay">💳 Process Payment</button>
            </div>
        </form>
    </div>

    <!-- Payment History for this employee -->
    <div class="form-section-title" style="font-family:'Syne',sans-serif;font-weight:700;font-size:.9rem;margin-bottom:14px;display:flex;align-items:center;gap:10px;">
        Recent Payments <span style="flex:1;height:1px;background:var(--border);display:block"></span>
    </div>
    <div class="history-card">
        <?php if ($history->num_rows === 0): ?>
            <div class="no-history">No payment records yet for this employee.</div>
        <?php else: ?>
        <table>
            <thead><tr><th>Pay Period</th><th>Pay Date</th><th>Amount</th><th>Status</th></tr></thead>
            <tbody>
            <?php while ($h = $history->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($h['pay_period']) ?></td>
                    <td style="color:var(--text-dim)"><?= $h['pay_date'] ?></td>
                    <td style="color:var(--accent)">₱<?= number_format($h['amount_paid'], 2) ?></td>
                    <td><span class="badge <?= $h['status'] === 'Paid' ? 'badge-paid' : 'badge-pending' ?>"><?= $h['status'] ?></span></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
function setStatus(value, el) {
    document.getElementById('statusInput').value = value;
    document.querySelectorAll('.status-btn').forEach(b => {
        b.className = 'status-btn';
    });
    el.classList.add(value === 'Paid' ? 'active-paid' : 'active-pending');
}
</script>
</body>
</html>
