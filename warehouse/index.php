<?php
// warehouse/index.php – Data Warehouse & ETL
// Demonstrates: Star Schema, ETL (stored procedure), Window Functions,
//               Subqueries, Views, Data Mart

include '../config/database.php';

$etlMessage = '';
$etlLog     = [];

// ── Run ETL via stored procedure ──────────────────────────────────────────────
if (isset($_POST['run_etl'])) {
    try {
        // Use prepare+execute so we can consume ALL result sets the procedure returns.
        // CALL returns multiple result sets; if we don't drain them, PDO throws
        // SQLSTATE[HY000]: General error: 2014 (pending result sets).
        $callStmt = $pdo->prepare("CALL run_etl()");
        $callStmt->execute();

        // Drain every result set the stored procedure emits
        do { $callStmt->fetchAll(); } while ($callStmt->nextRowset());
        $callStmt->closeCursor();
        unset($callStmt);

        $etlMessage = 'success';
        $etlLog[]   = "CALL run_etl() executed at " . date('Y-m-d H:i:s');

        // Now safe to run more queries – capture row counts
        $counts = [
            'fact_payroll'   => (int)$pdo->query("SELECT COUNT(*) FROM fact_payroll")->fetchColumn(),
            'dim_employee'   => (int)$pdo->query("SELECT COUNT(*) FROM dim_employee")->fetchColumn(),
            'dim_department' => (int)$pdo->query("SELECT COUNT(*) FROM dim_department")->fetchColumn(),
            'dim_date'       => (int)$pdo->query("SELECT COUNT(*) FROM dim_date")->fetchColumn(),
        ];
        foreach ($counts as $tbl => $n) {
            $etlLog[] = "$tbl: $n row(s) loaded";
        }
    } catch (PDOException $e) {
        $etlMessage = 'error';
        $msg = $e->getMessage();
        $etlLog[] = "Error: " . $msg;
        if (str_contains($msg, 'does not exist') || str_contains($msg, '1305')) {
            $etlLog[] = "FIX: Stored procedure not created yet.";
            $etlLog[] = "In phpMyAdmin -> payroll_db -> SQL tab.";
            $etlLog[] = "Change the Delimiter field (bottom) from ; to \$\$";
            $etlLog[] = "Then paste and run the contents of create_procedure.sql";
        }
    }
}

// ── Star Schema row counts ────────────────────────────────────────────────────
function tableCount(PDO $pdo, string $tbl): int {
    try { return (int)$pdo->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn(); }
    catch (Exception $e) { return 0; }
}
$factCount   = tableCount($pdo, 'fact_payroll');
$dimEmpCount = tableCount($pdo, 'dim_employee');
$dimDeptCount= tableCount($pdo, 'dim_department');
$dimDateCount= tableCount($pdo, 'dim_date');

// ── Window Functions: salary rank within department ───────────────────────────
// Uses RANK(), DENSE_RANK(), SUM() OVER(PARTITION BY …)
$windowSQL = "
    SELECT
        CONCAT(e.first_name, ' ', e.last_name) AS full_name,
        e.position,
        d.department_name,
        e.salary,
        RANK()        OVER (PARTITION BY e.department_id ORDER BY e.salary DESC) AS dept_rank,
        DENSE_RANK()  OVER (ORDER BY e.salary DESC)                               AS company_rank,
        SUM(e.salary) OVER (PARTITION BY e.department_id)                         AS dept_total_salary,
        AVG(e.salary) OVER (PARTITION BY e.department_id)                         AS dept_avg_salary
    FROM employees e
    JOIN departments d ON e.department_id = d.department_id
    ORDER BY d.department_name, dept_rank
";
$windowRows = $pdo->query($windowSQL)->fetchAll();

// ── Subquery: above-average salary (view) ─────────────────────────────────────
$aboveAvgRows = [];
try {
    $aboveAvgRows = $pdo->query("SELECT * FROM vw_above_avg_salary ORDER BY salary DESC")->fetchAll();
} catch (Exception $e) {}

// ── Department Summary view ───────────────────────────────────────────────────
$deptSummaryRows = [];
try {
    $deptSummaryRows = $pdo->query("SELECT * FROM vw_department_summary ORDER BY total_salary_expense DESC")->fetchAll();
} catch (Exception $e) {}

// ── Data Mart view (on fact_payroll) ─────────────────────────────────────────
$martRows = [];
try {
    $martRows = $pdo->query("SELECT * FROM vw_dept_mart ORDER BY total_paid DESC")->fetchAll();
} catch (Exception $e) {}

// ── Fact table preview ────────────────────────────────────────────────────────
$factRows = [];
try {
    $factRows = $pdo->query("
        SELECT fp.*, de.full_name, dd.department_name, dt.month_name, dt.year
        FROM fact_payroll fp
        JOIN dim_employee   de ON fp.emp_key  = de.emp_key
        JOIN dim_department dd ON fp.dept_key = dd.dept_key
        JOIN dim_date       dt ON fp.date_key = dt.date_key
        ORDER BY fp.fact_id DESC LIMIT 20
    ")->fetchAll();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Data Warehouse — Payroll System</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#0d0f14;--surface:#13161e;--card:#1a1e28;--border:#1f2430;--accent:#00e5a0;--accent2:#0066ff;--warn:#ffc247;--danger:#ff4d6d;--purple:#a78bfa;--text:#e2e8f0;--text-dim:#718096}
body{background:var(--bg);color:var(--text);font-family:'DM Mono',monospace;min-height:100vh}
.topbar{display:flex;align-items:center;justify-content:space-between;padding:20px 40px;border-bottom:1px solid var(--border);background:var(--surface);position:sticky;top:0;z-index:50}
.topbar-brand{font-family:'Syne',sans-serif;font-weight:800;font-size:1.25rem;letter-spacing:.04em;color:var(--accent);text-decoration:none}
.topbar-brand span{color:var(--text-dim);font-weight:400}
.topbar-nav{display:flex;gap:24px}
.topbar-nav a{color:var(--text-dim);text-decoration:none;font-size:.78rem;letter-spacing:.08em;text-transform:uppercase;transition:color .2s}
.topbar-nav a:hover,.topbar-nav a.active{color:var(--accent)}
.main{max-width:1200px;margin:0 auto;padding:48px 32px}
.page-label{font-size:.7rem;letter-spacing:.2em;text-transform:uppercase;color:var(--accent);margin-bottom:8px}
.page-title{font-family:'Syne',sans-serif;font-size:2.4rem;font-weight:800;line-height:1.1}
.page-subtitle{color:var(--text-dim);font-size:.82rem;margin-top:10px;margin-bottom:40px}

/* Star Schema Cards */
.schema-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:40px}
.schema-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:20px 22px;position:relative;overflow:hidden}
.schema-card.fact{border-color:rgba(0,229,160,.35);background:rgba(0,229,160,.04)}
.schema-card.dim{border-color:rgba(0,102,255,.25);background:rgba(0,102,255,.04)}
.schema-card-type{font-size:.6rem;letter-spacing:.18em;text-transform:uppercase;color:var(--text-dim);margin-bottom:6px}
.schema-card-name{font-family:'Syne',sans-serif;font-size:.95rem;font-weight:700;margin-bottom:10px}
.schema-card.fact .schema-card-name{color:var(--accent)}
.schema-card.dim .schema-card-name{color:#4d94ff}
.schema-card-count{font-family:'Syne',sans-serif;font-size:2rem;font-weight:800}
.schema-card-label{font-size:.65rem;color:var(--text-dim);margin-top:3px}

/* ETL button area */
.etl-box{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:28px 32px;margin-bottom:40px;display:flex;align-items:center;gap:24px;flex-wrap:wrap}
.etl-info{flex:1;min-width:240px}
.etl-title{font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;margin-bottom:6px}
.etl-desc{font-size:.78rem;color:var(--text-dim);line-height:1.6}
.btn-etl{padding:13px 28px;background:var(--accent);color:#0d0f14;border:none;border-radius:9px;font-family:'DM Mono',monospace;font-size:.88rem;font-weight:500;cursor:pointer;transition:background .2s;white-space:nowrap}
.btn-etl:hover{background:#00c98d}
.etl-log{background:#0a0c10;border:1px solid var(--border);border-radius:10px;padding:14px 18px;margin-top:20px;font-size:.75rem;width:100%}
.etl-log-line{padding:3px 0;color:var(--accent)}
.etl-log-line.err{color:var(--danger)}

/* Sections */
.section-title{font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;margin-bottom:16px;margin-top:40px;display:flex;align-items:center;gap:10px}
.section-title::after{content:'';flex:1;height:1px;background:var(--border)}
.section-tag{font-size:.6rem;padding:3px 8px;border-radius:4px;font-weight:500;letter-spacing:.06em;text-transform:uppercase}
.tag-window{background:rgba(167,139,250,.12);color:var(--purple);border:1px solid rgba(167,139,250,.2)}
.tag-subq{background:rgba(255,180,0,.1);color:var(--warn);border:1px solid rgba(255,180,0,.2)}
.tag-view{background:rgba(0,102,255,.1);color:#4d94ff;border:1px solid rgba(0,102,255,.2)}
.tag-fact{background:rgba(0,229,160,.1);color:var(--accent);border:1px solid rgba(0,229,160,.2)}

/* Tables */
.table-wrap{background:var(--surface);border:1px solid var(--border);border-radius:12px;overflow-x:auto;margin-bottom:8px}
table{width:100%;border-collapse:collapse;font-size:.78rem}
thead tr{background:#0d0f14;border-bottom:1px solid var(--border)}
th{padding:12px 14px;text-align:left;font-size:.6rem;letter-spacing:.12em;text-transform:uppercase;color:var(--text-dim);font-weight:500;white-space:nowrap}
tbody tr{border-bottom:1px solid var(--border);transition:background .15s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:rgba(255,255,255,.02)}
td{padding:11px 14px;color:var(--text);white-space:nowrap}
.badge{display:inline-block;padding:2px 9px;border-radius:20px;font-size:.65rem;font-weight:500}
.badge-paid{background:rgba(0,229,160,.1);color:var(--accent);border:1px solid rgba(0,229,160,.2)}
.badge-1{background:rgba(0,229,160,.15);color:var(--accent)}
.badge-2{background:rgba(77,148,255,.12);color:#4d94ff}
.badge-3{background:rgba(255,180,0,.12);color:var(--warn)}
.hl{color:var(--accent)}
.empty{padding:28px;text-align:center;color:var(--text-dim);font-size:.82rem}
</style>
</head>
<body>
<div class="topbar">
    <a href="../dashboard.php" class="topbar-brand">PAYROLL<span>.SYS</span></a>
    <nav class="topbar-nav">
        <a href="../dashboard.php">Dashboard</a>
        <a href="../employees/index.php">Employees</a>
        <a href="../payroll_history.php">History</a>
        <a href="index.php" class="active">Warehouse</a>
    </nav>
</div>

<div class="main">
    <div class="page-label">Data Warehouse</div>
    <div class="page-title">Star Schema &amp; ETL</div>
    <div class="page-subtitle">Fact table + dimension tables &middot; ETL stored procedure &middot; Window functions &middot; Data mart views</div>

    <!-- Star Schema overview cards -->
    <div class="schema-grid">
        <div class="schema-card fact">
            <div class="schema-card-type">Fact Table</div>
            <div class="schema-card-name">fact_payroll</div>
            <div class="schema-card-count hl"><?= $factCount ?></div>
            <div class="schema-card-label">rows loaded</div>
        </div>
        <div class="schema-card dim">
            <div class="schema-card-type">Dimension</div>
            <div class="schema-card-name">dim_employee</div>
            <div class="schema-card-count"><?= $dimEmpCount ?></div>
            <div class="schema-card-label">rows</div>
        </div>
        <div class="schema-card dim">
            <div class="schema-card-type">Dimension</div>
            <div class="schema-card-name">dim_department</div>
            <div class="schema-card-count"><?= $dimDeptCount ?></div>
            <div class="schema-card-label">rows</div>
        </div>
        <div class="schema-card dim">
            <div class="schema-card-type">Dimension</div>
            <div class="schema-card-name">dim_date</div>
            <div class="schema-card-count"><?= $dimDateCount ?></div>
            <div class="schema-card-label">rows</div>
        </div>
    </div>

    <!-- ETL Control Panel -->
    <div class="etl-box">
        <div class="etl-info">
            <div class="etl-title">&#9654; Run ETL Process</div>
            <div class="etl-desc">
                Executes <code>CALL run_etl()</code> — the stored procedure that extracts data from
                OLTP tables, transforms it, and loads it into the star schema (fact + dimension tables).
                Safe to run multiple times (incremental / idempotent).
            </div>
        </div>
        <form method="POST" action="">
            <button type="submit" name="run_etl" class="btn-etl">&#9654;&nbsp; Run ETL</button>
        </form>
        <?php if (!empty($etlLog)): ?>
        <div class="etl-log">
            <?php foreach ($etlLog as $line): ?>
            <div class="etl-log-line <?= str_contains($line,'Error') ? 'err' : '' ?>">
                &gt; <?= htmlspecialchars($line) ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Window Functions -->
    <div class="section-title">
        Salary Rankings
        <span class="section-tag tag-window">Window Functions</span>
    </div>
    <div class="table-wrap">
        <?php if (empty($windowRows)): ?>
        <div class="empty">No employee data.</div>
        <?php else: ?>
        <table>
            <thead><tr>
                <th>Employee</th>
                <th>Position</th>
                <th>Department</th>
                <th>Salary</th>
                <th>Dept Rank <small>(RANK)</small></th>
                <th>Company Rank <small>(DENSE_RANK)</small></th>
                <th>Dept Total <small>(SUM OVER)</small></th>
                <th>Dept Avg <small>(AVG OVER)</small></th>
            </tr></thead>
            <tbody>
            <?php foreach ($windowRows as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['full_name']) ?></td>
                <td style="color:var(--text-dim)"><?= htmlspecialchars($r['position']) ?></td>
                <td><?= htmlspecialchars($r['department_name']) ?></td>
                <td class="hl">&#8369;<?= number_format($r['salary'],2) ?></td>
                <td>
                    <span class="badge badge-<?= min($r['dept_rank'],3) ?>">#<?= $r['dept_rank'] ?></span>
                </td>
                <td style="color:var(--text-dim)">#<?= $r['company_rank'] ?></td>
                <td>&#8369;<?= number_format($r['dept_total_salary'],2) ?></td>
                <td>&#8369;<?= number_format($r['dept_avg_salary'],2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <p style="font-size:.7rem;color:var(--text-dim);margin-bottom:4px">
        SQL: <code>RANK() OVER (PARTITION BY department_id ORDER BY salary DESC)</code>,
        <code>SUM(salary) OVER (PARTITION BY department_id)</code>
    </p>

    <!-- Above-average subquery -->
    <div class="section-title">
        Above-Average Salary Employees
        <span class="section-tag tag-subq">Subquery</span>
        <span class="section-tag tag-view">View</span>
    </div>
    <div class="table-wrap">
        <?php if (empty($aboveAvgRows)): ?>
        <div class="empty">No data. Run ETL or check employees.</div>
        <?php else: ?>
        <table>
            <thead><tr>
                <th>Employee</th><th>Position</th><th>Department</th>
                <th>Salary</th><th>Above Company Avg</th>
            </tr></thead>
            <tbody>
            <?php foreach ($aboveAvgRows as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['full_name']) ?></td>
                <td style="color:var(--text-dim)"><?= htmlspecialchars($r['position']) ?></td>
                <td><?= htmlspecialchars($r['department_name']) ?></td>
                <td class="hl">&#8369;<?= number_format($r['salary'],2) ?></td>
                <td style="color:#4d94ff">+&#8369;<?= number_format($r['salary_above_avg'],2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <p style="font-size:.7rem;color:var(--text-dim);margin-bottom:4px">
        SQL: <code>WHERE salary &gt; (SELECT AVG(salary) FROM employees)</code> via <code>vw_above_avg_salary</code>
    </p>

    <!-- Department Summary view -->
    <div class="section-title">
        Department Summary
        <span class="section-tag tag-view">View</span>
    </div>
    <div class="table-wrap">
        <?php if (empty($deptSummaryRows)): ?>
        <div class="empty">No data.</div>
        <?php else: ?>
        <table>
            <thead><tr>
                <th>Department</th><th>Location</th><th>Employees</th>
                <th>Total Salary</th><th>Avg Salary</th>
                <th>Paid Records</th><th>Total Paid</th>
            </tr></thead>
            <tbody>
            <?php foreach ($deptSummaryRows as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['department_name']) ?></td>
                <td style="color:var(--text-dim)"><?= htmlspecialchars($r['location']) ?></td>
                <td><?= $r['total_employees'] ?></td>
                <td class="hl">&#8369;<?= number_format($r['total_salary_expense'],2) ?></td>
                <td>&#8369;<?= number_format($r['avg_salary'],2) ?></td>
                <td><?= $r['total_paid_records'] ?></td>
                <td>&#8369;<?= number_format($r['total_paid_amount'],2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Data Mart view -->
    <div class="section-title">
        Department Data Mart
        <span class="section-tag tag-fact">Fact Table</span>
        <span class="section-tag tag-view">Mart View</span>
    </div>
    <?php if (empty($martRows)): ?>
    <div class="table-wrap"><div class="empty">Run ETL first to populate the star schema.</div></div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Department</th><th>Location</th>
                <th>Employees Paid</th><th>Total Paid</th>
                <th>Avg Paid</th><th>Max</th><th>Min</th>
                <th>Base Salary Total</th><th>Years</th>
            </tr></thead>
            <tbody>
            <?php foreach ($martRows as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['department_name']) ?></td>
                <td style="color:var(--text-dim)"><?= htmlspecialchars($r['location']) ?></td>
                <td><?= $r['employees_paid'] ?></td>
                <td class="hl">&#8369;<?= number_format($r['total_paid'],2) ?></td>
                <td>&#8369;<?= number_format($r['avg_paid'],2) ?></td>
                <td>&#8369;<?= number_format($r['max_paid'],2) ?></td>
                <td>&#8369;<?= number_format($r['min_paid'],2) ?></td>
                <td>&#8369;<?= number_format($r['total_base_salary'],2) ?></td>
                <td style="color:var(--text-dim)"><?= htmlspecialchars($r['years_covered']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Fact table preview -->
    <div class="section-title">
        Fact Table Preview
        <span class="section-tag tag-fact">fact_payroll</span>
    </div>
    <?php if (empty($factRows)): ?>
    <div class="table-wrap"><div class="empty">Run ETL first.</div></div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Fact ID</th><th>Employee</th><th>Department</th>
                <th>Month</th><th>Year</th><th>Pay Period</th>
                <th>Amount Paid</th><th>Base Salary</th><th>Status</th>
            </tr></thead>
            <tbody>
            <?php foreach ($factRows as $r): ?>
            <tr>
                <td style="color:var(--text-dim)"><?= $r['fact_id'] ?></td>
                <td><?= htmlspecialchars($r['full_name']) ?></td>
                <td><?= htmlspecialchars($r['department_name']) ?></td>
                <td><?= htmlspecialchars($r['month_name']) ?></td>
                <td><?= $r['year'] ?></td>
                <td><?= htmlspecialchars($r['pay_period']) ?></td>
                <td class="hl">&#8369;<?= number_format($r['amount_paid'],2) ?></td>
                <td>&#8369;<?= number_format($r['base_salary'],2) ?></td>
                <td><span class="badge badge-paid"><?= $r['status'] ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
