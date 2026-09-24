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
            // Achado de performance (2026-09-24): com o banco remoto, o
            // handshake TCP+TLS custa ~600ms e cada round-trip ~290ms — pagos
            // a CADA request. Conexão persistente (só em contexto web; o
            // worker CLI é longo e já reaproveita a sua) elimina o handshake,
            // e o fuso vai no init command (roda só ao abrir a conexão real)
            // em vez de um exec() extra por request. Explicação do fuso: ver
            // comentário mais abaixo.
            PDO::ATTR_PERSISTENT         => PHP_SAPI !== 'cli',
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-03:00'",
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

        // Achado real (2026-09-08): o cluster gerenciado (DigitalOcean) roda
        // com o relógio da sessão em UTC (confirmado: NOW() = UTC_TIMESTAMP()
        // nele) — 3h à frente de Brasília. Toda comparação/gravação que
        // depende do relógio do BANCO (NOW(), CURDATE(), DEFAULT
        // CURRENT_TIMESTAMP em created_at/updated_at) ficava em UTC enquanto
        // o PHP (date_default_timezone_set em public/index.php e
        // bin/worker.php) já calcula tudo em America/Sao_Paulo — publicação
        // agendada disparava 3h cedo demais (dueForPublish comparando
        // scheduled_date, gravado em horário de Brasília pela aplicação,
        // contra NOW() em UTC), e todo created_at/updated_at exibido saía
        // 3h atrasado. Offset numérico fixo (não o nome da zona) porque
        // clusters gerenciados normalmente não têm as tabelas de fuso
        // horário do MySQL carregadas — Brasil não observa horário de
        // verão desde 2019, então -03:00 é sempre correto, sem precisar
        // reavaliar por data.
        // (aplicado via MYSQL_ATTR_INIT_COMMAND acima). Conexão persistente
        // pode voltar com transação aberta de um request que morreu no meio.
        if (self::$instance->inTransaction()) {
            self::$instance->rollBack();
        }

        return self::$instance;
    }
}
