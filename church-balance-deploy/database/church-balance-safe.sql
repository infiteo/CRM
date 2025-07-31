--
-- Church Balance SQL Installation Script (Safe Version)
-- This script creates tables without foreign keys first, then adds them safely
--

--
-- Table structure for table `church_balance_cb`
--

CREATE TABLE IF NOT EXISTS `church_balance_cb` (
  `cb_ID` mediumint(9) unsigned NOT NULL auto_increment,
  `cb_Date` date NOT NULL,
  `cb_Type` enum('Income','Expense','Transfer','Adjustment') NOT NULL default 'Income',
  `cb_Category` varchar(50) NOT NULL,
  `cb_Description` text NOT NULL,
  `cb_Amount` decimal(10,2) NOT NULL,
  `cb_Balance` decimal(10,2) NOT NULL,
  `cb_FundID` tinyint(3) unsigned default NULL,
  `cb_DepositID` mediumint(9) unsigned default NULL,
  `cb_CreatedBy` mediumint(9) unsigned NOT NULL,
  `cb_CreatedDate` datetime NOT NULL default CURRENT_TIMESTAMP,
  `cb_EditedBy` mediumint(9) unsigned default NULL,
  `cb_EditedDate` datetime default NULL,
  `cb_Notes` text,
  PRIMARY KEY (`cb_ID`),
  KEY `cb_Date` (`cb_Date`),
  KEY `cb_Type` (`cb_Type`),
  KEY `cb_FundID` (`cb_FundID`),
  KEY `cb_DepositID` (`cb_DepositID`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1;

--
-- Table structure for table `church_balance_categories_cbc`
--

CREATE TABLE IF NOT EXISTS `church_balance_categories_cbc` (
  `cbc_ID` mediumint(9) unsigned NOT NULL auto_increment,
  `cbc_Name` varchar(50) NOT NULL,
  `cbc_Type` enum('Income','Expense') NOT NULL,
  `cbc_Description` text,
  `cbc_Active` tinyint(1) NOT NULL default '1',
  `cbc_Order` tinyint(3) unsigned NOT NULL default '0',
  PRIMARY KEY (`cbc_ID`),
  UNIQUE KEY `cbc_Name` (`cbc_Name`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1;

--
-- Insert default categories (only if table is empty)
--

INSERT IGNORE INTO `church_balance_categories_cbc` (`cbc_Name`, `cbc_Type`, `cbc_Description`, `cbc_Order`) VALUES
('Offerte Domenicali', 'Income', 'Offerte regolari e collette domenicali', 1),
('Donazioni Speciali', 'Income', 'Donazioni speciali e offerte straordinarie', 2),
('Eventi Fundraising', 'Income', 'Eventi di raccolta fondi e attività benefiche', 3),
('Affitti/Locazioni', 'Income', 'Affitto locali e uso strutture', 4),
('Altre Entrate', 'Income', 'Entrate varie e diversificate', 5),
('Utenze', 'Expense', 'Elettricità, acqua, gas, internet, telefono', 1),
('Manutenzione Edificio', 'Expense', 'Riparazioni, manutenzione e miglioramenti', 2),
('Stipendi Staff', 'Expense', 'Compensi pastore e personale', 3),
('Spese Ministero', 'Expense', 'Programmi, materiali e costi ministeriali', 4),
('Spese Ufficio', 'Expense', 'Forniture ufficio, stampe, posta', 5),
('Assicurazioni', 'Expense', 'Assicurazione edificio e responsabilità civile', 6),
('Altre Spese', 'Expense', 'Spese varie e diversificate', 7);

--
-- Optional: Add foreign key constraints (only run if you want referential integrity)
-- If these fail, the system will still work without foreign keys
--

-- Add foreign key for fund reference (optional)
-- ALTER TABLE `church_balance_cb` ADD CONSTRAINT `fk_cb_fund` 
-- FOREIGN KEY (`cb_FundID`) REFERENCES `donationfund_fun` (`fun_ID`) ON DELETE SET NULL;

-- Add foreign key for deposit reference (optional)  
-- ALTER TABLE `church_balance_cb` ADD CONSTRAINT `fk_cb_deposit`
-- FOREIGN KEY (`cb_DepositID`) REFERENCES `deposit_dep` (`dep_ID`) ON DELETE SET NULL;
