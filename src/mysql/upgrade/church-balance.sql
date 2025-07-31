--
-- Table structure for table `church_balance_cb`
--

CREATE TABLE `church_balance_cb` (
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

CREATE TABLE `church_balance_categories_cbc` (
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
-- Insert default categories
--

INSERT INTO `church_balance_categories_cbc` (`cbc_Name`, `cbc_Type`, `cbc_Description`, `cbc_Order`) VALUES
('Donations', 'Income', 'Regular donations and offerings', 1),
('Special Offerings', 'Income', 'Special collections and offerings', 2),
('Fundraising', 'Income', 'Fundraising events and activities', 3),
('Rent/Facility Income', 'Income', 'Building rental and facility usage fees', 4),
('Other Income', 'Income', 'Miscellaneous income', 5),
('Utilities', 'Expense', 'Electricity, water, gas, internet, phone', 1),
('Building Maintenance', 'Expense', 'Repairs, maintenance, and improvements', 2),
('Staff Salaries', 'Expense', 'Pastor and staff compensation', 3),
('Ministry Expenses', 'Expense', 'Programs, materials, and ministry costs', 4),
('Office Expenses', 'Expense', 'Office supplies, printing, postage', 5),
('Insurance', 'Expense', 'Building and liability insurance', 6),
('Other Expenses', 'Expense', 'Miscellaneous expenses', 7);
