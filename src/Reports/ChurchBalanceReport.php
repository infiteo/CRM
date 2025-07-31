<?php

require_once '../Include/Config.php';
require_once '../Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Reports\ChurchInfoReport;

// Security
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isFinanceEnabled());

// Get report parameters
$dateStart = InputUtils::filterString($_POST['DateStart']);
$dateEnd = InputUtils::filterString($_POST['DateEnd']);
$output = InputUtils::filterString($_POST['output']);
$reportType = InputUtils::filterString($_POST['ReportType']);

if (empty($dateStart) || empty($dateEnd)) {
    header('Location: ../FinancialReports.php?ReturnMessage=InvalidDateRange&ReportType=Church%20Balance%20Report');
    exit;
}

class PdfChurchBalanceReport extends ChurchInfoReport
{
    // Constructor
    public function __construct()
    {
        parent::__construct('P', 'mm', $this->paperFormat);
        $this->SetFont('Times', '', 10);
        $this->SetMargins(20, 20);
        $this->SetAutoPageBreak(true, 25);
    }

    public function addPage()
    {
        $this->AddPage();
        $this->SetFont('Times', 'B', 16);
        $this->WriteAt(SystemConfig::getValue('leftX'), SystemConfig::getValue('incrementY'), SystemConfig::getValue('sChurchName'));
        $this->SetFont('Times', 'B', 12);
        $this->WriteAt(SystemConfig::getValue('leftX'), SystemConfig::getValue('incrementY') * 2, gettext('Church Balance Report'));
        $this->SetFont('Times', '', 10);
    }
}

// Build SQL query
$sSQL = "SELECT cb.*, f.fun_Name, u.usr_FirstName, u.usr_LastName 
         FROM church_balance_cb cb 
         LEFT JOIN donationfund_fun f ON cb.cb_FundID = f.fun_ID 
         LEFT JOIN user_usr u ON cb.cb_CreatedBy = u.usr_per_ID 
         WHERE cb.cb_Date BETWEEN '" . $dateStart . "' AND '" . $dateEnd . "' 
         ORDER BY cb.cb_Date, cb.cb_ID";

$rsReport = RunQuery($sSQL);

if (mysqli_num_rows($rsReport) === 0) {
    header('Location: ../FinancialReports.php?ReturnMessage=NoRows&ReportType=Church%20Balance%20Report');
    exit;
}

if ($output === 'csv') {
    // CSV Export
    $delimiter = ',';
    $eol = "\r\n";
    
    // Set headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="ChurchBalanceReport-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output headers
    echo '"Date"' . $delimiter;
    echo '"Type"' . $delimiter;
    echo '"Category"' . $delimiter;
    echo '"Description"' . $delimiter;
    echo '"Amount"' . $delimiter;
    echo '"Balance"' . $delimiter;
    echo '"Fund"' . $delimiter;
    echo '"Created By"' . $delimiter;
    echo '"Notes"' . $eol;
    
    // Output data
    while ($aRow = mysqli_fetch_array($rsReport)) {
        echo '"' . $aRow['cb_Date'] . '"' . $delimiter;
        echo '"' . $aRow['cb_Type'] . '"' . $delimiter;
        echo '"' . str_replace('"', '""', $aRow['cb_Category']) . '"' . $delimiter;
        echo '"' . str_replace('"', '""', $aRow['cb_Description']) . '"' . $delimiter;
        echo '"' . $aRow['cb_Amount'] . '"' . $delimiter;
        echo '"' . $aRow['cb_Balance'] . '"' . $delimiter;
        echo '"' . ($aRow['fun_Name'] ? str_replace('"', '""', $aRow['fun_Name']) : '') . '"' . $delimiter;
        echo '"' . str_replace('"', '""', $aRow['usr_FirstName'] . ' ' . $aRow['usr_LastName']) . '"' . $delimiter;
        echo '"' . str_replace('"', '""', $aRow['cb_Notes']) . '"' . $eol;
    }
    
} else {
    // PDF Export
    $pdf = new PdfChurchBalanceReport();
    $pdf->addPage();
    
    $curY = 40;
    $summaryIntervalY = 4;
    
    // Report period
    $pdf->SetFont('Times', 'B', 10);
    $pdf->WriteAt(20, $curY, gettext('Report Period') . ': ' . date('M j, Y', strtotime($dateStart)) . ' - ' . date('M j, Y', strtotime($dateEnd)));
    $curY += $summaryIntervalY * 2;
    
    // Table headers
    $pdf->SetFont('Times', 'B', 9);
    $pdf->WriteAt(20, $curY, gettext('Date'));
    $pdf->WriteAt(40, $curY, gettext('Type'));
    $pdf->WriteAt(60, $curY, gettext('Category'));
    $pdf->WriteAt(100, $curY, gettext('Description'));
    $pdf->WriteAt(150, $curY, gettext('Amount'));
    $pdf->WriteAt(175, $curY, gettext('Balance'));
    $curY += $summaryIntervalY;
    
    // Draw line under headers
    $pdf->Line(20, $curY, 200, $curY);
    $curY += $summaryIntervalY;
    
    $pdf->SetFont('Times', '', 8);
    
    $totalIncome = 0;
    $totalExpenses = 0;
    $page = 1;
    
    // Reset the result pointer
    mysqli_data_seek($rsReport, 0);
    
    while ($aRow = mysqli_fetch_array($rsReport)) {
        // Check if we need a new page
        if ($curY > 250) {
            $pdf->addPage();
            $curY = 40;
            
            // Redraw headers
            $pdf->SetFont('Times', 'B', 9);
            $pdf->WriteAt(20, $curY, gettext('Date'));
            $pdf->WriteAt(40, $curY, gettext('Type'));
            $pdf->WriteAt(60, $curY, gettext('Category'));
            $pdf->WriteAt(100, $curY, gettext('Description'));
            $pdf->WriteAt(150, $curY, gettext('Amount'));
            $pdf->WriteAt(175, $curY, gettext('Balance'));
            $curY += $summaryIntervalY;
            $pdf->Line(20, $curY, 200, $curY);
            $curY += $summaryIntervalY;
            $pdf->SetFont('Times', '', 8);
        }
        
        // Format date
        $date = date('M j', strtotime($aRow['cb_Date']));
        
        // Truncate long text fields
        $category = strlen($aRow['cb_Category']) > 20 ? substr($aRow['cb_Category'], 0, 17) . '...' : $aRow['cb_Category'];
        $description = strlen($aRow['cb_Description']) > 30 ? substr($aRow['cb_Description'], 0, 27) . '...' : $aRow['cb_Description'];
        
        // Format amount with + or - prefix
        $amountPrefix = $aRow['cb_Type'] === 'Income' ? '+' : '-';
        $formattedAmount = $amountPrefix . '$' . number_format($aRow['cb_Amount'], 2);
        
        // Track totals
        if ($aRow['cb_Type'] === 'Income') {
            $totalIncome += $aRow['cb_Amount'];
        } else {
            $totalExpenses += $aRow['cb_Amount'];
        }
        
        // Output row
        $pdf->WriteAt(20, $curY, $date);
        $pdf->WriteAt(40, $curY, $aRow['cb_Type']);
        $pdf->WriteAt(60, $curY, $category);
        $pdf->WriteAt(100, $curY, $description);
        $pdf->printRightJustified(168, $curY, $formattedAmount);
        $pdf->printRightJustified(195, $curY, '$' . number_format($aRow['cb_Balance'], 2));
        
        $curY += $summaryIntervalY;
    }
    
    // Summary section
    $curY += $summaryIntervalY * 2;
    $pdf->Line(20, $curY, 200, $curY);
    $curY += $summaryIntervalY;
    
    $pdf->SetFont('Times', 'B', 10);
    $pdf->WriteAt(20, $curY, gettext('Summary'));
    $curY += $summaryIntervalY * 1.5;
    
    $pdf->SetFont('Times', '', 10);
    $pdf->WriteAt(20, $curY, gettext('Total Income') . ':');
    $pdf->printRightJustified(100, $curY, '$' . number_format($totalIncome, 2));
    $curY += $summaryIntervalY;
    
    $pdf->WriteAt(20, $curY, gettext('Total Expenses') . ':');
    $pdf->printRightJustified(100, $curY, '$' . number_format($totalExpenses, 2));
    $curY += $summaryIntervalY;
    
    $pdf->Line(20, $curY, 100, $curY);
    $curY += $summaryIntervalY / 2;
    
    $pdf->SetFont('Times', 'B', 10);
    $netChange = $totalIncome - $totalExpenses;
    $pdf->WriteAt(20, $curY, gettext('Net Change') . ':');
    $pdf->printRightJustified(100, $curY, '$' . number_format($netChange, 2));
    
    // Output PDF
    $pdf->Output('ChurchBalanceReport-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'D');
}
?>
