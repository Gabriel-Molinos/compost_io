<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

// Os testes de integração batem no banco de dev de verdade (Connection::get())
// e precisam das mesmas credenciais do app, que vivem no .env (fora do git —
// nunca commitar). Antes disto o bootstrap era só o autoload: o .env nunca era
// carregado no PHPUnit, então DATABASE_HOST caía no padrão 127.0.0.1, a
// conexão era recusada e TODA a suíte de integração era "pulada" pelo
// skip-se-banco-fora-do-ar — parecia instabilidade da DigitalOcean, mas era
// configuração (achado real 2026-09-18). Sem .env (ex.: CI só de unitários) o
// comportamento continua o mesmo de antes: integração pula, unitário roda.
$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile)) {
    App\Config\Env::load($envFile);
}
