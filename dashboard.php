<?php include 'config/database.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg:        #0d0f14;
            --surface:   #13161e;
            --border:    #1f2430;
            --accent:    #00e5a0;
            --accent2:   #0066ff;
            --muted:     #4a5568;
            --text:      #e2e8f0;
            --text-dim:  #718096;
        }
        body { background: var(--bg); color: var(--text); font-family: 'DM Mono', monospace; min-height: 100vh; padding: 0; }
        .topbar { display: flex; align-items: center; justify-content: space-between; padding: 20px 40px; border-bottom: 1px solid var(--border); background: var(--surface); }
        .topbar-brand { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.25rem; letter-spacing: .04em; color: var(--accent); }
        .topbar-brand span { color: var(--text-dim); font-weight: 400; }
        .topbar-nav { display: flex; gap: 24px; }
        .topbar-nav a { color: var(--text-dim); text-decoration: none; font-size: .78rem; letter-spacing: .08em; text-transform: uppercase; transition: color .2s; }
        .topbar-nav a:hover, .topbar-nav a.active { color: var(--accent); }
        .main { max-width: 1100px; margin: 0 auto; padding: 48px 32px; }
        .page-header { margin-bottom: 48px; }
        .page-label { font-size: .7rem; letter-spacing: .2em; text-transform: uppercase; color: var(--accent); margin-bottom: 8px; }
        .page-title { font-family: 'Syne', sans-serif; font-size: 2.4rem; font-weight: 800; color: var(--text); line-height: 1.1; }
        .page-subtitle { color: var(--text-dim); font-size: .82rem; margin-top: 10px; }
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 20px; margin-bottom: 48px; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 28px 28px 24px; position: relative; overflow: hidden; animation: fadeUp .5s ease both; }
        .card:nth-child(1) { animation-delay: .05s; }
        .card:nth-child(2) { animation-delay: .12s; }
        .card:nth-child(3) { animation-delay: .19s; }
        .card:nth-child(4) { animation-delay: .26s; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }
        .card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, var(--accent), var(--accent2)); opacity: 0; transition: opacity .3s; }
        .card:hover::before { opacity: 1; }
        .card:hover { border-color: #2a3347; }
        .card-icon { width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; margin-bottom: 18px; }
        .card-icon.green  { background: rgba(0,229,160,.1); color: var(--accent); }
        .card-icon.blue   { background: rgba(0,102,255,.1); color: #4d94ff; }
        .card-icon.amber  { background: rgba(255,180,0,.1);  color: #ffb400; }
        .card-icon.rose   { background: rgba(255,72,110,.1); color: #ff486e; }
        .card-label { font-size: .68rem; letter-spacing: .15em; text-transform: uppercase; color: var(--text-dim); margin-bottom: 6px; }
        .card-value { font-family: 'Syne', sans-serif; font-size: 2rem; font-weight: 800; color: var(--text); line-height: 1; }
        .card-sub { font-size: .72rem; color: var(--text-dim); margin-top: 8px; }
        .section-title { font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
        .section-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }
        .table-wrap { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; font-size: .8rem; }
        thead tr { background: #0d0f14; border-bottom: 1px solid var(--border); }
        th { padding: 14px 20px; text-align: left; font-size: .65rem; letter-spacing: .14em; text-transform: uppercase; color: var(--text-dim); font-weight: 500; }
        tbody tr { border-bottom: 1px solid var(--border); transition: background .15s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: rgba(255,255,255,.02); }
        td { padding: 14px 20px; color: var(--text); }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: .68rem; font-weight: 500; letter-spacing: .04em; }
        .badge-paid    { background: rgba(0,229,160,.1); color: var(--accent); border: 1px solid rgba(0,229,160,.2); }
        .badge-pending { background: rgba(255,180,0,.1);  color: #ffb400; border: 1px solid rgba(255,180,0,.2); }
        .quick-links { display: flex; gap: 12px; margin-top: 36px; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; gap: 7px; padding: 10px 20px; border-radius: 8px; font-family: 'DM Mono', monospace; font-size: .78rem; text-decoration: none; letter-spacing: .04em; transition: all .2s; border: 1px solid transparent; }
        .btn-primary { background: var(--accent); color: #0d0f14; font-weight: 500; }
        .btn-primary:hover { background: #00c98d; }
        .btn-outline { border-color: var(--border); color: var(--text-dim); background: transparent; }
        .btn-outline:hover { border-color: var(--accent); color: var(--accent); }
    </style>
</head>
<body>

<div class="topbar">
    <div class="topbar-brand">PAYROLL<span>.SYS</span></div>
    <nav class="topbar-nav">
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="employees/index.php">Employees</a>
        <a href="payroll_history.php">History</a>
    </nav>
</div>

<div class="main">
    <div class="page-header">
        <div class="page-label">Overview</div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle"><?php echo date("l, F j, Y"); ?></p>
    </div>

    <?php
    $total_employees    = $conn->query("SELECT COUNT(*) AS c FROM employees")->fetch_assoc()['c'];
    $total_payroll_recs = $conn->query("SELECT COUNT(*) AS c FROM payroll")->fetch_assoc()['c'];
    $total_paid_result  = $conn->query("SELECT SUM(amount_paid) AS s FROM payroll WHERE status='Paid'")->fetch_assoc()['s'];
    $total_paid         = $total_paid_result ? $total_paid_result : 0;
    $last_date_row      = $conn->query("SELECT MAX(pay_date) AS d FROM payroll")->fetch_assoc()['d'];
    $last_payroll_date  = $last_date_row ? date("M d, Y", strtotime($last_date_row)) : 'N/A';
    ?>

    <div class="cards">
        <div class="card">
            <div class="card-icon green">👥</div>
            <div class="card-label">Total Employees</div>
            <div class="card-value"><?= $total_employees ?></div>
            <div class="card-sub">Active in system</div>
        </div>
        <div class="card">
            <div class="card-icon blue">📋</div>
            <div class="card-label">Payroll Records</div>
            <div class="card-value"><?= $total_payroll_recs ?></div>
            <div class="card-sub">All-time entries</div>
        </div>
        <div class="card">
            <div class="card-icon amber">💰</div>
            <div class="card-label">Total Amount Paid</div>
            <div class="card-value">₱<?= number_format($total_paid, 0) ?></div>
            <div class="card-sub">Paid status only</div>
        </div>
        <div class="card">
            <div class="card-icon rose">📅</div>
            <div class="card-label">Last Payroll Date</div>
            <div class="card-value" style="font-size:1.25rem;line-height:1.3"><?= $last_payroll_date ?></div>
            <div class="card-sub">Most recent run</div>
        </div>
    </div>

    <div class="section-title">Recent Payroll Records</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Employee</th><th>Department</th>
                    <th>Pay Period</th><th>Amount</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $recent = $conn->query("
                SELECT p.payroll_id,
                       CONCAT(e.first_name,' ',e.last_name) AS emp_name,
                       d.department_name, p.pay_period, p.amount_paid, p.status
                FROM payroll p
                JOIN employees e   ON p.employee_id   = e.employee_id
                JOIN departments d ON e.department_id = d.department_id
                ORDER BY p.pay_date DESC LIMIT 10
            ");
            while ($r = $recent->fetch_assoc()):
            ?>
            <tr>
                <td style="color:var(--text-dim)"><?= $r['payroll_id'] ?></td>
                <td><?= htmlspecialchars($r['emp_name']) ?></td>
                <td style="color:var(--text-dim)"><?= htmlspecialchars($r['department_name']) ?></td>
                <td><?= htmlspecialchars($r['pay_period']) ?></td>
                <td>₱<?= number_format($r['amount_paid'], 2) ?></td>
                <td>
                    <span class="badge <?= $r['status'] === 'Paid' ? 'badge-paid' : 'badge-pending' ?>">
                        <?= $r['status'] ?>
                    </span>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="quick-links">
        <a href="employees/index.php" class="btn btn-primary">➕ Add Employee</a>
        <a href="payroll_history.php" class="btn btn-outline">📋 Full Payroll History</a>
    </div>
</div>
</body>
</html>
