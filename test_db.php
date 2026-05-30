<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

echo "=== COLUMNS OF invoices ===\n";
$r = query("DESCRIBE invoices");
while ($row = fetchAssoc($r)) {
    print_r($row);
}

echo "=== COLUMNS OF invoice_items ===\n";
$r = query("DESCRIBE invoice_items");
while ($row = fetchAssoc($r)) {
    print_r($row);
}
