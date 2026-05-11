<?php include '../config/database.php';

$error = '';
if (isset($_POST['submit'])) {
    // PDO prepared statement - prevents SQL injection
    try {
        $stmt = $pdo->prepare("INSERT INTO employees
            (first_name, last_name, email, position, salary, hire_date, department_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            trim($_POST['first_name']),
            trim($_POST['last_name']),
            trim($_POST['email']),
            trim($_POST['position']),
            (float)$_POST['salary'],
            trim($_POST['hire_date']),
            (int)$_POST['department_id'],
        ]);
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}

$departments = $pdo->query("SELECT * FROM departments ORDER BY department_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employee — Payroll System</title>
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
        .page-title { font-family: 'Syne', sans-serif; font-size: 2rem; font-weight: 800; line-height: 1.1; margin-bottom: 32px; }

        /* ── Form card ── */
        .form-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 36px; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 7px; }
        .form-group.full { grid-column: 1 / -1; }

        label { font-size: .7rem; letter-spacing: .1em; text-transform: uppercase; color: var(--text-dim); }

        input[type="text"],
        input[type="email"],
        input[type="number"],
        input[type="date"],
        select {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 9px;
            padding: 11px 14px;
            color: var(--text);
            font-family: 'DM Mono', monospace;
            font-size: .85rem;
            outline: none;
            width: 100%;
            transition: border-color .2s, box-shadow .2s;
        }
        input:focus, select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(0,229,160,.08);
        }
        select option { background: var(--surface); }
        input::placeholder { color: var(--text-dim); }

        /* ── Error ── */
        .alert-error { background: rgba(255,77,109,.1); border: 1px solid rgba(255,77,109,.3); border-radius: 9px; padding: 14px 18px; margin-bottom: 24px; color: var(--danger); font-size: .82rem; }

        /* ── Divider ── */
        .divider { height: 1px; background: var(--border); margin: 28px 0; grid-column: 1 / -1; }

        /* ── Buttons ── */
        .form-footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 28px; }
        .btn-save { display: inline-flex; align-items: center; gap: 8px; padding: 12px 28px; background: var(--accent); color: #0d0f14; border: none; border-radius: 9px; font-family: 'DM Mono', monospace; font-size: .85rem; font-weight: 500; cursor: pointer; transition: background .2s, transform .1s; }
        .btn-save:hover { background: #00c98d; }
        .btn-save:active { transform: scale(.97); }
        .btn-cancel { display: inline-flex; align-items: center; gap: 8px; padding: 12px 22px; background: transparent; color: var(--text-dim); border: 1px solid var(--border); border-radius: 9px; font-family: 'DM Mono', monospace; font-size: .85rem; text-decoration: none; transition: all .2s; }
        .btn-cancel:hover { border-color: var(--text-dim); color: var(--text); }

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
        <a href="../warehouse/index.php">Warehouse</a>
    </nav>
</div>

<div class="main">
    <div class="page-label">Employees</div>
    <h1 class="page-title">Add New Employee</h1>

    <?php if (!empty($error)): ?>
        <div class="alert-error">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST">
            <div class="form-grid">

                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" placeholder="e.g. Juan" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" placeholder="e.g. dela Cruz" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                </div>

                <div class="form-group full">
                    <label>Email Address *</label>
                    <input type="email" name="email" placeholder="e.g. juan@company.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Position</label>
                    <input type="text" name="position" placeholder="e.g. Developer" value="<?= htmlspecialchars($_POST['position'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Department *</label>
                    <select name="department_id" required>
                        <option value="">Select department…</option>
                        <?php
                        foreach ($departments as $d):
                            $sel = (isset($_POST['department_id']) && $_POST['department_id'] == $d['department_id']) ? 'selected' : '';
                        ?>
                        <option value="<?= $d['department_id'] ?>" <?= $sel ?>><?= htmlspecialchars($d['department_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Salary (₱) *</label>
                    <input type="number" name="salary" placeholder="e.g. 35000" step="0.01" min="0" required value="<?= htmlspecialchars($_POST['salary'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Hire Date *</label>
                    <input type="date" name="hire_date" required value="<?= htmlspecialchars($_POST['hire_date'] ?? '') ?>">
                </div>

            </div>

            <div class="form-footer">
                <a href="index.php" class="btn-cancel">✕ Cancel</a>
                <button type="submit" name="submit" class="btn-save">＋ Save Employee</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>