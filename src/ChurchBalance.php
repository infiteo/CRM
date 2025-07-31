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
$dateFrom = isset($_GET['date_from']) ? InputUtils::filterString($_GET['date_from']) : date('Y-01-01'); // Inizio anno invece del mese
$dateTo = isset($_GET['date_to']) ? InputUtils::filterString($_GET['date_to']) : date('Y-m-d');
$typeFilter = isset($_GET['type_filter']) ? InputUtils::filterString($_GET['type_filter']) : '';
$categoryFilter = isset($_GET['category_filter']) ? InputUtils::filterString($_GET['category_filter']) : '';

// Helper function to get current church balance
function getCurrentChurchBalance() {
    // First check if table exists
    $tableCheck = RunQuery("SHOW TABLES LIKE 'church_balance_cb'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
        return 0.00; // Table doesn't exist yet
    }
    
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

                <?php
                // Check if tables exist and show warning if not
                $tableCheck = RunQuery("SHOW TABLES LIKE 'church_balance_cb'");
                $categoriesCheck = RunQuery("SHOW TABLES LIKE 'church_balance_categories_cbc'");
                if (!$tableCheck || mysqli_num_rows($tableCheck) === 0 || !$categoriesCheck || mysqli_num_rows($categoriesCheck) === 0): ?>
                    <div class="alert alert-warning" role="alert">
                        <h4><i class="fa fa-exclamation-triangle"></i> Setup Necessario</h4>
                        <p>Le tabelle del database per il Bilancio Chiesa non sono ancora state create.</p>
                        <p>Per utilizzare questa funzionalità, è necessario installare le tabelle del database:</p>
                        <ol>
                            <li>Esegui il file <code>church-balance-safe.sql</code> nel tuo database MySQL/MariaDB</li>
                            <li>Oppure usa il comando: <code>mysql -u username -p database_name &lt; church-balance-safe.sql</code></li>
                        </ol>
                        <p><a href="debug_balance.php" class="btn btn-info"><i class="fa fa-stethoscope"></i> Diagnosi Database</a></p>
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
                                            // Get categories - check if table exists first
                                            $categoriesTableCheck = RunQuery("SHOW TABLES LIKE 'church_balance_categories_cbc'");
                                            if ($categoriesTableCheck && mysqli_num_rows($categoriesTableCheck) > 0) {
                                                $sSQL = "SELECT cbc_Name, cbc_Type FROM church_balance_categories_cbc WHERE cbc_Active = 1 ORDER BY cbc_Type, cbc_Order";
                                                $rsCategories = RunQuery($sSQL);
                                                $incomeCategories = [];
                                                $expenseCategories = [];
                                                
                                                if ($rsCategories && mysqli_num_rows($rsCategories) > 0) {
                                                    while ($row = mysqli_fetch_array($rsCategories)) {
                                                        if ($row['cbc_Type'] === 'Income') {
                                                            $incomeCategories[] = $row['cbc_Name'];
                                                        } else {
                                                            $expenseCategories[] = $row['cbc_Name'];
                                                        }
                                                    }
                                                } else {
                                                    // Default categories if table is empty
                                                    $incomeCategories = ['Offerte Domenicali', 'Donazioni Speciali', 'Eventi Fundraising', 'Altre Entrate'];
                                                    $expenseCategories = ['Utenze', 'Manutenzione Edificio', 'Stipendi Staff', 'Altre Spese'];
                                                }
                                                
                                                foreach ($incomeCategories as $category) {
                                                    echo "<option value='" . htmlentities($category) . "' data-type='Income'>" . htmlentities($category) . "</option>";
                                                }
                                                foreach ($expenseCategories as $category) {
                                                    echo "<option value='" . htmlentities($category) . "' data-type='Expense'>" . htmlentities($category) . "</option>";
                                                }
                                            } else {
                                                // Table doesn't exist - show default categories
                                                echo "<option value='Offerte Domenicali' data-type='Income'>Offerte Domenicali</option>";
                                                echo "<option value='Donazioni Speciali' data-type='Income'>Donazioni Speciali</option>";
                                                echo "<option value='Eventi Fundraising' data-type='Income'>Eventi Fundraising</option>";
                                                echo "<option value='Altre Entrate' data-type='Income'>Altre Entrate</option>";
                                                echo "<option value='Utenze' data-type='Expense'>Utenze</option>";
                                                echo "<option value='Manutenzione Edificio' data-type='Expense'>Manutenzione Edificio</option>";
                                                echo "<option value='Stipendi Staff' data-type='Expense'>Stipendi Staff</option>";
                                                echo "<option value='Altre Spese' data-type='Expense'>Altre Spese</option>";
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
                        <h4>Storico Transazioni 
                            <?php if (isset($_GET['show_all'])): ?>
                                <span class="badge badge-info">Mostra Tutto</span>
                            <?php endif; ?>
                        </h4>
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
                            
                            <a href="ChurchBalance.php?show_all=1" class="btn btn-info ml-2">
                                <i class="fa fa-list"></i> Mostra Tutto
                            </a>
                        </form>
                    </div>
                </div>

                <!-- Transaction History Table -->
                <div class="card">
                    <div class="card-body">
                        <?php
                        // Check if table exists before building SQL query
                        $transactionsTableCheck = RunQuery("SHOW TABLES LIKE 'church_balance_cb'");
                        if ($transactionsTableCheck && mysqli_num_rows($transactionsTableCheck) > 0) {
                            // Build SQL query with filters - SEMPLIFICATA SENZA JOIN
                            $sSQL = "SELECT cb.* FROM church_balance_cb cb ";
                            
                            // Se non è richiesto "mostra tutto", applica filtri di data
                            if (!isset($_GET['show_all'])) {
                                $sSQL .= " WHERE cb.cb_Date BETWEEN '" . $dateFrom . "' AND '" . $dateTo . "'";
                                
                                if (!empty($typeFilter)) {
                                    $sSQL .= " AND cb.cb_Type = '" . $typeFilter . "'";
                                }
                                
                                if (!empty($categoryFilter)) {
                                    $sSQL .= " AND cb.cb_Category = '" . addslashes($categoryFilter) . "'";
                                }
                            }
                            
                            $sSQL .= " ORDER BY cb.cb_Date DESC, cb.cb_ID DESC";
                            
                            $rsTransactions = RunQuery($sSQL);
                            $totalIncome = 0;
                            $totalExpenses = 0;
                            
                            // Debug: controllare se ci sono transazioni
                            $debugCountSQL = "SELECT COUNT(*) as total FROM church_balance_cb";
                            $debugResult = RunQuery($debugCountSQL);
                            $debugRow = mysqli_fetch_array($debugResult);
                            $totalTransactions = $debugRow['total'];
                        } else {
                            // Table doesn't exist - create empty result
                            $rsTransactions = false;
                            $totalIncome = 0;
                            $totalExpenses = 0;
                        }
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
                                if (!$rsTransactions || mysqli_num_rows($rsTransactions) === 0) {
                                    if (!$rsTransactions) {
                                        echo "<tr><td colspan='8' class='text-center'><strong>Tabelle del database non ancora create.</strong><br>";
                                        echo "Per utilizzare il sistema Bilancio Chiesa, devi prima installare le tabelle del database.<br>";
                                        echo "Esegui il file <code>church-balance-safe.sql</code> nel tuo database.<br>";
                                        echo "<a href='debug_balance.php' class='btn btn-sm btn-info'>Diagnosi Database</a></td></tr>";
                                    } else {
                                        echo "<tr><td colspan='8' class='text-center'>";
                                        echo "<strong>Nessuna transazione trovata per il periodo selezionato.</strong><br>";
                                        if (isset($totalTransactions)) {
                                            echo "Totale transazioni nel database: " . $totalTransactions . "<br>";
                                            echo "Periodo ricerca: " . $dateFrom . " - " . $dateTo . "<br>";
                                            if ($totalTransactions > 0) {
                                                echo "<em>Prova ad espandere il range di date o rimuovere i filtri.</em><br>";
                                                
                                                // Debug: mostra le categorie che esistono nel database
                                                $categoriesUsedSQL = "SELECT DISTINCT cb_Category FROM church_balance_cb LIMIT 5";
                                                $categoriesUsedResult = RunQuery($categoriesUsedSQL);
                                                if ($categoriesUsedResult && mysqli_num_rows($categoriesUsedResult) > 0) {
                                                    echo "<small><strong>Categorie presenti nel database:</strong> ";
                                                    $categories = [];
                                                    while ($catRow = mysqli_fetch_array($categoriesUsedResult)) {
                                                        $categories[] = $catRow['cb_Category'];
                                                    }
                                                    echo implode(', ', $categories) . "</small><br>";
                                                }
                                                
                                                // Debug: mostra le date delle transazioni
                                                $datesSQL = "SELECT MIN(cb_Date) as min_date, MAX(cb_Date) as max_date FROM church_balance_cb";
                                                $datesResult = RunQuery($datesSQL);
                                                if ($datesResult && mysqli_num_rows($datesResult) > 0) {
                                                    $datesRow = mysqli_fetch_array($datesResult);
                                                    echo "<small><strong>Date transazioni:</strong> da " . $datesRow['min_date'] . " a " . $datesRow['max_date'] . "</small>";
                                                }
                                            }
                                        }
                                        echo "</td></tr>";
                                    }
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
                                        echo "<td>" . ($row['cb_FundID'] ? 'Fondo ID: ' . $row['cb_FundID'] : '-') . "</td>";
                                        echo "<td>" . ($row['cb_CreatedBy'] ? 'User ID: ' . $row['cb_CreatedBy'] : '-') . "</td>";
                                        echo "</tr>";
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                        
                        <?php if ($rsTransactions && mysqli_num_rows($rsTransactions) > 0): ?>
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
