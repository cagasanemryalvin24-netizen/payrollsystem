<?php include '../config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) { header("Location: index.php"); exit; }

$s = $pdo->prepare("SELECT e.*, d.department_name FROM employees e JOIN departments d ON e.department_id = d.department_id WHERE e.employee_id = ?");
$s->execute([$id]);
$row = $s->fetch();

if (!$row) { header("Location: index.php"); exit; }

if (isset($_POST['confirm_delete'])) {
    // Transaction: delete payroll first, then employee (referential integrity)
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM fact_payroll WHERE emp_key IN (SELECT emp_key FROM dim_employee WHERE employee_id = ?)")->execute([$id]);
        $pdo->prepare("DELETE FROM dim_employee WHERE employee_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM payroll WHERE employee_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM employees WHERE employee_id = ?")->execute([$id]);
        $pdo->commit();
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $deleteError = $e->getMessage();
    }
}

$initials = strtoupper(substr($row['first_name'],0,1) . substr($row['last_name'],0,1));
$hire     = date('M d, Y', strtotime($row['hire_date']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Employee — Payroll System</title>
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

        /* ── Topbar ── */
        .topbar { display: flex; align-items: center; justify-content: space-between; padding: 20px 40px; border-bottom: 1px solid var(--border); background: var(--surface); position: sticky; top: 0; z-index: 50; }
        .topbar-brand { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.25rem; letter-spacing: .04em; color: var(--accent); text-decoration: none; }
        .topbar-brand span { color: var(--text-dim); font-weight: 400; }
        .topbar-nav { display: flex; gap: 24px; }
        .topbar-nav a { color: var(--text-dim); text-decoration: none; font-size: .78rem; letter-spacing: .08em; text-transform: uppercase; transition: color .2s; }
        .topbar-nav a:hover, .topbar-nav a.active { color: var(--accent); }

        /* ── Main ── */
        .main { max-width: 560px; margin: 0 auto; padding: 64px 24px; }

        /* ── Danger card ── */
        .danger-card {
            background: var(--surface);
            border: 1px solid rgba(255,77,109,.3);
            border-radius: 16px;
            overflow: hidden;
        }

        .danger-header {
            background: rgba(255,77,109,.07);
            border-bottom: 1px solid rgba(255,77,109,.2);
            padding: 28px 32px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .danger-icon {
            width: 52px; height: 52px; border-radius: 50%;
            background: rgba(255,77,109,.15);
            color: var(--danger);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }
        .danger-title { font-family: 'Syne', sans-serif; font-size: 1.3rem; font-weight: 800; color: var(--danger); }
        .danger-subtitle { font-size: .78rem; color: var(--text-dim); margin-top: 4px; }

        .card-body { padding: 32px; }

        /* ── Employee info ── */
        .emp-info {
            display: flex; align-items: center; gap: 16px;
            background: var(--card); border: 1px solid var(--border);
            border-radius: 12px; padding: 20px;
            margin-bottom: 24px;
        }
        .avatar {
            width: 50px; height: 50px; border-radius: 50%;
            background: rgba(255,77,109,.12); color: var(--danger);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Syne', sans-serif; font-size: 16px; font-weight: 800;
            flex-shrink: 0;
        }
        .emp-name { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 1.05rem; margin-bottom: 4px; }
        .emp-meta { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; }
        .meta-tag {
            font-size: .68rem; padding: 3px 9px; border-radius: 20px;
            background: var(--surface); border: 1px solid var(--border); color: var(--text-dim);
        }

        /* ── Warning box ── */
        .warning-box {
            background: rgba(255,194,71,.06);
            border: 1px solid rgba(255,194,71,.2);
            border-radius: 10px;
            padding: 14px 18px;
            font-size: .78rem;
            color: var(--warn);
            margin-bottom: 28px;
            line-height: 1.6;
        }
        .warning-box strong { display: block; margin-bottom: 4px; font-size: .8rem; }

        /* ── Buttons ── */
        .btn-row { display: flex; gap: 12px; }
        .btn-delete {
            flex: 1;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            padding: 13px 24px;
            background: var(--danger); color: #fff;
            border: none; border-radius: 10px;
            font-family: 'DM Mono', monospace; font-size: .85rem; font-weight: 500;
            cursor: pointer; transition: background .2s, transform .1s;
        }
        .btn-delete:hover { background: #e5344f; }
        .btn-delete:active { transform: scale(.97); }
        .btn-cancel {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            padding: 13px 22px;
            background: transparent; color: var(--text-dim);
            border: 1px solid var(--border); border-radius: 10px;
            font-family: 'DM Mono', monospace; font-size: .85rem;
            text-decoration: none; transition: all .2s;
        }
        .btn-cancel:hover { border-color: var(--text-dim); color: var(--text); }
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
    <div class="danger-card">

        <!-- Header -->
        <div class="danger-header">
            <div class="danger-icon">🗑</div>
            <div>
                <div class="danger-title">Delete Employee</div>
                <div class="danger-subtitle">This action cannot be undone</div>
            </div>
        </div>

        <div class="card-body">

            <!-- Employee info -->
            <div class="emp-info">
                <div class="avatar"><?= $initials ?></div>
                <div>
                    <div class="emp-name"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
                    <div style="font-size:.78rem;color:var(--text-dim)"><?= htmlspecialchars($row['email']) ?></div>
                    <div class="emp-meta">
                        <span class="meta-tag">ID #<?= $id ?></span>
                        <span class="meta-tag"><?= htmlspecialchars($row['position']) ?></span>
                        <span class="meta-tag"><?= htmlspecialchars($row['department_name']) ?></span>
                        <span class="meta-tag">Since <?= $hire ?></span>
                    </div>
                </div>
            </div>

            <!-- Warning -->
            <div class="warning-box">
                <strong>⚠ Warning — the following will be permanently deleted:</strong>
                · Employee profile and all personal information<br>
                · All linked payroll records for this employee<br>
                · This cannot be recovered after confirmation
            </div>

            <!-- Action buttons -->
            <form method="POST">
                <div class="btn-row">
                    <a href="index.php" class="btn-cancel">✕ Cancel</a>
                    <button type="submit" name="confirm_delete" class="btn-delete">🗑 Yes, Delete Employee</button>
                </div>
            </form>

        </div>
    </div>
</div>
</body>
</html>