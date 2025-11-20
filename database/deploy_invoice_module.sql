-- =====================================================
-- INVOICE MODULE PRODUCTION DEPLOYMENT SCRIPT
-- =====================================================
-- This script deploys the complete Invoice module functionality
-- for branch invoice ordering system with HQ approval workflow

-- =====================================================

USE ospos;

-- =====================================================
-- 1. CREATE INVOICE TABLES
-- =====================================================

-- Main invoices table
CREATE TABLE IF NOT EXISTS `ospos_invoices` (
  `invoice_id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_date` date NOT NULL,
  `branch_location_id` int(11) NOT NULL,
  `notes` text,
  `total_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','approved','declined','fulfilled','cancelled') NOT NULL DEFAULT 'pending',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fulfilled_at` timestamp NULL DEFAULT NULL,
  `approved_by` int(11) NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `declined_by` int(11) NULL DEFAULT NULL,
  `declined_at` timestamp NULL DEFAULT NULL,
  `decline_reason` text NULL DEFAULT NULL,
  PRIMARY KEY (`invoice_id`),
  KEY `branch_location_id` (`branch_location_id`),
  KEY `created_by` (`created_by`),
  KEY `status` (`status`),
  KEY `invoice_date` (`invoice_date`),
  KEY `idx_status` (`status`),
  KEY `idx_approved_by` (`approved_by`),
  KEY `idx_declined_by` (`declined_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Invoice items table
CREATE TABLE IF NOT EXISTS `ospos_invoice_items` (
  `invoice_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `total_price` decimal(15,2) NOT NULL,
  PRIMARY KEY (`invoice_item_id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- =====================================================
-- 2. ADD FOREIGN KEY CONSTRAINTS
-- =====================================================

-- Add foreign key constraints for invoices table
ALTER TABLE `ospos_invoices`
  ADD CONSTRAINT `fk_invoices_branch_location` FOREIGN KEY (`branch_location_id`) REFERENCES `ospos_stock_locations` (`location_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_invoices_created_by` FOREIGN KEY (`created_by`) REFERENCES `ospos_people` (`person_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_invoices_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `ospos_people` (`person_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_invoices_declined_by` FOREIGN KEY (`declined_by`) REFERENCES `ospos_people` (`person_id`) ON DELETE SET NULL;

-- Add foreign key constraints for invoice items table
ALTER TABLE `ospos_invoice_items`
  ADD CONSTRAINT `fk_invoice_items_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `ospos_invoices` (`invoice_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_invoice_items_item` FOREIGN KEY (`item_id`) REFERENCES `ospos_items` (`item_id`) ON DELETE CASCADE;

-- =====================================================
-- 3. ADD MODULE AND PERMISSIONS
-- =====================================================

-- Insert invoice module
INSERT INTO `ospos_modules` (`module_id`, `name_lang_key`, `desc_lang_key`, `sort`) 
VALUES ('invoice', 'module_invoice', 'module_invoice_desc', 75)
ON DUPLICATE KEY UPDATE 
  `name_lang_key` = VALUES(`name_lang_key`),
  `desc_lang_key` = VALUES(`desc_lang_key`),
  `sort` = VALUES(`sort`);

-- Insert invoice permissions
INSERT INTO `ospos_permissions` (`permission_id`, `module_id`, `location_id`) 
VALUES 
('invoice', 'invoice', NULL),
('invoice_create', 'invoice', NULL),
('invoice_view', 'invoice', NULL),
('invoice_review', 'invoice', NULL)
ON DUPLICATE KEY UPDATE 
  `module_id` = VALUES(`module_id`),
  `location_id` = VALUES(`location_id`);

-- =====================================================
-- 4. GRANT PERMISSIONS TO USERS
-- =====================================================

-- Grant basic invoice permissions to all employees
INSERT IGNORE INTO `ospos_grants` (`permission_id`, `person_id`, `menu_group`)
SELECT 'invoice', `person_id`, 'home'
FROM `ospos_employees`
WHERE `deleted` = 0;

INSERT IGNORE INTO `ospos_grants` (`permission_id`, `person_id`, `menu_group`)
SELECT 'invoice_create', `person_id`, 'home'
FROM `ospos_employees`
WHERE `deleted` = 0;

INSERT IGNORE INTO `ospos_grants` (`permission_id`, `person_id`, `menu_group`)
SELECT 'invoice_view', `person_id`, 'home'
FROM `ospos_employees`
WHERE `deleted` = 0;

-- =====================================================
-- 5. VERIFICATION QUERIES
-- =====================================================

-- Verify tables were created
SELECT 'Tables created successfully' as status, 
       COUNT(*) as table_count 
FROM information_schema.tables 
WHERE table_schema = 'ospos' 
AND table_name IN ('ospos_invoices', 'ospos_invoice_items');

-- Verify module was added
SELECT 'Module added successfully' as status,
       module_id, name_lang_key, desc_lang_key, sort
FROM `ospos_modules` 
WHERE `module_id` = 'invoice';

-- Verify permissions were added
SELECT 'Permissions added successfully' as status,
       permission_id, module_id
FROM `ospos_permissions` 
WHERE `permission_id` LIKE 'invoice%'
ORDER BY permission_id;

-- Verify grants were assigned
SELECT 'Grants assigned successfully' as status,
       permission_id, COUNT(*) as user_count
FROM `ospos_grants` 
WHERE `permission_id` LIKE 'invoice%'
GROUP BY permission_id;
