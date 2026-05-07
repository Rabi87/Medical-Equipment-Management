-- قاعدة بيانات نظام إدارة شركة تجهيزات طبية
-- الإصدار 1.0
-- تاريخ الإنشاء: 2026-05-06

-- حذف قاعدة البيانات إذا كانت موجودة
DROP DATABASE IF EXISTS medical_equipment_db;
CREATE DATABASE medical_equipment_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE medical_equipment_db;

-- حذف الجداول إذا كانت موجودة (بترتيب عكسي بسبب العلاقات)
DROP TABLE IF EXISTS requests;
DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS minmax_settings;
DROP TABLE IF EXISTS maintenance_logs;
DROP TABLE IF EXISTS devices;
DROP TABLE IF EXISTS hospitals;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS system_logs;
DROP TABLE IF EXISTS users;

-- جدول المستخدمين (الصلاحيات)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'rep', 'storekeeper') NOT NULL DEFAULT 'storekeeper',
    phone VARCHAR(20),
    last_login DATETIME DEFAULT NULL,
    last_login_ip VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_role (role),
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول الموردين
CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول المشافي
CREATE TABLE hospitals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    rep_id INT DEFAULT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (rep_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_rep (rep_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول أجهزة الطبية (المنتجات)
CREATE TABLE devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    serial_number VARCHAR(50) NOT NULL UNIQUE,
    model VARCHAR(100) NOT NULL,
    brand VARCHAR(50),
    supplier_id INT,
    purchase_date DATE NOT NULL,
    warranty_months INT NOT NULL DEFAULT 12,
    warranty_expiry_date DATE,
    purchase_price DECIMAL(10,2),
    notes TEXT,
    status ENUM('in_stock', 'sold', 'maintenance') DEFAULT 'in_stock',
    current_hospital_id INT DEFAULT NULL,
    sold_date DATE DEFAULT NULL,
    sold_price DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (current_hospital_id) REFERENCES hospitals(id) ON DELETE SET NULL,
    INDEX idx_serial (serial_number),
    INDEX idx_status (status),
    INDEX idx_model (model),
    INDEX idx_warranty (warranty_expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول الصيانة والأعطال
CREATE TABLE maintenance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    hospital_id INT NOT NULL,
    reported_by INT DEFAULT NULL,
    assigned_rep_id INT NOT NULL,
    problem_description TEXT NOT NULL,
    status ENUM('pending', 'in_progress', 'resolved') DEFAULT 'pending',
    reported_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    start_date DATETIME DEFAULT NULL,
    resolution_notes TEXT,
    resolved_date DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_rep_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_device (device_id),
    INDEX idx_status (status),
    INDEX idx_assigned (assigned_rep_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول حركات المخزون
CREATE TABLE stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    movement_type ENUM('in', 'out', 'sale', 'return', 'adjustment') NOT NULL,
    from_status VARCHAR(20),
    to_status VARCHAR(20),
    hospital_id INT DEFAULT NULL,
    rep_id INT DEFAULT NULL,
    quantity INT DEFAULT 1,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE SET NULL,
    FOREIGN KEY (rep_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_device (device_id),
    INDEX idx_type (movement_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول إعدادات min/max للمخزون
CREATE TABLE minmax_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    model VARCHAR(100) NOT NULL,
    min_qty INT NOT NULL DEFAULT 1,
    max_qty INT NOT NULL DEFAULT 10,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_model (model)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول سجل الحركات النظام
CREATE TABLE system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action_type VARCHAR(50) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INT DEFAULT NULL,
    old_data JSON DEFAULT NULL,
    new_data JSON DEFAULT NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action_type),
    INDEX idx_table (table_name),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول طلبات الأجهزة
CREATE TABLE requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT NOT NULL,
    device_model VARCHAR(100) NOT NULL,
    brand VARCHAR(50),
    quantity INT NOT NULL DEFAULT 1,
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    status ENUM('pending', 'approved', 'rejected', 'fulfilled') DEFAULT 'pending',
    request_date DATE NOT NULL,
    needed_date DATE,
    notes TEXT,
    approved_by INT DEFAULT NULL,
    approved_date DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_hospital (hospital_id),
    INDEX idx_status (status),
    INDEX idx_request_date (request_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- بيانات اختبارية أولية
INSERT INTO users (username, email, password, full_name, role, phone, is_active) VALUES
('admin', 'admin@medical.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدير النظام', 'admin', '0501234567', TRUE),
('rep1', 'rep1@medical.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'المندوب أحمد', 'rep', '0502345678', TRUE),
('store1', 'store1@medical.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مسؤول المخزن', 'storekeeper', '0503456789', TRUE);

INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES
('شركة التجهيزات الطبية للاستيراد', 'أحمد محمد', '0123456789', 'contact@supplier1.com', 'دبي، الإمارات'),
('مؤسسة الصحة العالمية', 'سارة أحمد', '0123456790', 'contact@supplier2.com', 'الرياض، السعودية');

INSERT INTO hospitals (name, rep_id, contact_person, phone, email, address) VALUES
('مشفى الرازي', 2, 'د. خالد', '0112345678', 'contact@alrazzi.sy', 'دمشق، سوريا'),
('مشفى السلام', 2, 'د. ناصر', '0112345679', 'contact@alsalam.sy', 'حمص، سوريا'),
('مشفى المجتهد', NULL, 'د. فهد', '0112345680', 'contact@almajtahed.sy', 'حلب، سوريا');

INSERT INTO devices (serial_number, model, brand, supplier_id, purchase_date, warranty_months, warranty_expiry_date, purchase_price, status, current_hospital_id) VALUES
('SN-2024-001', 'ECG-MACHINE-X1', 'Philips', 1, '2024-01-15', 24, '2026-01-15', 15000.00, 'sold', 1),
('SN-2024-002', 'ULTRASOUND-PRO', 'GE', 1, '2024-02-20', 36, '2027-02-20', 45000.00, 'in_stock', NULL),
('SN-2024-003', 'VENTILATOR-V5', 'Dräger', 2, '2024-03-10', 24, '2026-03-10', 28000.00, 'sold', 2),
('SN-2024-004', 'MRI-SCANNER-BASIC', 'Siemens', 2, '2024-01-05', 48, '2028-01-05', 120000.00, 'in_stock', NULL),
('SN-2024-005', 'DEFIBRILLATOR-PLUS', 'Medtronic', 1, '2024-04-15', 24, '2026-04-15', 35000.00, 'maintenance', 3);

INSERT INTO minmax_settings (model, min_qty, max_qty, notes) VALUES
('ECG-MACHINE-X1', 2, 10, 'أجهزة تخطيط القلب - الحد الأدنى 2 للحالات الطارئة'),
('ULTRASOUND-PRO', 1, 5, 'أجهزة السونار المتقدمة'),
('VENTILATOR-V5', 2, 8, 'أجهزة التنفس الاصطناعي'),
('MRI-SCANNER-BASIC', 1, 3, 'أجهزة الرنين المغناطيسي'),
('DEFIBRILLATOR-PLUS', 2, 6, 'أجهزة الصدم القلبي - ضرورية للطوارئ');

-- بيانات عمليات الصيانة
INSERT INTO maintenance_logs (device_id, hospital_id, reported_by, assigned_rep_id, problem_description, status, reported_date, start_date, resolution_notes, resolved_date) VALUES
(1, 1, 1, 2, 'يظهر خطأ في قراءة نبضات القلب، الشاشة تتوقف عن العمل', 'resolved', '2024-06-01 09:00:00', '2024-06-02 10:00:00', 'تم استبدال بطاقة الدوائر وتحديث البرنامج', '2024-06-05 14:00:00'),
(3, 2, 1, 2, 'الضوضاء الزائد في المحرك وخطأ في ضغط الهواء', 'in_progress', '2024-06-10 08:30:00', '2024-06-11 09:00:00', NULL, NULL),
(2, 3, 1, 2, 'لم يعد يقرأ إشارات الأمواج، طلب صيانة طارئة', 'pending', '2024-06-15 11:00:00', NULL, NULL, NULL);

-- حركات المخزون (إدخال وإخراج)
INSERT INTO stock_movements (device_id, movement_type, from_status, to_status, hospital_id, rep_id, quantity, notes) VALUES
(1, 'in', NULL, 'in_stock', NULL, NULL, 1, 'إضافة جهاز تخطيط القلب من المورد'),
(2, 'in', NULL, 'in_stock', NULL, NULL, 1, 'إضافة جهاز سونار من المورد'),
(3, 'in', NULL, 'in_stock', NULL, NULL, 1, 'إضافة جهاز تنفس اصطناعي من المورد'),
(4, 'in', NULL, 'in_stock', NULL, NULL, 1, 'إضافة جهاز رنين مغناطيسي من المورد'),
(5, 'in', NULL, 'in_stock', NULL, NULL, 1, 'إضافة جهاز ضخم كهربائي من المورد'),
(1, 'sale', 'in_stock', 'sold', 1, 1, 1, 'بيع إلى مشفى الرازي'),
(3, 'sale', 'in_stock', 'sold', 2, 1, 1, 'بيع إلى مشفى السلام'),
(5, 'out', 'in_stock', 'maintenance', 3, 2, 1, 'إرسال جهاز ضخم كهربائي للصيانة في مشفى المجتهد');

-- سجل صيانة الجهاز الجديد
INSERT INTO maintenance_logs (device_id, hospital_id, reported_by, assigned_rep_id, problem_description, status, reported_date) VALUES
(5, 3, 1, 2, 'جهاز ضخم كهربائي لا يتصل بالشاشة ويظهر خطأ في التشفير', 'pending', '2024-06-16 14:00:00');

-- طلبات الأجهزة
INSERT INTO requests (hospital_id, device_model, brand, quantity, priority, status, request_date, needed_date, notes, approved_by, approved_date) VALUES
(1, 'VENTILATOR-V5', 'Dräger', 2, 'high', 'approved', '2024-06-01', '2024-06-20', 'طلب عاجل لأجهزة تنفس اصطناعي لقسانات الطوارئ', 1, '2024-06-02 10:00:00'),
(2, 'ECG-MACHINE-X2', 'Philips', 1, 'normal', 'pending', '2024-06-05', '2024-06-30', 'بديل لجهاز قديم', NULL, NULL),
(3, 'ULTRASOUND-PRO', 'GE', 1, 'urgent', 'fulfilled', '2024-06-03', '2024-06-10', 'للقساة الحالي، الجهاز الحالي تالف', 1, '2024-06-04 09:00:00'),
(1, 'DEFIBRILLATOR-PLUS', 'Medtronic', 3, 'high', 'approved', '2024-06-10', '2024-06-25', 'للطوارئ جميع الأقسام', 1, '2024-06-11 11:00:00');