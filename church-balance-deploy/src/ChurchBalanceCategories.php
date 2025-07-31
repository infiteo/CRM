<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

// Security: User must be administrator to use this page
AuthenticationManager::redirectHomeIfNotAdmin();

$sPageTitle = gettext('Categorie Bilancio Chiesa');

require 'Include/Header.php';

// Handle form submissions
$action = '';
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = InputUtils::filterString($_POST['action']);
        
        if ($action === 'add_category') {
            $name = InputUtils::filterString($_POST['name']);
            $type = InputUtils::filterString($_POST['type']);
            $description = InputUtils::filterString($_POST['description']);
            $order = InputUtils::filterInt($_POST['order']);
            
            if (empty($name) || empty($type)) {
                $message = 'Nome e Tipo sono campi obbligatori.';
                $messageType = 'error';
            } else {
                // Check if category name already exists
                $sSQL = "SELECT cbc_ID FROM church_balance_categories_cbc WHERE cbc_Name = '" . addslashes($name) . "'";
                $result = RunQuery($sSQL);
                
                if (mysqli_num_rows($result) > 0) {
                    $message = 'Una categoria con questo nome esiste già.';
                    $messageType = 'error';
                } else {
                    $sSQL = "INSERT INTO church_balance_categories_cbc (cbc_Name, cbc_Type, cbc_Description, cbc_Order) 
                             VALUES ('" . addslashes($name) . "', '" . $type . "', '" . addslashes($description) . "', " . $order . ")";
                    
                    if (RunQuery($sSQL)) {
                        $message = 'Categoria aggiunta con successo.';
                        $messageType = 'success';
                    } else {
                        $message = 'Errore nell\'aggiunta della categoria.';
                        $messageType = 'error';
                    }
                }
            }
        } elseif ($action === 'update_category') {
            $id = InputUtils::filterInt($_POST['id']);
            $name = InputUtils::filterString($_POST['name']);
            $type = InputUtils::filterString($_POST['type']);
            $description = InputUtils::filterString($_POST['description']);
            $order = InputUtils::filterInt($_POST['order']);
            $active = isset($_POST['active']) ? 1 : 0;
            
            if (empty($name) || empty($type)) {
                $message = 'Nome e Tipo sono campi obbligatori.';
                $messageType = 'error';
            } else {
                $sSQL = "UPDATE church_balance_categories_cbc SET 
                        cbc_Name = '" . addslashes($name) . "', 
                        cbc_Type = '" . $type . "', 
                        cbc_Description = '" . addslashes($description) . "', 
                        cbc_Order = " . $order . ", 
                        cbc_Active = " . $active . " 
                        WHERE cbc_ID = " . $id;
                
                if (RunQuery($sSQL)) {
                    $message = 'Categoria aggiornata con successo.';
                    $messageType = 'success';
                } else {
                    $message = 'Errore nell\'aggiornamento della categoria.';
                    $messageType = 'error';
                }
            }
        } elseif ($action === 'delete_category') {
            $id = InputUtils::filterInt($_POST['id']);
            
            // Check if category is being used
            $sSQL = "SELECT COUNT(*) as count FROM church_balance_cb WHERE cb_Category = (SELECT cbc_Name FROM church_balance_categories_cbc WHERE cbc_ID = " . $id . ")";
            $result = RunQuery($sSQL);
            $row = mysqli_fetch_array($result);
            
            if ($row['count'] > 0) {
                $message = 'Impossibile eliminare la categoria: è utilizzata nei record delle transazioni.';
                $messageType = 'error';
            } else {
                $sSQL = "DELETE FROM church_balance_categories_cbc WHERE cbc_ID = " . $id;
                
                if (RunQuery($sSQL)) {
                    $message = 'Categoria eliminata con successo.';
                    $messageType = 'success';
                } else {
                    $message = 'Errore nell\'eliminazione della categoria.';
                    $messageType = 'error';
                }
            }
        }
    }
}
?>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-tags"></i>
                    Categorie Bilancio Chiesa
                </h3>
            </div>
            <div class="card-body">
                
                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType === 'error' ? 'danger' : 'success' ?>" role="alert">
                        <?= $message ?>
                    </div>
                <?php endif; ?>

                <!-- Add New Category Form -->
                <div class="card">
                    <div class="card-header">
                        <h4>Aggiungi Nuova Categoria</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="ChurchBalanceCategories.php">
                            <input type="hidden" name="action" value="add_category">
                            
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Nome *</label>
                                        <input type="text" name="name" class="form-control" maxlength="50" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Tipo *</label>
                                        <select name="type" class="form-control" required>
                                            <option value="">Seleziona Tipo</option>
                                            <option value="Income">Entrata</option>
                                            <option value="Expense">Uscita</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Descrizione</label>
                                        <input type="text" name="description" class="form-control" maxlength="255">
                                    </div>
                                </div>
                                
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Ordine</label>
                                        <input type="number" name="order" class="form-control" value="0" min="0">
                                    </div>
                                </div>
                                
                                <div class="col-md-1">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="submit" class="btn btn-primary form-control">
                                            <i class="fa fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Categories Table -->
                <div class="card">
                    <div class="card-header">
                        <h4>Categorie Esistenti</h4>
                    </div>
                    <div class="card-body">
                        <?php
                        $sSQL = "SELECT * FROM church_balance_categories_cbc ORDER BY cbc_Type, cbc_Order, cbc_Name";
                        $rsCategories = RunQuery($sSQL);
                        ?>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Tipo</th>
                                        <th>Descrizione</th>
                                        <th>Ordine</th>
                                        <th>Attiva</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (mysqli_num_rows($rsCategories) === 0) {
                                        echo "<tr><td colspan='6' class='text-center'>Nessuna categoria trovata.</td></tr>";
                                    } else {
                                        while ($row = mysqli_fetch_array($rsCategories)) {
                                            $editFormId = 'editForm' . $row['cbc_ID'];
                                            echo "<tr>";
                                            echo "<form method='POST' action='ChurchBalanceCategories.php' id='" . $editFormId . "'>";
                                            echo "<input type='hidden' name='action' value='update_category'>";
                                            echo "<input type='hidden' name='id' value='" . $row['cbc_ID'] . "'>";
                                            
                                            echo "<td><input type='text' name='name' class='form-control' value='" . htmlentities($row['cbc_Name']) . "' maxlength='50' required></td>";
                                            
                                            echo "<td>";
                                            echo "<select name='type' class='form-control' required>";
                                            echo "<option value='Income'" . ($row['cbc_Type'] === 'Income' ? ' selected' : '') . ">Entrata</option>";
                                            echo "<option value='Expense'" . ($row['cbc_Type'] === 'Expense' ? ' selected' : '') . ">Uscita</option>";
                                            echo "</select>";
                                            echo "</td>";
                                            
                                            echo "<td><input type='text' name='description' class='form-control' value='" . htmlentities($row['cbc_Description']) . "' maxlength='255'></td>";
                                            
                                            echo "<td><input type='number' name='order' class='form-control' value='" . $row['cbc_Order'] . "' min='0' style='width: 80px;'></td>";
                                            
                                            echo "<td>";
                                            echo "<input type='checkbox' name='active' value='1'" . ($row['cbc_Active'] ? ' checked' : '') . ">";
                                            echo "</td>";
                                            
                                            echo "<td>";
                                            echo "<button type='submit' class='btn btn-sm btn-success' title='Aggiorna'>";
                                            echo "<i class='fa fa-save'></i>";
                                            echo "</button>";
                                            
                                            echo "</form>";
                                            
                                            // Delete form
                                            echo "<form method='POST' action='ChurchBalanceCategories.php' style='display: inline;' onsubmit='return confirm(\"Sei sicuro di voler eliminare questa categoria?\")'>";
                                            echo "<input type='hidden' name='action' value='delete_category'>";
                                            echo "<input type='hidden' name='id' value='" . $row['cbc_ID'] . "'>";
                                            echo "<button type='submit' class='btn btn-sm btn-danger ml-1' title='Elimina'>";
                                            echo "<i class='fa fa-trash'></i>";
                                            echo "</button>";
                                            echo "</form>";
                                            
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Back to Balance Management -->
                <div class="row">
                    <div class="col-md-12">
                        <a href="ChurchBalance.php" class="btn btn-primary">
                            <i class="fa fa-arrow-left"></i> Torna alla Gestione Bilancio
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'Include/Footer.php';
?>
