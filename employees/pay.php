<?php
// pay.php – Process Payroll
// Demonstrates: Transaction Management (BEGIN/COMMIT/ROLLBACK)
//               Concurrency Control (SELECT FOR UPDATE + version column)
//               Prepared Statements / PDO

include '../config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header("Location: index.php"); exit; }

// Fetch employee with version (optimistic lock info)
$stmt = $pdo->prepare("
    SELECT e.*, d.department_name
    FROM employees e
    JOIN departments d ON e.department_id = d.department_id
    WHERE e.employee_id = ?
");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) { header("Location: index.php"); exit; }

$success = '';
$error   = '';
$txLog   = [];  // transaction log to display to user

if (isset($_POST['process_pay'])) {
    $amount      = (float)$_POST['amount'];
    $pay_date    = trim($_POST['pay_date']);
    $pay_period  = trim($_POST['pay_period']);
    $status      = trim($_POST['status']);
    $ver_at_load = (int)$_POST['version'];   // version snapshot from form

    if (empty($pay_date) || empty($pay_period) || $amount <= 0) {
        $error = "Please fill in all fields with valid values.";
    } else {
        // ── TRANSACTION START ─────────────────────────────────────────
        try {
            $pdo->beginTransaction();
            $txLog[] = "BEGIN TRANSACTION";

            // ── CONCURRENCY CONTROL: SELECT FOR UPDATE ───────────────
            // Acquires a row-level exclusive lock so no other session can
            // modify this employee record until we COMMIT or ROLLBACK.
            $lock = $pdo->prepare("
                SELECT employee_id, version, salary
                FROM employees
                WHERE employee_id = ?
                FOR UPDATE
            ");
            $lock->execute([$id]);
            $locked = $lock->fetch();
            $txLog[] = "SELECT FOR UPDATE on employee #$id (locked row)";

            // ── OPTIMISTIC VERSION CHECK ─────────────────────────────
            // Compare the version the user loaded vs. current DB version.
            // If they differ, another session edited the record first.
            if ($locked['version'] !== $ver_at_load) {
                throw new Exception(
                    "Concurrency conflict: employee record was modified by another session "
                    . "(loaded version $ver_at_load, current version {$locked['version']}). "
                    . "Please reload and try again."
                );
            }
            $txLog[] = "Version check passed (version = $ver_at_load)";

            // ── CHECK DUPLICATE PAY PERIOD ───────────────────────────
            $dup = $pdo->prepare("
                SELECT COUNT(*) FROM payroll
                WHERE employee_id = ? AND pay_period = ?
            ");
            $dup->execute([$id, $pay_period]);
            if ((int)$dup->fetchColumn() > 0) {
                throw new Exception("Payroll for '$pay_period' already exists for this employee.");
            }
            $txLog[] = "Duplicate pay-period check passed";

            // ── INSERT PAYROLL RECORD ────────────────────────────────
            $ins = $pdo->prepare("
                INSERT INTO payroll (employee_id, amount_paid, pay_date, pay_period, status)
                VALUES (?, ?, ?, ?, ?)
            ");
            $ins->execute([$id, $amount, $pay_date, $pay_period, $status]);
            $newId = $pdo->lastInsertId();
            $txLog[] = "INSERT payroll record (ID = $newId)";

            // ── BUMP VERSION (prevents stale reads by others) ────────
            $upd = $pdo->prepare("
                UPDATE employees
                SET version = version + 1
                WHERE employee_id = ?
            ");
            $upd->execute([$id]);
            $txLog[] = "UPDATE employees.version to " . ($ver_at_load + 1);

            // ── COMMIT ───────────────────────────────────────────────
            $pdo->commit();
            $txLog[] = "COMMIT – transaction successful";

            $success = "Payroll processed successfully (Record #$newId).";

            // Refresh employee row to get new version
            $stmt->execute([$id]);
            $row = $stmt->fetch();

        } catch (Exception $e) {
            // ── ROLLBACK ─────────────────────────────────────────────
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
                $txLog[] = "ROLLBACK – " . $e->getMessage();
            }
            $error = $e->getMessage();
        }
        // ── TRANSACTION END ───────────────────────────────────────────
    }
}

// Fetch last 5 payroll records for this employee
$history = $pdo->prepare("SELECT * FROM payroll WHERE employee_id = ? ORDER BY pay_date DESC LIMIT 5");
$history->execute([$id]);
$histRows = $history->fetchAll();

$initials    = strtoupper(substr($row['first_name'],0,1) . substr($row['last_name'],0,1));
$colors      = ['#00e5a0','#4d94ff','#ff4d6d','#ffc247','#a78bfa','#fb923c'];
$color       = $colors[$id % count($colors)];
$currMonth   = date('F Y');
$currDate    = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Process Payment — Payroll System</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#0d0f14;--surface:#13161e;--card:#1a1e28;--border:#1f2430;--accent:#00e5a0;--danger:#ff4d6d;--warn:#ffc247;--text:#e2e8f0;--text-dim:#718096}
body{background:var(--bg);color:var(--text);font-family:'DM Mono',monospace;min-height:100vh}
.topbar{display:flex;align-items:center;justify-content:space-between;padding:20px 40px;border-bottom:1px solid var(--border);background:var(--surface);position:sticky;top:0;z-index:50}
.topbar-brand{font-family:'Syne',sans-serif;font-weight:800;font-size:1.25rem;letter-spacing:.04em;color:var(--accent);text-decoration:none}
.topbar-brand span{color:var(--text-dim);font-weight:400}
.topbar-nav{display:flex;gap:24px}
.topbar-nav a{color:var(--text-dim);text-decoration:none;font-size:.78rem;letter-spacing:.08em;text-transform:uppercase;transition:color .2s}
.topbar-nav a:hover,.topbar-nav a.active{color:var(--accent)}
.main{max-width:780px;margin:0 auto;padding:48px 24px}
.page-label{font-size:.7rem;letter-spacing:.2em;text-transform:uppercase;color:var(--accent);margin-bottom:8px}
.page-title{font-family:'Syne',sans-serif;font-size:2rem;font-weight:800;margin-bottom:28px}
.emp-badge{display:flex;align-items:center;gap:16px;background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:20px 24px;margin-bottom:24px}
.avatar{width:50px;height:50px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:16px;font-weight:800;flex-shrink:0}
.emp-name-text{font-family:'Syne',sans-serif;font-weight:700;font-size:1.05rem}
.emp-meta{font-size:.75rem;color:var(--text-dim);margin-top:3px}
.salary-tag{margin-left:auto;text-align:right}
.salary-tag .slabel{font-size:.65rem;color:var(--text-dim);text-transform:uppercase;letter-spacing:.1em;margin-bottom:2px}
.salary-tag .svalue{font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:800;color:var(--accent)}
.alert-error{background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.3);border-radius:9px;padding:13px 18px;margin-bottom:20px;color:var(--danger);font-size:.82rem}
.alert-success{background:rgba(0,229,160,.1);border:1px solid rgba(0,229,160,.3);border-radius:9px;padding:13px 18px;margin-bottom:20px;color:var(--accent);font-size:.82rem}
.form-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:32px;margin-bottom:24px}
.card-title{font-family:'Syne',sans-serif;font-size:.95rem;font-weight:700;margin-bottom:22px;display:flex;align-items:center;gap:10px}
.card-title::after{content:'';flex:1;height:1px;background:var(--border)}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.form-group{display:flex;flex-direction:column;gap:7px}
.form-group.full{grid-column:1/-1}
label{font-size:.68rem;letter-spacing:.1em;text-transform:uppercase;color:var(--text-dim)}
input,select{background:var(--card);border:1px solid var(--border);border-radius:8px;padding:11px 14px;color:var(--text);font-family:'DM Mono',monospace;font-size:.85rem;outline:none;transition:border-color .2s;width:100%}
input:focus,select:focus{border-color:var(--accent)}
select option{background:var(--surface)}
.btn-pay{display:inline-flex;align-items:center;gap:8px;padding:13px 28px;background:var(--accent);color:#0d0f14;border:none;border-radius:9px;font-family:'DM Mono',monospace;font-size:.88rem;font-weight:500;cursor:pointer;transition:background .2s;margin-top:8px}
.btn-pay:hover{background:#00c98d}
.btn-back{display:inline-flex;align-items:center;gap:7px;padding:11px 22px;border:1px solid var(--border);color:var(--text-dim);border-radius:9px;font-family:'DM Mono',monospace;font-size:.82rem;text-decoration:none;transition:all .2s}
.btn-back:hover{border-color:var(--accent);color:var(--accent)}
/* Transaction log */
.tx-log{background:#0a0c10;border:1px solid var(--border);border-radius:10px;padding:16px 20px;margin-bottom:24px;font-size:.78rem}
.tx-log-title{font-size:.65rem;letter-spacing:.15em;text-transform:uppercase;color:var(--text-dim);margin-bottom:10px}
.tx-step{padding:4px 0;border-bottom:1px solid #1a1d24;display:flex;gap:10px;align-items:flex-start}
.tx-step:last-child{border-bottom:none}
.tx-dot{width:6px;height:6px;border-radius:50%;background:var(--accent);flex-shrink:0;margin-top:5px}
.tx-dot.err{background:var(--danger)}
/* History */
.section-title{font-family:'Syne',sans-serif;font-size:.95rem;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:10px}
.section-title::after{content:'';flex:1;height:1px;background:var(--border)}
.table-wrap{background:var(--surface);border:1px solid var(--border);border-radius:12px;overflow:hidden}
table{width:100%;border-collapse:collapse;font-size:.8rem}
thead tr{background:#0d0f14;border-bottom:1px solid var(--border)}
th{padding:12px 16px;text-align:left;font-size:.63rem;letter-spacing:.12em;text-transform:uppercase;color:var(--text-dim);font-weight:500}
tbody tr{border-bottom:1px solid var(--border);transition:background .15s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:rgba(255,255,255,.02)}
td{padding:12px 16px;color:var(--text)}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.68rem;font-weight:500}
.badge-paid{background:rgba(0,229,160,.1);color:var(--accent);border:1px solid rgba(0,229,160,.2)}
.badge-pending{background:rgba(255,180,0,.1);color:#ffb400;border:1px solid rgba(255,180,0,.2)}
/* Concurrency info box */
.info-box{background:rgba(0,102,255,.07);border:1px solid rgba(0,102,255,.2);border-radius:10px;padding:14px 18px;margin-bottom:20px;font-size:.78rem;color:#8ab4ff}
.info-box strong{color:#4d94ff}
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
    <div class="page-label">Process Payment</div>
    <div class="page-title">Pay Employee</div>

    <!-- Employee badge -->
    <div class="emp-badge">
        <div class="avatar" style="background:<?= $color ?>22;color:<?= $color ?>"><?= $initials ?></div>
        <div>
            <div class="emp-name-text"><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></div>
            <div class="emp-meta"><?= htmlspecialchars($row['position']) ?> &middot; <?= htmlspecialchars($row['department_name']) ?> &middot; v<?= $row['version'] ?></div>
        </div>
        <div class="salary-tag">
            <div class="slabel">Base Salary</div>
            <div class="svalue">&#8369;<?= number_format($row['salary'], 2) ?></div>
        </div>
    </div>

    <!-- Concurrency info -->
    <div class="info-box">
        <strong>Concurrency Control active.</strong>
        This form uses <code>SELECT FOR UPDATE</code> (pessimistic locking) plus a <code>version</code>
        column (optimistic locking) to prevent double-payment if two users submit at the same time.
        Current version: <strong><?= $row['version'] ?></strong>
    </div>

    <?php if ($error): ?>
    <div class="alert-error">&#9888; <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert-success">&#10003; <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Transaction log display -->
    <?php if (!empty($txLog)): ?>
    <div class="tx-log">
        <div class="tx-log-title">Transaction Log</div>
        <?php foreach ($txLog as $i => $step):
            $isErr = str_contains($step, 'ROLLBACK');
        ?>
        <div class="tx-step">
            <div class="tx-dot <?= $isErr ? 'err' : '' ?>"></div>
            <div><?= htmlspecialchars($step) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Payroll form -->
    <div class="form-card">
        <div class="card-title">Payroll Details</div>
        <form method="POST" action="">
            <!-- Hidden version for optimistic locking -->
            <input type="hidden" name="version" value="<?= $row['version'] ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>Amount (PHP)</label>
                    <input type="number" name="amount" step="0.01" min="1"
                           value="<?= htmlspecialchars($row['salary']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Pay Date</label>
                    <input type="date" name="pay_date" value="<?= $currDate ?>" required>
                </div>
                <div class="form-group">
                    <label>Pay Period</label>
                    <input type="text" name="pay_period" value="<?= $currMonth ?>"
                           placeholder="e.g. May 2026" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="Paid">Paid</option>
                        <option value="Pending">Pending</option>
                    </select>
                </div>
            </div>
            <div style="display:flex;gap:12px;margin-top:24px;align-items:center">
                <button type="submit" name="process_pay" class="btn-pay">&#9654; Process Payment</button>
                <a href="index.php" class="btn-back">&#8592; Cancel</a>
            </div>
        </form>
    </div>

    <!-- Recent history -->
    <div class="section-title">Recent Payroll History</div>
    <?php if (empty($histRows)): ?>
    <p style="color:var(--text-dim);font-size:.82rem">No payroll records yet.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Pay Period</th><th>Amount Paid</th><th>Pay Date</th><th>Status</th>
            </tr></thead>
            <tbody>
            <?php foreach ($histRows as $h): ?>
            <tr>
                <td><?= htmlspecialchars($h['pay_period']) ?></td>
                <td>&#8369;<?= number_format($h['amount_paid'], 2) ?></td>
                <td><?= $h['pay_date'] ?></td>
                <td><span class="badge badge-<?= strtolower($h['status']) ?>"><?= $h['status'] ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
