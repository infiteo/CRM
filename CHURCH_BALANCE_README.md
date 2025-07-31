# Church Balance Management for ChurchCRM

This extension adds comprehensive financial balance tracking to your ChurchCRM installation, allowing you to monitor your church's financial health with detailed income and expense tracking.

## Features

### 📊 **Financial Tracking**
- **Real-time Balance Display**: See your current church balance at a glance
- **Transaction Management**: Add income, expenses, transfers, and adjustments
- **Category System**: Organize transactions with predefined or custom categories
- **Fund Integration**: Link transactions to existing donation funds

### 📈 **Reporting & Analytics**
- **Transaction History**: View detailed transaction logs with filters
- **Balance Reports**: Generate PDF or CSV reports for any date range
- **Summary Statistics**: Track total income, expenses, and net changes
- **Period Analysis**: Compare financial performance across different periods

### 🔧 **Administration**
- **Category Management**: Create and manage income/expense categories
- **User Permissions**: Finance-enabled users can view/add transactions
- **Admin Controls**: Full category management for administrators
- **Audit Trail**: Track who created each transaction and when

## Installation

### Prerequisites
- ChurchCRM 4.x or later
- MySQL/MariaDB database access
- Finance permissions enabled for relevant users

### Installation Steps

1. **Copy Files**: Place all the Church Balance files in your ChurchCRM installation:
   ```
   src/ChurchBalance.php
   src/ChurchBalanceCategories.php
   src/Reports/ChurchBalanceReport.php
   src/mysql/upgrade/church-balance.sql
   ```

2. **Run Installation Script**:
   ```bash
   cd /path/to/your/churchcrm/src
   ./install-church-balance.sh
   ```

3. **Manual Database Installation** (if script fails):
   ```bash
   mysql -u [username] -p [database_name] < mysql/upgrade/church-balance.sql
   ```

4. **Refresh ChurchCRM**: Clear your browser cache and refresh the interface.

## Usage Guide

### 🏠 **Main Balance Page**
Navigate to: **Deposit Menu → Church Balance**

- **Current Balance Widget**: Displays your church's current financial balance
- **Add Transaction Form**: Record new income or expenses with categories
- **Transaction History**: View and filter past transactions
- **Summary Statistics**: See totals for income, expenses, and net change

### 📝 **Adding Transactions**

1. **Select Transaction Type**:
   - **Income**: Money coming into the church (donations, rentals, etc.)
   - **Expense**: Money going out (utilities, supplies, etc.)
   - **Transfer**: Moving money between accounts
   - **Adjustment**: Corrections or reconciliation entries

2. **Choose Category**: Select from predefined categories or create new ones

3. **Enter Details**:
   - Date of transaction
   - Amount (always positive - the type determines if it's added or subtracted)
   - Description (required)
   - Associated fund (optional)
   - Notes (optional)

### 🏷️ **Managing Categories**
Navigate to: **Deposit Menu → Admin → Balance Categories** (Admin only)

- **Add Categories**: Create new income or expense categories
- **Edit Existing**: Modify names, descriptions, and order
- **Active/Inactive**: Enable or disable categories without deleting them
- **Reorder**: Set display order for better organization

### 📊 **Generating Reports**
Navigate to: **Deposit Menu → Deposit Reports → Church Balance Report**

1. Select date range for the report
2. Choose output format (PDF or CSV)
3. Generate report with:
   - Detailed transaction list
   - Summary totals
   - Balance progression over time

## Default Categories

The system comes with these pre-configured categories:

### **Income Categories**
- Donations (Regular donations and offerings)
- Special Offerings (Special collections)
- Fundraising (Events and activities)
- Rent/Facility Income (Building rental fees)
- Other Income (Miscellaneous income)

### **Expense Categories**
- Utilities (Electricity, water, gas, internet, phone)
- Building Maintenance (Repairs and improvements)
- Staff Salaries (Pastor and staff compensation)
- Ministry Expenses (Programs and materials)
- Office Expenses (Supplies, printing, postage)
- Insurance (Building and liability insurance)
- Other Expenses (Miscellaneous expenses)

## Technical Details

### 🗄️ **Database Tables**

**`church_balance_cb`** - Main transactions table
- Stores all financial transactions
- Tracks running balance
- Links to funds and deposits
- Maintains audit trail

**`church_balance_categories_cbc`** - Categories table
- Manages income/expense categories
- Supports ordering and active/inactive status
- Prevents deletion of categories in use

### 🔐 **Security & Permissions**

- **Finance Permission**: Required to view and add transactions
- **Admin Permission**: Required to manage categories
- **Input Validation**: All user inputs are sanitized and validated
- **SQL Injection Protection**: Uses parameterized queries
- **Audit Trail**: Tracks creation and modification of all records

### 📱 **User Interface**

- **Responsive Design**: Works on desktop, tablet, and mobile
- **Bootstrap Integration**: Matches ChurchCRM's existing styling
- **AJAX Filtering**: Dynamic category filtering based on transaction type
- **Date Pickers**: Easy date selection for transactions and reports
- **Form Validation**: Client-side and server-side validation

## Troubleshooting

### Common Issues

**"Permission Denied" Error**
- Ensure user has Finance permissions in ChurchCRM
- Check that the user is logged in properly

**"Category Not Found" Error**
- Verify that balance categories were installed correctly
- Run the database installation script again if needed

**Balance Calculations Seem Wrong**
- Check for duplicate transactions
- Verify that transaction types (Income/Expense) are correct
- Review the transaction history for any adjustments

**Reports Not Generating**
- Ensure the Reports directory is writable
- Check that the date range contains transactions
- Verify the ChurchInfoReport class is available

### 🔍 **Database Maintenance**

**Recalculate Balances** (if needed):
```sql
-- This query recalculates running balances
SET @balance = 0;
UPDATE church_balance_cb 
SET cb_Balance = (@balance := @balance + 
    CASE 
        WHEN cb_Type = 'Income' THEN cb_Amount 
        ELSE -cb_Amount 
    END
)
ORDER BY cb_Date, cb_ID;
```

**Check for Data Integrity**:
```sql
-- Verify category references
SELECT cb.*, cbc.cbc_Name 
FROM church_balance_cb cb 
LEFT JOIN church_balance_categories_cbc cbc ON cb.cb_Category = cbc.cbc_Name 
WHERE cbc.cbc_Name IS NULL;
```

## Support

For support with the Church Balance functionality:

1. **Check Logs**: Review ChurchCRM error logs for specific error messages
2. **Database Access**: Ensure proper database permissions
3. **File Permissions**: Verify web server can read/write necessary files
4. **ChurchCRM Version**: Confirm compatibility with your ChurchCRM version

## Future Enhancements

Potential features for future versions:
- **Budget Planning**: Set and track budgets by category
- **Recurring Transactions**: Automate regular income/expenses
- **Multi-Account Support**: Track multiple bank accounts
- **Financial Dashboards**: Enhanced reporting with charts and graphs
- **Import/Export**: Bulk transaction import from bank statements
- **Approval Workflow**: Multi-step approval for large transactions

---

**Version**: 1.0  
**Compatibility**: ChurchCRM 4.x+  
**License**: Same as ChurchCRM  
**Author**: ChurchCRM Community
