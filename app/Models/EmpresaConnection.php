<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use PDO;
use PDOException;

class EmpresaConnection extends Model
{
    use HasFactory;

    // protected $connection = 'admin'; // Usar conexión por defecto por ahora
    protected $table = 'empresas';
    protected $primaryKey = 'id_empresa';

    protected $fillable = [
        'id_persona',
        'direccion',
        'ubigeo',
        'sub_dominio',
        'db_host',
        'db_name',
        'db_usuario',
        'db_contrasena',
        'fec_afiliacion',
        'flg_reconexion',
        'estado',
        'status'
    ];

    protected $casts = [
        'fec_afiliacion' => 'datetime',
        'estado' => 'integer',
        'status' => 'integer',
        'flg_reconexion' => 'integer'
    ];

    /**
     * Obtener todas las conexiones de empresas activas
     */
    public static function getActiveConnections()
    {
        return self::select([
                'id_empresa',
                'sub_dominio',
                'db_host',
                'db_name',
                'db_usuario',
                'db_contrasena'
            ])
            ->where('estado', '1')
            ->where('status', '1')
            ->orderBy('sub_dominio')
            ->get();
    }

    /**
     * Obtener conexión por subdominio
     */
    public static function getConnectionBySubdomain($subdominio)
    {
        return self::select([
                'id_empresa',
                'sub_dominio',
                'db_host',
                'db_name',
                'db_usuario',
                'db_contrasena'
            ])
            ->where('sub_dominio', $subdominio)
            ->where('estado', '1')
            ->where('status', '1')
            ->first();
    }

    /**
     * Obtener conexión por RUC
     */
    public static function getConnectionByRuc($ruc)
    {
        return self::select([
                'empresas.id_empresa',
                'empresas.sub_dominio',
                'empresas.db_host',
                'empresas.db_name',
                'empresas.db_usuario',
                'empresas.db_contrasena'
            ])
            ->join('personas', 'empresas.id_persona', '=', 'personas.id_persona')
            ->where('personas.ruc', $ruc)
            ->where('empresas.estado', '1')
            ->where('empresas.status', '1')
            ->where('personas.status', '1')
            ->first();
    }

    /**
     * Crear conexión PDO a la base de datos de la empresa
     */
    public function createPDOConnection()
    {
        try {
            $dsn = "mysql:host={$this->db_host};dbname={$this->db_name};charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4'"
            ];

            return new PDO($dsn, $this->db_usuario, $this->db_contrasena, $options);
            
        } catch (PDOException $e) {
            Log::error("Error conectando a DB de empresa {$this->sub_dominio}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Probar conexión a la base de datos
     */
    public function testConnection()
    {
        $pdo = $this->createPDOConnection();
        
        if ($pdo === null) {
            return false;
        }

        try {
            $pdo->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            Log::error("Error testando conexión a {$this->sub_dominio}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Ejecutar consulta en la base de datos de la empresa
     */
    public function executeQuery($query, $params = [])
    {
        $pdo = $this->createPDOConnection();
        
        if ($pdo === null) {
            return false;
        }

        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            
            // Limpiar query y verificar si es SELECT
            $cleanQuery = trim(strtoupper($query));
            if (strpos($cleanQuery, 'SELECT') === 0) {
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            return $stmt->rowCount();
            
        } catch (PDOException $e) {
            Log::error("Error ejecutando query en {$this->sub_dominio}: " . $e->getMessage());
            return false;
        }
    }
}