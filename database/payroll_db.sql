-- ============================================================
--  payroll_db.sql  -  IT221 Final Term PIT
--  Full database: OLTP tables, star schema, views,
--  stored procedures, advanced SQL, concurrency control
-- ============================================================

CREATE DATABASE IF NOT EXISTS payroll_db;
USE payroll_db;

-- ============================================================
-- SECTION 1: OLTP TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS departments (
    department_id   INT          NOT NULL AUTO_INCREMENT,
    department_name VARCHAR(100) NOT NULL,
    location        VARCHAR(100),
    PRIMARY KEY (department_id)
);

-- version column = optimistic concurrency control
CREATE TABLE IF NOT EXISTS employees (
    employee_id   INT            NOT NULL AUTO_INCREMENT,
    first_name    VARCHAR(50)    NOT NULL,
    last_name     VARCHAR(50)    NOT NULL,
    email         VARCHAR(100)   NOT NULL UNIQUE,
    position      VARCHAR(100),
    salary        DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    hire_date     DATE           NOT NULL,
    department_id INT            NOT NULL,
    version       INT            NOT NULL DEFAULT 0,
    PRIMARY KEY (employee_id),
    FOREIGN KEY (department_id) REFERENCES departments(department_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS payroll (
    payroll_id  INT            NOT NULL AUTO_INCREMENT,
    employee_id INT            NOT NULL,
    amount_paid DECIMAL(10,2)  NOT NULL,
    pay_date    DATE           NOT NULL,
    pay_period  VARCHAR(50)    NOT NULL,
    status      VARCHAR(20)    NOT NULL DEFAULT 'Pending',
    PRIMARY KEY (payroll_id),
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

-- ============================================================
-- SECTION 2: SAMPLE DATA
-- ============================================================

INSERT INTO departments (department_name, location) VALUES
    ('Engineering', 'Building A'),
    ('HR',          'Building B'),
    ('Finance',     'Building C'),
    ('IT',          'Building D');

INSERT INTO employees (first_name, last_name, email, position, salary, hire_date, department_id) VALUES
    ('Emry',    'Cagasan',  'emrylovelujin@gmail.com', 'Developer',       35000.00, '2023-01-15', 1),
    ('Lougene', 'Valencia', 'lujen@company.com',       'HR Specialist',   28000.00, '2022-06-01', 2),
    ('Carlos',  'Reyes',    'carlos@company.com',      'Finance Analyst', 32000.00, '2021-09-10', 3),
    ('Lorraine','Bautista', 'lorraine@company.com',    'DB Admin',        36000.00, '2023-03-20', 4);

INSERT INTO payroll (employee_id, amount_paid, pay_date, pay_period, status) VALUES
    (1, 35000.00, '2026-04-30', 'April 2026', 'Paid'),
    (2, 28000.00, '2026-04-30', 'April 2026', 'Paid'),
    (3, 32000.00, '2026-04-30', 'April 2026', 'Paid'),
    (4, 36000.00, '2026-04-30', 'April 2026', 'Paid'),
    (1, 35000.00, '2026-03-31', 'March 2026', 'Paid'),
    (2, 28000.00, '2026-03-31', 'March 2026', 'Paid'),
    (3, 32000.00, '2026-03-31', 'March 2026', 'Paid'),
    (4, 36000.00, '2026-03-31', 'March 2026', 'Paid');

-- ============================================================
-- SECTION 3: ADVANCED SQL - VIEWS
-- ============================================================

-- VIEW 1: Full payroll detail (JOIN across 3 tables)
CREATE OR REPLACE VIEW vw_payroll_detail AS
SELECT
    p.payroll_id,
    p.pay_period,
    p.pay_date,
    p.amount_paid,
    p.status,
    e.employee_id,
    CONCAT(e.first_name, ' ', e.last_name) AS full_name,
    e.position,
    e.salary   AS base_salary,
    d.department_id,
    d.department_name
FROM payroll p
JOIN employees   e ON p.employee_id    = e.employee_id
JOIN departments d ON e.department_id  = d.department_id;

-- VIEW 2: Department summary with correlated subqueries
CREATE OR REPLACE VIEW vw_department_summary AS
SELECT
    d.department_id,
    d.department_name,
    d.location,
    COUNT(DISTINCT e.employee_id)  AS total_employees,
    COALESCE(SUM(e.salary), 0)     AS total_salary_expense,
    COALESCE(AVG(e.salary), 0)     AS avg_salary,
    (SELECT COUNT(*) FROM payroll p2
       JOIN employees e2 ON p2.employee_id = e2.employee_id
       WHERE e2.department_id = d.department_id
         AND p2.status = 'Paid')   AS total_paid_records,
    (SELECT COALESCE(SUM(p2.amount_paid), 0) FROM payroll p2
       JOIN employees e2 ON p2.employee_id = e2.employee_id
       WHERE e2.department_id = d.department_id
         AND p2.status = 'Paid')   AS total_paid_amount
FROM departments d
LEFT JOIN employees e ON d.department_id = e.department_id
GROUP BY d.department_id, d.department_name, d.location;

-- VIEW 3: Above-average salary (subquery)
CREATE OR REPLACE VIEW vw_above_avg_salary AS
SELECT
    e.employee_id,
    CONCAT(e.first_name, ' ', e.last_name) AS full_name,
    e.position,
    e.salary,
    d.department_name,
    ROUND(e.salary - (SELECT AVG(salary) FROM employees), 2) AS salary_above_avg
FROM employees e
JOIN departments d ON e.department_id = d.department_id
WHERE e.salary > (SELECT AVG(salary) FROM employees);

-- ============================================================
-- SECTION 4: DATA WAREHOUSE - STAR SCHEMA
-- ============================================================

CREATE TABLE IF NOT EXISTS dim_date (
    date_key     INT         NOT NULL,
    full_date    DATE        NOT NULL,
    day_of_month TINYINT     NOT NULL,
    month_num    TINYINT     NOT NULL,
    month_name   VARCHAR(20) NOT NULL,
    quarter      TINYINT     NOT NULL,
    year         SMALLINT    NOT NULL,
    PRIMARY KEY (date_key)
);

CREATE TABLE IF NOT EXISTS dim_employee (
    emp_key     INT          NOT NULL AUTO_INCREMENT,
    employee_id INT          NOT NULL,
    full_name   VARCHAR(101) NOT NULL,
    position    VARCHAR(100),
    hire_date   DATE,
    PRIMARY KEY (emp_key),
    UNIQUE KEY uq_dim_employee (employee_id)
);

CREATE TABLE IF NOT EXISTS dim_department (
    dept_key        INT          NOT NULL AUTO_INCREMENT,
    department_id   INT          NOT NULL,
    department_name VARCHAR(100) NOT NULL,
    location        VARCHAR(100),
    PRIMARY KEY (dept_key),
    UNIQUE KEY uq_dim_department (department_id)
);

CREATE TABLE IF NOT EXISTS fact_payroll (
    fact_id     INT            NOT NULL AUTO_INCREMENT,
    date_key    INT            NOT NULL,
    emp_key     INT            NOT NULL,
    dept_key    INT            NOT NULL,
    payroll_id  INT            NOT NULL,
    amount_paid DECIMAL(10,2)  NOT NULL,
    base_salary DECIMAL(10,2)  NOT NULL,
    pay_period  VARCHAR(50)    NOT NULL,
    status      VARCHAR(20)    NOT NULL,
    PRIMARY KEY (fact_id),
    UNIQUE KEY uq_fact_payroll (payroll_id),
    FOREIGN KEY (date_key) REFERENCES dim_date(date_key),
    FOREIGN KEY (emp_key)  REFERENCES dim_employee(emp_key),
    FOREIGN KEY (dept_key) REFERENCES dim_department(dept_key)
);

-- ============================================================
-- SECTION 5: ETL STORED PROCEDURE
-- ============================================================

DROP PROCEDURE IF EXISTS run_etl;

DELIMITER $$

CREATE PROCEDURE run_etl()
BEGIN
    -- Step 1: Populate dim_date
    INSERT INTO dim_date (date_key, full_date, day_of_month, month_num, month_name, quarter, year)
    SELECT DISTINCT
        CAST(DATE_FORMAT(pay_date, '%Y%m%d') AS UNSIGNED),
        pay_date,
        DAY(pay_date),
        MONTH(pay_date),
        MONTHNAME(pay_date),
        QUARTER(pay_date),
        YEAR(pay_date)
    FROM payroll
    ON DUPLICATE KEY UPDATE full_date = VALUES(full_date);

    -- Step 2: Populate dim_employee
    INSERT INTO dim_employee (employee_id, full_name, position, hire_date)
    SELECT
        employee_id,
        CONCAT(first_name, ' ', last_name),
        position,
        hire_date
    FROM employees
    ON DUPLICATE KEY UPDATE
        full_name = VALUES(full_name),
        position  = VALUES(position);

    -- Step 3: Populate dim_department
    INSERT INTO dim_department (department_id, department_name, location)
    SELECT department_id, department_name, location FROM departments
    ON DUPLICATE KEY UPDATE
        department_name = VALUES(department_name),
        location        = VALUES(location);

    -- Step 4: Populate fact_payroll (incremental - skip existing)
    INSERT INTO fact_payroll
        (date_key, emp_key, dept_key, payroll_id, amount_paid, base_salary, pay_period, status)
    SELECT
        CAST(DATE_FORMAT(p.pay_date, '%Y%m%d') AS UNSIGNED),
        de.emp_key,
        dd.dept_key,
        p.payroll_id,
        p.amount_paid,
        e.salary,
        p.pay_period,
        p.status
    FROM payroll p
    JOIN employees     e  ON p.employee_id    = e.employee_id
    JOIN dim_employee  de ON e.employee_id    = de.employee_id
    JOIN dim_department dd ON e.department_id = dd.department_id
    ON DUPLICATE KEY UPDATE status = VALUES(status);

    -- Return summary
    SELECT
        'ETL complete' AS result,
        (SELECT COUNT(*) FROM fact_payroll)    AS fact_rows,
        (SELECT COUNT(*) FROM dim_employee)    AS emp_dim_rows,
        (SELECT COUNT(*) FROM dim_department)  AS dept_dim_rows,
        (SELECT COUNT(*) FROM dim_date)        AS date_dim_rows;
END$$

DELIMITER ;

-- ============================================================
-- SECTION 6: DATA MART VIEW (on star schema)
-- ============================================================

CREATE OR REPLACE VIEW vw_dept_mart AS
SELECT
    dd.department_name,
    dd.location,
    dd.dept_key,
    COUNT(DISTINCT fp.emp_key)  AS employees_paid,
    SUM(fp.amount_paid)         AS total_paid,
    AVG(fp.amount_paid)         AS avg_paid,
    MAX(fp.amount_paid)         AS max_paid,
    MIN(fp.amount_paid)         AS min_paid,
    SUM(fp.base_salary)         AS total_base_salary,
    GROUP_CONCAT(DISTINCT dt.year ORDER BY dt.year) AS years_covered
FROM fact_payroll fp
JOIN dim_department dd ON fp.dept_key = dd.dept_key
JOIN dim_date       dt ON fp.date_key = dt.date_key
GROUP BY dd.dept_key, dd.department_name, dd.location;

-- ============================================================
-- SECTION 7: QUICK VERIFICATION QUERIES
-- ============================================================

SELECT COUNT(*) AS total_employees    FROM employees;
SELECT COUNT(*) AS total_payroll      FROM payroll;
SELECT SUM(amount_paid) AS total_paid FROM payroll WHERE status = 'Paid';
SELECT MAX(pay_date) AS last_pay_date FROM payroll;
SELECT * FROM vw_department_summary;
SELECT * FROM vw_above_avg_salary;
