<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have Finance permission
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isFinanceEnabled());

$sPageTitle = gettext('Bilancio Chiesa');

require 'Include/Header.php';

// Handle form submissions
$action = '';
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = InputUtils::filterString($_POST['action']);
        
        if ($action === 'add_transaction') {
            $date = InputUtils::filterString($_POST['date']);
            $type = InputUtils::filterString($_POST['type']);
            $category = InputUtils::filterString($_POST['category']);
            $description = InputUtils::filterString($_POST['description']);
            $amount = InputUtils::filterFloat($_POST['amount']);
            $notes = InputUtils::filterString($_POST['notes']);
            $fundId = !empty($_POST['fund_id']) ? InputUtils::filterInt($_POST['fund_id']) : null;
            
            // Validate input
            if (empty($date) || empty($type) || empty($category) || empty($description) || $amount <= 0) {
                $message = gettext('Compila tutti i campi obbligatori con valori validi.');
                $messageType = 'error';
            } else {
                // Get current balance
                $currentBalance = getCurrentChurchBalance();
                
                // Calculate new balance
                if ($type === 'Income') {
                    $newBalance = $currentBalance + $amount;
                } else {
                    $newBalance = $currentBalance - $amount;
                }
                
                // Insert transaction
                $sSQL = "INSERT INTO church_balance_cb (cb_Date, cb_Type, cb_Category, cb_Description, cb_Amount, cb_Balance, cb_FundID, cb_CreatedBy, cb_Notes) 
                         VALUES ('" . $date . "', '" . $type . "', '" . $category . "', '" . addslashes($description) . "', " . $amount . ", " . $newBalance . ", " . 
                         ($fundId ? $fundId : 'NULL') . ", " . AuthenticationManager::getCurrentUser()->getId() . ", '" . addslashes($notes) . "')";
                
                if (RunQuery($sSQL)) {
                    $message = gettext('Transazione aggiunta con successo.');
                    $messageType = 'success';
                } else {
                    $message = gettext('Errore nell\'aggiunta della transazione.');
                    $messageType = 'error';
                }
            }
        } elseif ($action === 'import_csv') {
            // Handle CSV import
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                $csvFile = $_FILES['csv_file']['tmp_name'];
                $importResults = importTransactionsFromCSV($csvFile);
                $message = $importResults['message'];
                $messageType = $importResults['type'];
            } else {
                $message = gettext('Errore nel caricamento del file CSV.');
                $messageType = 'error';
            }
        }
    }
}

// Get filter parameters
$dateFrom = isset($_GET['date_from']) ? InputUtils::filterString($_GET['date_from']) : date('Y-m-01');
$dateTo = isset($_GET['date_to']) ? InputUtils::filterString($_GET['date_to']) : date('Y-m-d');
$typeFilter = isset($_GET['type_filter']) ? InputUtils::filterString($_GET['type_filter']) : '';
$categoryFilter = isset($_GET['category_filter']) ? InputUtils::filterString($_GET['category_filter']) : '';

// Helper function to get current church balance
function getCurrentChurchBalance() {
    $sSQL = "SELECT cb_Balance FROM church_balance_cb ORDER BY cb_ID DESC LIMIT 1";
    $result = RunQuery($sSQL);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_array($result);
        return floatval($row['cb_Balance']);
    }
    return 0.00;
}

// Function to import transactions from CSV
function importTransactionsFromCSV($csvFile) {
    $imported = 0;
    $errors = 0;
    $errorMessages = [];
    
    if (($handle = fopen($csvFile, "r")) !== FALSE) {
        // Skip header row
        $header = fgetcsv($handle);
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            // Expected CSV format: Date, Type, Category, Description, Amount, Notes, Fund
            if (count($data) >= 5) {
                $date = trim($data[0]);
                $type = trim($data[1]);
                $category = trim($data[2]);
                $description = trim($data[3]);
                $amount = floatval($data[4]);
                $notes = isset($data[5]) ? trim($data[5]) : '';
                $fundName = isset($data[6]) ? trim($data[6]) : '';
                
                // Validate data
                if (empty($date) || empty($type) || empty($category) || empty($description) || $amount <= 0) {
                    $errors++;
                    $errorMessages[] = "Riga saltata: dati mancanti o non validi";
                    continue;
                }
                
                // Convert date format if needed
                $dateObj = DateTime::createFromFormat('Y-m-d', $date);
                if (!$dateObj) {
                    $dateObj = DateTime::createFromFormat('d/m/Y', $date);
                }
                if (!$dateObj) {
                    $errors++;
                    $errorMessages[] = "Formato data non valido: $date";
                    continue;
                }
                $date = $dateObj->format('Y-m-d');
                
                // Validate type
                if (!in_array($type, ['Income', 'Expense', 'Entrata', 'Uscita'])) {
                    $errors++;
                    $errorMessages[] = "Tipo transazione non valido: $type";
                    continue;
                }
                
                // Normalize type to English for database
                if ($type === 'Entrata') $type = 'Income';
                if ($type === 'Uscita') $type = 'Expense';
                
                // Find fund ID if fund name provided
                $fundId = null;
                if (!empty($fundName)) {
                    $sSQL = "SELECT fun_ID FROM donationfund_fun WHERE fun_Name = '" . addslashes($fundName) . "'";
                    $result = RunQuery($sSQL);
                    if ($row = mysqli_fetch_assoc($result)) {
                        $fundId = $row['fun_ID'];
                    }
                }
                
                // Get current balance
                $currentBalance = getCurrentChurchBalance();
                
                // Calculate new balance
                if ($type === 'Income') {
                    $newBalance = $currentBalance + $amount;
                } else {
                    $newBalance = $currentBalance - $amount;
                }
                
                // Insert transaction
                $sSQL = "INSERT INTO church_balance_cb (cb_Date, cb_Type, cb_Category, cb_Description, cb_Amount, cb_Balance, cb_FundID, cb_CreatedBy, cb_Notes) 
                         VALUES ('" . $date . "', '" . $type . "', '" . addslashes($category) . "', '" . addslashes($description) . "', " . $amount . ", " . $newBalance . ", " . 
                         ($fundId ? $fundId : 'NULL') . ", " . AuthenticationManager::getCurrentUser()->getId() . ", '" . addslashes($notes) . "')";
                
                if (RunQuery($sSQL)) {
                    $imported++;
                } else {
                    $errors++;
                    $errorMessages[] = "Errore nell'inserimento della transazione: $description";
                }
            } else {
                $errors++;
                $errorMessages[] = "Riga saltata: formato non valido";
            }
        }
        fclose($handle);
    } else {
        return ['message' => gettext('Impossibile aprire il file CSV.'), 'type' => 'error'];
    }
    
    $message = "Importate $imported transazioni";
    if ($errors > 0) {
        $message .= ", $errors errori riscontrati";
        if (!empty($errorMessages)) {
            $message .= ": " . implode(', ', array_slice($errorMessages, 0, 3));
            if (count($errorMessages) > 3) {
                $message .= "...";
            }
        }
    }
    
    return ['message' => $message, 'type' => $imported > 0 ? 'success' : 'error'];
}
?>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-money"></i>
                    Bilancio Chiesa
                </h3>
            </div>
            <div class="card-body">
                
                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType === 'error' ? 'danger' : 'success' ?>" role="alert">
                        <?= $message ?>
                    </div>
                <?php endif; ?>

                <!-- Current Balance Display -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="info-box bg-green">
                            <span class="info-box-icon"><i class="fa fa-money"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Saldo Corrente Chiesa</span>
                                <span class="info-box-number">
                                    €<?= number_format(getCurrentChurchBalance(), 2, ',', '.') ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Import/Export Section -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h4><i class="fa fa-upload"></i> Importa da CSV</h4>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="ChurchBalance.php" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="import_csv">
                                    <div class="form-group">
                                        <label>File CSV</label>
                                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                                        <small class="form-text text-muted">
                                            Formato: Data,Tipo,Categoria,Descrizione,Importo,Note,Fondo<br>
                                            Tipo: Income/Entrata o Expense/Uscita
                                        </small>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-upload"></i> Importa Transazioni
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h4><i class="fa fa-download"></i> Esporta Report</h4>
                            </div>
                            <div class="card-body">
                                <div class="btn-group" role="group">
                                    <a href="ChurchBalanceReport.php?output=csv" class="btn btn-success">
                                        <i class="fa fa-file-text-o"></i> Esporta CSV
                                    </a>
                                    <a href="ChurchBalanceReport.php?output=excel" class="btn btn-primary">
                                        <i class="fa fa-file-excel-o"></i> Esporta Excel
                                    </a>
                                    <a href="ChurchBalanceReport.php?output=pdf" class="btn btn-danger">
                                        <i class="fa fa-file-pdf-o"></i> Esporta PDF
                                    </a>
                                </div>
                                <hr>
                                <form method="GET" action="ChurchBalanceReport.php" style="margin-top: 10px;">
                                    <div class="row">
                                        <div class="col-md-5">
                                            <input type="date" name="start_date" class="form-control" placeholder="Data inizio">
                                        </div>
                                        <div class="col-md-5">
                                            <input type="date" name="end_date" class="form-control" placeholder="Data fine">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="submit" name="output" value="excel" class="btn btn-sm btn-primary">Filtra</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add New Transaction Form -->
                <div class="card">
                    <div class="card-header">
                        <h4>Aggiungi Nuova Transazione</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="ChurchBalance.php">
                            <input type="hidden" name="action" value="add_transaction">
                            
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Data *</label>
                                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Tipo *</label>
                                        <select name="type" class="form-control" id="transactionType" required>
                                            <option value="Income">Entrata</option>
                                            <option value="Expense">Uscita</option>
                                            <option value="Transfer">Trasferimento</option>
                                            <option value="Adjustment">Aggiustamento</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Categoria *</label>
                                        <select name="category" class="form-control" id="categorySelect" required>
                                            <option value="">Seleziona Categoria</option>
                                            <?php
                                            // Get categories
                                            $sSQL = "SELECT cbc_Name, cbc_Type FROM church_balance_categories_cbc WHERE cbc_Active = 1 ORDER BY cbc_Type, cbc_Order";
                                            $rsCategories = RunQuery($sSQL);
                                            $incomeCategories = [];
                                            $expenseCategories = [];
                                            
                                            while ($row = mysqli_fetch_array($rsCategories)) {
                                                if ($row['cbc_Type'] === 'Income') {
                                                    $incomeCategories[] = $row['cbc_Name'];
                                                } else {
                                                    $expenseCategories[] = $row['cbc_Name'];
                                                }
                                            }
                                            
                                            foreach ($incomeCategories as $category) {
                                                echo "<option value='" . htmlentities($category) . "' data-type='Income'>" . htmlentities($category) . "</option>";
                                            }
                                            foreach ($expenseCategories as $category) {
                                                echo "<option value='" . htmlentities($category) . "' data-type='Expense'>" . htmlentities($category) . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Importo *</label>
                                        <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Fondo</label>
                                        <select name="fund_id" class="form-control">
                                            <option value="">Nessuno</option>
                                            <?php
                                            $sSQL = "SELECT fun_ID, fun_Name FROM donationfund_fun WHERE fun_Active = 'true' ORDER BY fun_Name";
                                            $rsFunds = RunQuery($sSQL);
                                            while ($row = mysqli_fetch_array($rsFunds)) {
                                                echo "<option value='" . $row['fun_ID'] . "'>" . htmlentities($row['fun_Name']) . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Descrizione *</label>
                                        <input type="text" name="description" class="form-control" maxlength="255" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Note</label>
                                        <input type="text" name="notes" class="form-control" maxlength="255">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-plus"></i> Aggiungi Transazione
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Transaction History Filters -->
                <div class="card">
                    <div class="card-header">
                        <h4>Storico Transazioni</h4>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="ChurchBalance.php" class="form-inline">
                            <div class="form-group mr-3">
                                <label class="mr-2">Da:</label>
                                <input type="date" name="date_from" class="form-control" value="<?= $dateFrom ?>">
                            </div>
                            
                            <div class="form-group mr-3">
                                <label class="mr-2">A:</label>
                                <input type="date" name="date_to" class="form-control" value="<?= $dateTo ?>">
                            </div>
                            
                            <div class="form-group mr-3">
                                <label class="mr-2">Tipo:</label>
                                <select name="type_filter" class="form-control">
                                    <option value="">Tutti i Tipi</option>
                                    <option value="Income" <?= $typeFilter === 'Income' ? 'selected' : '' ?>>Entrata</option>
                                    <option value="Expense" <?= $typeFilter === 'Expense' ? 'selected' : '' ?>>Uscita</option>
                                    <option value="Transfer" <?= $typeFilter === 'Transfer' ? 'selected' : '' ?>>Trasferimento</option>
                                    <option value="Adjustment" <?= $typeFilter === 'Adjustment' ? 'selected' : '' ?>>Aggiustamento</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-filter"></i> Filtra
                            </button>
                            
                            <a href="ChurchBalance.php" class="btn btn-default ml-2">
                                <i class="fa fa-refresh"></i> Azzera
                            </a>
                        </form>
                    </div>
                </div>

                <!-- Transaction History Table -->
                <div class="card">
                    <div class="card-body">
                        <?php
                        // Build SQL query with filters
                        $sSQL = "SELECT cb.*, f.fun_Name, u.usr_FirstName, u.usr_LastName 
                                FROM church_balance_cb cb 
                                LEFT JOIN donationfund_fun f ON cb.cb_FundID = f.fun_ID 
                                LEFT JOIN user_usr u ON cb.cb_CreatedBy = u.usr_per_ID 
                                WHERE cb.cb_Date BETWEEN '" . $dateFrom . "' AND '" . $dateTo . "'";
                        
                        if (!empty($typeFilter)) {
                            $sSQL .= " AND cb.cb_Type = '" . $typeFilter . "'";
                        }
                        
                        if (!empty($categoryFilter)) {
                            $sSQL .= " AND cb.cb_Category = '" . addslashes($categoryFilter) . "'";
                        }
                        
                        $sSQL .= " ORDER BY cb.cb_Date DESC, cb.cb_ID DESC";
                        
                        $rsTransactions = RunQuery($sSQL);
                        $totalIncome = 0;
                        $totalExpenses = 0;
                        ?>
                        
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Tipo</th>
                                    <th>Categoria</th>
                                    <th>Descrizione</th>
                                    <th>Importo</th>
                                    <th>Saldo</th>
                                    <th>Fondo</th>
                                    <th>Creato da</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (mysqli_num_rows($rsTransactions) === 0) {
                                    echo "<tr><td colspan='8' class='text-center'>Nessuna transazione trovata per il periodo selezionato.</td></tr>";
                                } else {
                                    while ($row = mysqli_fetch_array($rsTransactions)) {
                                        $amountClass = $row['cb_Type'] === 'Income' ? 'text-success' : 'text-danger';
                                        $amountPrefix = $row['cb_Type'] === 'Income' ? '+' : '-';
                                        $typeDisplay = $row['cb_Type'] === 'Income' ? 'Entrata' : 'Uscita';
                                        
                                        if ($row['cb_Type'] === 'Income') {
                                            $totalIncome += $row['cb_Amount'];
                                        } else {
                                            $totalExpenses += $row['cb_Amount'];
                                        }
                                        
                                        echo "<tr>";
                                        echo "<td>" . date('d/m/Y', strtotime($row['cb_Date'])) . "</td>";
                                        echo "<td><span class='badge bg-" . ($row['cb_Type'] === 'Income' ? 'green' : 'red') . "'>" . $typeDisplay . "</span></td>";
                                        echo "<td>" . htmlentities($row['cb_Category']) . "</td>";
                                        echo "<td>" . htmlentities($row['cb_Description']) . "</td>";
                                        echo "<td class='" . $amountClass . "'>" . $amountPrefix . "€" . number_format($row['cb_Amount'], 2, ',', '.') . "</td>";
                                        echo "<td><strong>€" . number_format($row['cb_Balance'], 2, ',', '.') . "</strong></td>";
                                        echo "<td>" . ($row['fun_Name'] ? htmlentities($row['fun_Name']) : '-') . "</td>";
                                        echo "<td>" . htmlentities($row['usr_FirstName'] . ' ' . $row['usr_LastName']) . "</td>";
                                        echo "</tr>";
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                        
                        <?php if (mysqli_num_rows($rsTransactions) > 0): ?>
                            <div class="row mt-3">
                                <div class="col-md-4">
                                    <div class="info-box bg-green">
                                        <span class="info-box-icon"><i class="fa fa-arrow-up"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Totale Entrate</span>
                                            <span class="info-box-number">€<?= number_format($totalIncome, 2, ',', '.') ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="info-box bg-red">
                                        <span class="info-box-icon"><i class="fa fa-arrow-down"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Totale Uscite</span>
                                            <span class="info-box-number">€<?= number_format($totalExpenses, 2, ',', '.') ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="info-box bg-blue">
                                        <span class="info-box-icon"><i class="fa fa-calculator"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Variazione Netta</span>
                                            <span class="info-box-number">€<?= number_format($totalIncome - $totalExpenses, 2, ',', '.') ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function() {
    // Filter categories based on transaction type
    $('#transactionType').change(function() {
        var selectedType = $(this).val();
        var categorySelect = $('#categorySelect');
        
        categorySelect.find('option').each(function() {
            if ($(this).val() === '') {
                // Keep the "Select Category" option
                return;
            }
            
            var optionType = $(this).data('type');
            if (selectedType === 'Income' && optionType === 'Income') {
                $(this).show();
            } else if (selectedType === 'Expense' && optionType === 'Expense') {
                $(this).show();
            } else if (selectedType === 'Transfer' || selectedType === 'Adjustment') {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        
        // Reset category selection
        categorySelect.val('');
    });
    
    // Initialize the category filter on page load
    $('#transactionType').trigger('change');
});
</script>

<?php
require_once 'Include/Footer.php';
?>
