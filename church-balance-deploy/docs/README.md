# Sistema di Gestione Bilancio Chiesa

Sistema completo per la gestione del bilancio della chiesa, completamente integrato in ChurchCRM.

## 🚀 Caratteristiche Principali

### ✅ Gestione Transazioni
- Aggiunta manuale di entrate e uscite
- Categorizzazione automatica delle transazioni
- Collegamento con i fondi di donazione esistenti
- Calcolo automatico del saldo corrente

### ✅ Import/Export Dati
- **Importazione CSV**: Carica transazioni multiple da file CSV
- **Esportazione Excel**: Genera report Excel con formattazione professionale
- **Esportazione CSV**: Export dati per analisi esterne
- **Report PDF**: Stampe formattate per documentazione

### ✅ Interfaccia Italiana
- Completamente localizzata in italiano
- Formato valuta europea (€)
- Date in formato italiano (gg/mm/aaaa)
- Terminologia finanziaria appropriata

### ✅ Filtri e Reportistica
- Filtri per data, tipo transazione e categoria
- Visualizzazione totali per periodo
- Calcolo variazione netta automatica
- Storico completo delle transazioni

## 📋 Utilizzo

### Aggiungere una Transazione
1. Vai alla pagina "Bilancio Chiesa" dal menu Finanze
2. Compila il modulo "Aggiungi Nuova Transazione"
3. Seleziona il tipo (Entrata/Uscita)
4. Scegli la categoria appropriata
5. Inserisci importo e descrizione
6. Salva la transazione

### Importare da CSV
1. Prepara un file CSV con il formato corretto (vedi template)
2. Usa la sezione "Importa da CSV"
3. Carica il file e avvia l'importazione
4. Controlla i messaggi di conferma/errore

#### 📄 Formato CSV Template
```csv
Data,Tipo,Categoria,Descrizione,Importo,Note,Fondo
2024-01-15,Entrata,Offerte Domenicali,Offerte raccolte,250.50,Note opzionali,
2024-01-16,Uscita,Manutenzione,Riparazione,450.00,Fattura 123,
```

Un file template è disponibile: `church-balance-template.csv`

### Esportare Report
1. Usa i pulsanti nella sezione "Esporta Report"
2. Scegli il formato: Excel, CSV o PDF
3. Opzionalmente imposta filtri per data
4. Scarica il file generato

## 🔧 Installazione

### Database
```sql
-- Eseguire questi comandi nel database ChurchCRM
source church-balance-safe.sql
```

### File di Sistema
1. Caricare tutti i file PHP nella cartella `src/`
2. Verificare i permessi di scrittura
3. Il menu si aggiorna automaticamente

### Configurazione Menu
Il sistema si integra automaticamente nel menu "Finanze" di ChurchCRM.

## 📊 Struttura Database

### Tabella: church_balance_cb
Memorizza tutte le transazioni del bilancio chiesa:
- ID transazione univoco
- Data e tipo transazione
- Categoria e descrizione
- Importo e saldo corrente
- Collegamenti a fondi e utenti

### Tabella: church_balance_categories_cbc  
Gestisce le categorie predefinite per entrate e uscite:
- Categorie di entrata (Offerte, Donazioni, Eventi, ecc.)
- Categorie di uscita (Utenze, Manutenzione, Forniture, ecc.)

## 🚨 Risoluzione Problemi

### Menu Non Funziona
- ✅ **RISOLTO**: Verificare che `Include/Header.php` sia caricato
- Controllare permessi utente per l'accesso alle finanze
- Verificare compatibilità con la versione ChurchCRM

### Errori di Importazione CSV
- Controllare formato file (UTF-8 consigliato)
- Verificare intestazioni colonne
- Formato date supportate: YYYY-MM-DD o DD/MM/YYYY
- Tipi validi: Entrata/Income, Uscita/Expense

### Problemi di Esportazione
- Verificare permessi di scrittura server
- Controllare disponibilità memoria PHP
- Testare con dataset più piccoli

## 📞 Supporto Tecnico

Per assistenza tecnica:
1. Verificare log errori ChurchCRM
2. Controllare configurazione database
3. Validare permessi utente
4. Consultare documentazione ChurchCRM

## 📈 Aggiornamenti

**Versione 1.2.0 (Corrente):**
- ✅ Interfaccia completamente italiana
- ✅ Importazione CSV con validazione
- ✅ Esportazione Excel avanzata
- ✅ Correzioni bug menu
- ✅ Template CSV incluso
- ✅ Formattazione valuta europea

**Caratteristiche Tecniche:**
- Compatibile con ChurchCRM 4.x+
- Responsive design
- Protezione SQL injection
- Autenticazione utente integrata
- Audit trail completo

Sistema testato e funzionante con le seguenti funzionalità:
- ✅ Gestione transazioni
- ✅ Import CSV automatico  
- ✅ Export Excel/PDF/CSV
- ✅ Menu integrato ChurchCRM
- ✅ Interfaccia italiana completa
