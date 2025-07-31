<?php
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;

// Security: User must be logged in
if (!AuthenticationManager::getCurrentUser()) {
    Redirect('Login.php');
    exit;
}

// Get parameters
$output = $_GET['output'] ?? 'pdf';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$type = $_GET['type'] ?? '';

// Build SQL query
$whereClause = "1 = 1";
$params = [];

if (!empty($startDate)) {
    $whereClause .= " AND cb_Date >= ?";
    $params[] = $startDate;
}

if (!empty($endDate)) {
    $whereClause .= " AND cb_Date <= ?";
    $params[] = $endDate;
}

if (!empty($type) && in_array($type, ['Income', 'Expense'])) {
    $whereClause .= " AND cb_Type = ?";
    $params[] = $type;
}

$sSQL = "SELECT cb.*, u.usr_FirstName, u.usr_LastName, df.fun_Name 
         FROM church_balance_cb cb 
         LEFT JOIN user_usr u ON cb.cb_CreatedBy = u.usr_per_ID 
         LEFT JOIN donationfund_fun df ON cb.cb_FundID = df.fun_ID 
         WHERE $whereClause 
         ORDER BY cb_Date DESC, cb_ID DESC";

$result = RunQuery($sSQL);

// Generate filename
$filename = 'church_balance_' . date('Y-m-d');
if (!empty($startDate) || !empty($endDate)) {
    $filename .= '_' . ($startDate ?: 'all') . '_to_' . ($endDate ?: 'all');
}

if ($output === 'csv') {
    generateCSV($result, $filename);
} elseif ($output === 'excel') {
    generateExcel($result, $filename);
} else {
    generatePDF($result, $filename);
}

function generateCSV($result, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Write BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write header
    fputcsv($output, [
        'Data',
        'Tipo',
        'Categoria', 
        'Descrizione',
        'Importo',
        'Saldo',
        'Fondo',
        'Creato da',
        'Note'
    ]);
    
    // Write data
    while ($row = mysqli_fetch_array($result)) {
        fputcsv($output, [
            $row['cb_Date'],
            $row['cb_Type'] === 'Income' ? 'Entrata' : 'Uscita',
            $row['cb_Category'],
            $row['cb_Description'],
            number_format($row['cb_Amount'], 2),
            number_format($row['cb_Balance'], 2),
            $row['fun_Name'] ?: '',
            ($row['usr_FirstName'] . ' ' . $row['usr_LastName']),
            $row['cb_Notes']
        ]);
    }
    
    fclose($output);
    exit;
}

function generateExcel($result, $filename) {
    // Simple Excel generation using HTML table with Excel MIME type
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
    echo '<style>';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th, td { border: 1px solid #000; padding: 8px; text-align: left; }';
    echo 'th { background-color: #f2f2f2; font-weight: bold; }';
    echo '.number { text-align: right; }';
    echo '.positive { color: green; }';
    echo '.negative { color: red; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<h2>Bilancio Chiesa - Report</h2>';
    echo '<p>Generato il: ' . date('d/m/Y H:i:s') . '</p>';
    
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Data</th>';
    echo '<th>Tipo</th>';
    echo '<th>Categoria</th>';
    echo '<th>Descrizione</th>';
    echo '<th>Importo</th>';
    echo '<th>Saldo</th>';
    echo '<th>Fondo</th>';
    echo '<th>Creato da</th>';
    echo '<th>Note</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    $totalIncome = 0;
    $totalExpense = 0;
    
    while ($row = mysqli_fetch_array($result)) {
        if ($row['cb_Type'] === 'Income') {
            $totalIncome += $row['cb_Amount'];
        } else {
            $totalExpense += $row['cb_Amount'];
        }
        
        echo '<tr>';
        echo '<td>' . date('d/m/Y', strtotime($row['cb_Date'])) . '</td>';
        echo '<td>' . ($row['cb_Type'] === 'Income' ? 'Entrata' : 'Uscita') . '</td>';
        echo '<td>' . htmlspecialchars($row['cb_Category']) . '</td>';
        echo '<td>' . htmlspecialchars($row['cb_Description']) . '</td>';
        echo '<td class="number ' . ($row['cb_Type'] === 'Income' ? 'positive' : 'negative') . '">';
        echo '€ ' . number_format($row['cb_Amount'], 2, ',', '.');
        echo '</td>';
        echo '<td class="number">€ ' . number_format($row['cb_Balance'], 2, ',', '.') . '</td>';
        echo '<td>' . htmlspecialchars($row['fun_Name'] ?: '') . '</td>';
        echo '<td>' . htmlspecialchars($row['usr_FirstName'] . ' ' . $row['usr_LastName']) . '</td>';
        echo '<td>' . htmlspecialchars($row['cb_Notes']) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '<tfoot>';
    echo '<tr style="background-color: #e6f3ff; font-weight: bold;">';
    echo '<td colspan="4">Totali</td>';
    echo '<td class="number positive">Entrate: € ' . number_format($totalIncome, 2, ',', '.') . '</td>';
    echo '<td class="number negative">Uscite: € ' . number_format($totalExpense, 2, ',', '.') . '</td>';
    echo '<td class="number">Bilancio: € ' . number_format($totalIncome - $totalExpense, 2, ',', '.') . '</td>';
    echo '<td colspan="2"></td>';
    echo '</tr>';
    echo '</tfoot>';
    echo '</table>';
    
    echo '</body>';
    echo '</html>';
    exit;
}

function generatePDF($result, $filename) {
    require_once('Include/ReportFunctions.php');
    
    class PDF extends ChurchInfoReportTCPDF {
        function Header() {
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 10, 'Bilancio Chiesa - Report', 0, 1, 'C');
            $this->Cell(0, 10, 'Generato il: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
            $this->Ln(5);
        }
    }
    
    $pdf = new PDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 10);
    
    // Table header
    $pdf->Cell(25, 7, 'Data', 1);
    $pdf->Cell(20, 7, 'Tipo', 1);
    $pdf->Cell(30, 7, 'Categoria', 1);
    $pdf->Cell(40, 7, 'Descrizione', 1);
    $pdf->Cell(25, 7, 'Importo', 1);
    $pdf->Cell(25, 7, 'Saldo', 1);
    $pdf->Cell(25, 7, 'Fondo', 1);
    $pdf->Ln();
    
    $pdf->SetFont('Arial', '', 8);
    
    while ($row = mysqli_fetch_array($result)) {
        $pdf->Cell(25, 6, date('d/m/Y', strtotime($row['cb_Date'])), 1);
        $pdf->Cell(20, 6, $row['cb_Type'] === 'Income' ? 'Entrata' : 'Uscita', 1);
        $pdf->Cell(30, 6, substr($row['cb_Category'], 0, 15), 1);
        $pdf->Cell(40, 6, substr($row['cb_Description'], 0, 20), 1);
        $pdf->Cell(25, 6, '€ ' . number_format($row['cb_Amount'], 2), 1);
        $pdf->Cell(25, 6, '€ ' . number_format($row['cb_Balance'], 2), 1);
        $pdf->Cell(25, 6, substr($row['fun_Name'] ?: '', 0, 12), 1);
        $pdf->Ln();
    }
    
    $pdf->Output($filename . '.pdf', 'D');
    exit;
}
?>
