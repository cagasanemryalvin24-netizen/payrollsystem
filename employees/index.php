<?php include '../config/database.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees — Payroll System</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg:       #0d0f14;
            --surface:  #13161e;
            --card:     #1a1e28;
            --border:   #1f2430;
            --accent:   #00e5a0;
            --accent2:  #0066ff;
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

        .main { max-width: 1200px; margin: 0 auto; padding: 48px 32px; }
        .page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 36px; flex-wrap: wrap; gap: 16px; }
        .page-label { font-size: .7rem; letter-spacing: .2em; text-transform: uppercase; color: var(--accent); margin-bottom: 8px; }
        .page-title { font-family: 'Syne', sans-serif; font-size: 2.2rem; font-weight: 800; line-height: 1.1; }

        .btn-add { display: inline-flex; align-items: center; gap: 8px; padding: 11px 22px; background: var(--accent); color: #0d0f14; border-radius: 9px; font-family: 'DM Mono', monospace; font-size: .82rem; font-weight: 500; text-decoration: none; transition: background .2s; }
        .btn-add:hover { background: #00c98d; }

        .stats { display: flex; gap: 16px; margin-bottom: 28px; flex-wrap: wrap; }
        .stat { flex: 1; min-width: 120px; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 16px 20px; }
        .stat-label { font-size: .65rem; letter-spacing: .15em; text-transform: uppercase; color: var(--text-dim); margin-bottom: 6px; }
        .stat-value { font-family: 'Syne', sans-serif; font-size: 1.6rem; font-weight: 800; }
        .green { color: var(--accent); } .blue { color: #4d94ff; } .amber { color: var(--warn); }

        .table-wrap { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; font-size: .82rem; }
        thead tr { background: var(--bg); border-bottom: 1px solid var(--border); }
        th { padding: 14px 16px; text-align: left; font-size: .63rem; letter-spacing: .12em; text-transform: uppercase; color: var(--text-dim); font-weight: 500; }
        tbody tr { border-bottom: 1px solid var(--border); transition: background .15s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: rgba(255,255,255,.025); }
        td { padding: 13px 16px; vertical-align: middle; }

        .name-cell { display: flex; align-items: center; gap: 11px; }
        .avatar { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-family: 'Syne', sans-serif; font-size: 13px; font-weight: 700; flex-shrink: 0; }
        .emp-name  { font-weight: 500; }
        .emp-email { font-size: .72rem; color: var(--text-dim); margin-top: 2px; }

        .dept-pill { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: .7rem; font-weight: 500; background: rgba(0,102,255,.12); color: #4d94ff; border: 1px solid rgba(0,102,255,.25); }
        .salary { color: var(--accent); }

        .pay-status { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 20px; font-size: .68rem; font-weight: 500; }
        .pay-status.paid    { background: rgba(0,229,160,.1);  color: var(--accent); border: 1px solid rgba(0,229,160,.2); }
        .pay-status.pending { background: rgba(255,194,71,.1); color: var(--warn);   border: 1px solid rgba(255,194,71,.2); }
        .pay-status.none    { background: rgba(107,115,148,.1);color: var(--text-dim);border: 1px solid var(--border); }

        .actions { display: flex; gap: 6px; flex-wrap: wrap; }
        .btn-icon { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; border-radius: 7px; font-family: 'DM Mono', monospace; font-size: .73rem; font-weight: 500; text-decoration: none; border: 1px solid var(--border); background: transparent; cursor: pointer; transition: all .15s; white-space: nowrap; }
        .btn-pay:hover  { background: rgba(0,229,160,.1);  border-color: var(--accent); color: var(--accent); }
        .btn-pay  { color: var(--accent); border-color: rgba(0,229,160,.3); }
        .btn-edit { color: #4d94ff; border-color: rgba(0,102,255,.3); }
        .btn-edit:hover { background: rgba(0,102,255,.12); border-color: #4d94ff; }
        .btn-del  { color: var(--danger); border-color: rgba(255,77,109,.3); }
        .btn-del:hover  { background: rgba(255,77,109,.12); border-color: var(--danger); }

        .empty { padding: 60px; text-align: center; color: var(--text-dim); }
        .empty-icon { font-size: 2.5rem; margin-bottom: 12px; }
        .empty-title { font-family: 'Syne', sans-serif; font-size: 1.1rem; font-weight: 700; margin-bottom: 6px; color: var(--text); }

        #toast { position: fixed; bottom: 28px; right: 28px; background: var(--surface); border: 1px solid rgba(0,229,160,.3); border-left: 4px solid var(--accent); border-radius: 10px; padding: 14px 22px; font-size: .82rem; font-weight: 500; color: var(--accent); transform: translateY(60px); opacity: 0; transition: all .3s; z-index: 999; }
        #toast.show { transform: translateY(0); opacity: 1; }
    </style>
</head>
<body>

<div class="topbar">
    <a href="../dashboard.php" class="topbar-brand">PAYROLL<span>.SYS</span></a>
    <nav class="topbar-nav">
        <a href="../dashboard.php">Dashboard</a>
        <a href="index.php" class="active">Employees</a>
        <a href="../payroll_history.php">History</a>
        <a href="../warehouse/index.php">Warehouse</a>
    </nav>
</div>

<div class="main">
    <?php
    $employees = $pdo->query("
        SELECT e.*, d.department_name,
               (SELECT status     FROM payroll WHERE employee_id = e.employee_id ORDER BY pay_date DESC LIMIT 1) AS last_status,
               (SELECT pay_period FROM payroll WHERE employee_id = e.employee_id ORDER BY pay_date DESC LIMIT 1) AS last_period
        FROM employees e
        JOIN departments d ON e.department_id = d.department_id
        ORDER BY e.employee_id ASC
    ")->fetchAll();
    $total      = count($employees);
    $avg_salary = $total ? array_sum(array_column($employees, 'salary')) / $total : 0;
    $depts      = count(array_unique(array_column($employees, 'department_id')));
    $paid_count = count(array_filter($employees, fn($e) => $e['last_status'] === 'Paid'));
    ?>

    <div class="stats">
        <div class="stat"><div class="stat-label">Total Employees</div><div class="stat-value green"><?= $total ?></div></div>
        <div class="stat"><div class="stat-label">Departments</div><div class="stat-value blue"><?= $depts ?></div></div>
        <div class="stat"><div class="stat-label">Avg. Salary</div><div class="stat-value amber">₱<?= number_format($avg_salary, 0) ?></div></div>
        <div class="stat"><div class="stat-label">Paid This Period</div><div class="stat-value green"><?= $paid_count ?></div></div>
    </div>

    <div class="page-header">
        <div>
            <div class="page-label">Management</div>
            <h1 class="page-title">Employees</h1>
        </div>
        <a href="add.php" class="btn-add">＋ Add Employee</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Employee</th><th>Department</th>
                    <th>Position</th><th>Salary</th><th>Payment Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($employees)): ?>
                <tr><td colspan="7">
                    <div class="empty">
                        <div class="empty-icon">🗂️</div>
                        <div class="empty-title">No employees yet</div>
                        <div>Click "Add Employee" to get started.</div>
                    </div>
                </td></tr>
            <?php else:
                $colors = ['#00e5a0','#4d94ff','#ff4d6d','#ffc247','#a78bfa','#fb923c'];
                foreach ($employees as $row):
                    $initials = strtoupper(substr($row['first_name'],0,1) . substr($row['last_name'],0,1));
                    $color    = $colors[$row['employee_id'] % count($colors)];
            ?>
                <tr>
                    <td style="color:var(--text-dim)"><?= $row['employee_id'] ?></td>
                    <td>
                        <div class="name-cell">
                            <div class="avatar" style="background:<?= $color ?>22;color:<?= $color ?>"><?= $initials ?></div>
                            <div>
                                <div class="emp-name"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
                                <div class="emp-email"><?= htmlspecialchars($row['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="dept-pill"><?= htmlspecialchars($row['department_name']) ?></span></td>
                    <td style="color:var(--text-dim)"><?= htmlspecialchars($row['position']) ?></td>
                    <td class="salary">₱<?= number_format($row['salary'], 2) ?></td>
                    <td>
                        <?php if ($row['last_status'] === 'Paid'): ?>
                            <span class="pay-status paid">✔ Paid · <?= htmlspecialchars($row['last_period']) ?></span>
                        <?php elseif ($row['last_status'] === 'Pending'): ?>
                            <span class="pay-status pending">⏳ Pending</span>
                        <?php else: ?>
                            <span class="pay-status none">— Not paid yet</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="actions">
                            <a href="pay.php?id=<?= $row['employee_id'] ?>" class="btn-icon btn-pay">💳 Pay</a>
                            <a href="edit.php?id=<?= $row['employee_id'] ?>" class="btn-icon btn-edit">✏ Edit</a>
                            <a href="delete.php?id=<?= $row['employee_id'] ?>" class="btn-icon btn-del" onclick="return confirm('Delete <?= htmlspecialchars($row['first_name']) ?>?')">🗑</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="toast">✔ Payment processed successfully!</div>

<script>
if (new URLSearchParams(location.search).get('paid') === '1') {
    const t = document.getElementById('toast');
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3500);
    history.replaceState({}, '', 'index.php');
}
</script>
</body>
</html>