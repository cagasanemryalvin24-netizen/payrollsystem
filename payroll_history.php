<?php include 'config/database.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll History — Payroll System</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg:       #0d0f14;
            --surface:  #13161e;
            --border:   #1f2430;
            --accent:   #00e5a0;
            --accent2:  #0066ff;
            --text:     #e2e8f0;
            --text-dim: #718096;
            --warn:     #ffc247;
            --danger:   #ff4d6d;
        }
        body { background: var(--bg); color: var(--text); font-family: 'DM Mono', monospace; min-height: 100vh; }

        .topbar { display: flex; align-items: center; justify-content: space-between; padding: 20px 40px; border-bottom: 1px solid var(--border); background: var(--surface); position: sticky; top: 0; z-index: 50; }
        .topbar-brand { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.25rem; letter-spacing: .04em; color: var(--accent); text-decoration: none; }
        .topbar-brand span { color: var(--text-dim); font-weight: 400; }
        .topbar-nav { display: flex; gap: 24px; }
        .topbar-nav a { color: var(--text-dim); text-decoration: none; font-size: .78rem; letter-spacing: .08em; text-transform: uppercase; transition: color .2s; }
        .topbar-nav a:hover, .topbar-nav a.active { color: var(--accent); }

        .main { max-width: 1100px; margin: 0 auto; padding: 48px 32px; }
        .page-header { margin-bottom: 28px; }
        .page-label { font-size: .7rem; letter-spacing: .2em; text-transform: uppercase; color: var(--accent); margin-bottom: 8px; }
        .page-title { font-family: 'Syne', sans-serif; font-size: 2.2rem; font-weight: 800; }

        .filter-bar { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 18px 24px; margin-bottom: 24px; }
        .filter-bar form { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-label { font-size: .7rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: .08em; white-space: nowrap; }
        select, input[type="number"] { background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 8px 12px; color: var(--text); font-family: 'DM Mono', monospace; font-size: .8rem; outline: none; transition: border-color .2s; }
        select:focus, input[type="number"]:focus { border-color: var(--accent); }
        select option { background: var(--surface); }
        input[type="number"] { width: 110px; }
        .btn-filter { padding: 9px 20px; background: var(--accent); color: #0d0f14; border: none; border-radius: 8px; font-family: 'DM Mono', monospace; font-size: .8rem; font-weight: 500; cursor: pointer; transition: background .2s; }
        .btn-filter:hover { background: #00c98d; }
        .btn-reset { padding: 9px 16px; background: transparent; color: var(--text-dim); border: 1px solid var(--border); border-radius: 8px; font-family: 'DM Mono', monospace; font-size: .8rem; cursor: pointer; text-decoration: none; transition: all .2s; }
        .btn-reset:hover { border-color: var(--accent); color: var(--accent); }

        .stats { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
        .stat { flex: 1; min-width: 120px; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 16px 20px; }
        .stat-label { font-size: .65rem; letter-spacing: .15em; text-transform: uppercase; color: var(--text-dim); margin-bottom: 6px; }
        .stat-value { font-family: 'Syne', sans-serif; font-size: 1.6rem; font-weight: 800; }
        .green { color: var(--accent); } .blue { color: #4d94ff; } .amber { color: var(--warn); }

        .section-title { font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 700; margin-bottom: 14px; display: flex; align-items: center; gap: 10px; }
        .section-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }

        .table-wrap { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; font-size: .82rem; }
        thead tr { background: var(--bg); border-bottom: 1px solid var(--border); }
        th { padding: 14px 20px; text-align: left; font-size: .63rem; letter-spacing: .12em; text-transform: uppercase; color: var(--text-dim); font-weight: 500; }
        tbody tr { border-bottom: 1px solid var(--border); transition: background .15s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: rgba(255,255,255,.025); }
        td { padding: 14px 20px; vertical-align: middle; }

        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: .7rem; font-weight: 500; }
        .badge-paid    { background: rgba(0,229,160,.1); color: var(--accent); border: 1px solid rgba(0,229,160,.2); }
        .badge-pending { background: rgba(255,194,71,.1); color: var(--warn);   border: 1px solid rgba(255,194,71,.2); }
        .dept-pill { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: .7rem; background: rgba(0,102,255,.12); color: #4d94ff; border: 1px solid rgba(0,102,255,.25); }
        .amount { color: var(--accent); }

        .empty { padding: 60px; text-align: center; color: var(--text-dim); }
        .empty-icon { font-size: 2.5rem; margin-bottom: 12px; }
        .empty-title { font-family: 'Syne', sans-serif; font-size: 1.1rem; font-weight: 700; margin-bottom: 6px; color: var(--text); }
    </style>
</head>
<body>

<div class="topbar">
    <a href="dashboard.php" class="topbar-brand">PAYROLL<span>.SYS</span></a>
    <nav class="topbar-nav">
        <a href="dashboard.php">Dashboard</a>
        <a href="employees/index.php">Employees</a>
        <a href="payroll_history.php" class="active">History</a>
    </nav>
</div>

<div class="main">
    <div class="page-header">
        <div class="page-label">Records</div>
        <h1 class="page-title">Payroll History</h1>
    </div>

    <!-- Filter -->
    <div class="filter-bar">
        <form method="GET" action="payroll_history.php">
            <div class="filter-group">
                <span class="filter-label">Department</span>
                <select name="department">
                    <option value="">All</option>
                    <?php
                    $dept_list = $conn->query("SELECT department_name FROM departments ORDER BY department_name");
                    while ($d = $dept_list->fetch_assoc()):
                        $sel = (isset($_GET['department']) && $_GET['department'] === $d['department_name']) ? 'selected' : '';
                    ?>
                    <option value="<?= htmlspecialchars($d['department_name']) ?>" <?= $sel ?>><?= htmlspecialchars($d['department_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <span class="filter-label">Year</span>
                <input type="number" name="year" placeholder="e.g. 2026" value="<?= htmlspecialchars($_GET['year'] ?? '') ?>">
            </div>
            <button type="submit" class="btn-filter">Filter</button>
            <a href="payroll_history.php" class="btn-reset">Reset</a>
        </form>
    </div>

    <?php
    // Build query — NO default filters, show everything unless user filters
    $where = [];
    if (!empty($_GET['department'])) {
        $dept    = $conn->real_escape_string($_GET['department']);
        $where[] = "d.department_name = '$dept'";
    }
    if (!empty($_GET['year'])) {
        $year    = (int)$_GET['year'];
        $where[] = "YEAR(p.pay_date) = $year";
    }
    $whereSQL = count($where) ? "WHERE " . implode(" AND ", $where) : "";

    $sql = "
        SELECT p.payroll_id,
               CONCAT(e.first_name,' ',e.last_name) AS employee_name,
               d.department_name, e.position,
               p.pay_period, p.pay_date, p.amount_paid, p.status
        FROM payroll p
        JOIN employees   e ON p.employee_id   = e.employee_id
        JOIN departments d ON e.department_id = d.department_id
        $whereSQL
        ORDER BY p.pay_date DESC, p.payroll_id DESC
    ";

    $result        = $conn->query($sql);
    $rows          = [];
    while ($r = $result->fetch_assoc()) $rows[] = $r;

    $total_records = count($rows);
    $total_amount  = array_sum(array_column($rows, 'amount_paid'));
    $paid_count    = count(array_filter($rows, fn($r) => $r['status'] === 'Paid'));
    ?>

    <!-- Stats -->
    <div class="stats">
        <div class="stat"><div class="stat-label">Records Shown</div><div class="stat-value blue"><?= $total_records ?></div></div>
        <div class="stat"><div class="stat-label">Total Amount</div><div class="stat-value green">₱<?= number_format($total_amount, 0) ?></div></div>
        <div class="stat"><div class="stat-label">Paid Count</div><div class="stat-value amber"><?= $paid_count ?></div></div>
    </div>

    <div class="section-title">All Payroll Records</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Employee</th><th>Department</th>
                    <th>Position</th><th>Pay Period</th><th>Pay Date</th>
                    <th>Net Pay</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="8">
                    <div class="empty">
                        <div class="empty-icon">📋</div>
                        <div class="empty-title">No records found</div>
                        <div>Process a payment from the Employees page to see records here.</div>
                    </div>
                </td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td style="color:var(--text-dim)"><?= $r['payroll_id'] ?></td>
                    <td style="font-weight:500"><?= htmlspecialchars($r['employee_name']) ?></td>
                    <td><span class="dept-pill"><?= htmlspecialchars($r['department_name']) ?></span></td>
                    <td style="color:var(--text-dim)"><?= htmlspecialchars($r['position']) ?></td>
                    <td><?= htmlspecialchars($r['pay_period']) ?></td>
                    <td style="color:var(--text-dim)"><?= $r['pay_date'] ?></td>
                    <td class="amount">₱<?= number_format($r['amount_paid'], 2) ?></td>
                    <td><span class="badge <?= $r['status'] === 'Paid' ? 'badge-paid' : 'badge-pending' ?>"><?= $r['status'] ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>