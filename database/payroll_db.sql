-- 1: Create the database
CREATE DATABASE IF NOT EXISTS payroll_db;
USE payroll_db;

-- TABLE 1: departments
CREATE TABLE departments (
    department_id   INT             NOT NULL AUTO_INCREMENT,
    department_name VARCHAR(100)    NOT NULL,
    location        VARCHAR(100),
    PRIMARY KEY (department_id)
);

-- TABLE 2: employees
CREATE TABLE employees (
    employee_id   INT             NOT NULL AUTO_INCREMENT,
    first_name    VARCHAR(50)     NOT NULL,
    last_name     VARCHAR(50)     NOT NULL,
    email         VARCHAR(100)    NOT NULL UNIQUE,
    position      VARCHAR(100),
    salary        DECIMAL(10, 2)  NOT NULL DEFAULT 0.00,
    hire_date     DATE            NOT NULL,
    department_id INT             NOT NULL,
    PRIMARY KEY (employee_id),
    FOREIGN KEY (department_id) REFERENCES departments(department_id)
);

-- TABLE 3: payroll
CREATE TABLE payroll (
    payroll_id  INT             NOT NULL AUTO_INCREMENT,
    employee_id INT             NOT NULL,
    amount_paid DECIMAL(10, 2)  NOT NULL,
    pay_date    DATE            NOT NULL,
    pay_period  VARCHAR(50)     NOT NULL,  -- e.g. 'April 2026'
    status      VARCHAR(20)     NOT NULL DEFAULT 'Pending',  -- Pending / Paid
    PRIMARY KEY (payroll_id),
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
);

-- SAMPLE DATA (for testing)
INSERT INTO departments (department_name, location) VALUES
    ('Engineering',  'Building A'),
    ('HR',           'Building B'),
    ('Finance',      'Building C');

INSERT INTO employees (first_name, last_name, email, position, salary, hire_date, department_id) VALUES
    ('Emry',    'Cagasan',  'emrylovelujin@gmail.com',   'Developer',       35000.00, '2023-01-15', 1),
    ('Lougene',   'Valencia',     'lujen@company.com',  'HR Specialist',   28000.00, '2022-06-01', 2),
    ('Carlos',  'Reyes',      'carlos@company.com', 'Finance Analyst', 32000.00, '2021-09-10', 3),
    ('Lorraine','Bautista',   'lorraine@company.com','DB Admin',       36000.00, '2023-03-20', 4);

INSERT INTO payroll (employee_id, amount_paid, pay_date, pay_period, status) VALUES
    (1, 35000.00, '2026-04-30', 'April 2026', 'Paid'),
    (2, 28000.00, '2026-04-30', 'April 2026', 'Paid'),
    (3, 32000.00, '2026-04-30', 'April 2026', 'Paid'),
    (4, 36000.00, '2026-04-30', 'April 2026', 'Paid');

-- TEST QUERIES
-- (Run these to verify tables are working)
-- Total employees
SELECT COUNT(*) AS total_employees FROM employees;

-- Total payroll records
SELECT COUNT(*) AS total_payroll_records FROM payroll;

-- Total amount paid
SELECT SUM(amount_paid) AS total_amount_paid FROM payroll WHERE status = 'Paid';

-- Last payroll date
SELECT MAX(pay_date) AS last_payroll_date FROM payroll;

-- Full join (employees with department and latest pay)
SELECT
    e.employee_id,
    CONCAT(e.first_name, ' ', e.last_name) AS full_name,
    d.department_name,
    e.salary,
    p.pay_date,
    p.status
FROM employees e
JOIN departments d ON e.department_id = d.department_id
LEFT JOIN payroll p ON e.employee_id = p.employee_id
ORDER BY p.pay_date DESC;
