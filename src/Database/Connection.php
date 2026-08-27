<?php

declare(strict_types=1);

namespace App\Database;

use App\Config\Env;
use PDO;
use PDOException;

/**
 * Conexão PDO centralizada (Model / acesso a dados).
 * Ver docs/technical/padroes-de-codigo.md §88.1.
 */
final class Connection
{
    private static ?PDO $instance = null;

    public static function get(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $host = Env::get('DATABASE_HOST', '127.0.0.1');
        $port = Env::get('DATABASE_PORT', '3306');
        $name = Env::get('DATABASE_NAME', '');
        $user = Env::get('DATABASE_USER', '');
        $pass = Env::get('DATABASE_PASSWORD', '');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        if (Env::bool('DATABASE_SSL')) {
            // Cluster gerenciado (DigitalOcean) exige TLS.
            // TODO(hardening): apontar PDO::MYSQL_ATTR_SSL_CA para o ca-certificate.crt
            //                  do cluster e verificar o certificado do servidor.
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        try {
            self::$instance = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // Nunca expor host/credenciais na mensagem (docs/technical/seguranca.md §48).
            error_log('DB connection failed: ' . $e->getMessage());
            throw new PDOException('Falha ao conectar ao banco de dados.');
        }

        return self::$instance;
    }
}
