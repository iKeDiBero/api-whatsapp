<?php

// Script de prueba para verificar el modelo EmpresaConnection
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "🔍 Probando modelo EmpresaConnection...\n";
    
    $count = App\Models\EmpresaConnection::count();
    echo "✅ Total empresas encontradas: " . $count . "\n";
    
    if ($count > 0) {
        $connections = App\Models\EmpresaConnection::getActiveConnections();
        echo "✅ Conexiones activas: " . $connections->count() . "\n";
        
        foreach ($connections->take(3) as $conn) {
            echo "   - " . $conn->sub_dominio . " (" . $conn->db_name . ")\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "   Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}