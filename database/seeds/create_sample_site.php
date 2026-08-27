<?php

declare(strict_types=1);

/**
 * Cria um site de exemplo já com configuração editorial completa: categorias,
 * interesses/não-interesses e uma meta com distribuição por categoria. Serve
 * para exercitar a área /sites/{id} (Fase 3) sem cadastrar tudo à mão.
 *
 *   php database/seeds/create_sample_site.php
 *
 * Opcional — vincular um Redator-Chefe já existente ao site:
 *   REDATOR_EMAIL="chefe@ex.com" php database/seeds/create_sample_site.php
 *
 * Idempotente: se já existir um site com o mesmo nome, nada é alterado.
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Config\Env;
use App\Database\Connection;

Env::load(dirname(__DIR__, 2) . '/.env');

const SITE_NAME = 'Valorizei (exemplo)';

$pdo = Connection::get();

$exists = $pdo->prepare('SELECT id FROM sites WHERE name = :n LIMIT 1');
$exists->execute(['n' => SITE_NAME]);
if ($siteId = $exists->fetchColumn()) {
    echo "Site de exemplo já existe (id {$siteId}). Nada a fazer.\n";
    exit(0);
}

$pdo->beginTransaction();
try {
    $pdo->prepare(
        "INSERT INTO sites (name, niche, language, target_audience, tone, is_active)
         VALUES (:n, 'Finanças pessoais', 'pt-BR', 'Iniciantes em finanças', 'Profissional e claro', 1)"
    )->execute(['n' => SITE_NAME]);
    $siteId = (int) $pdo->lastInsertId();

    $categories = [
        'Cartões'       => 'Focar comparativos e público iniciante. Evitar jargão.',
        'Investimentos' => 'Sempre citar riscos. Dados de fontes oficiais (BCB, CVM).',
        'Empréstimos'   => 'Deixar o custo total (CET) explícito. Sem promessas.',
    ];
    $catStmt = $pdo->prepare('INSERT INTO categories (site_id, name, guidelines) VALUES (:s, :n, :g)');
    $catIds = [];
    foreach ($categories as $name => $guidelines) {
        $catStmt->execute(['s' => $siteId, 'n' => $name, 'g' => $guidelines]);
        $catIds[$name] = (int) $pdo->lastInsertId();
    }

    $rules = [
        ['INTEREST', 'Linguagem clara e direta', 5],
        ['INTEREST', 'Dados de fontes oficiais', 5],
        ['INTEREST', 'Comparativos e tabelas', 4],
        ['INTEREST', 'FAQ ao final do artigo', 3],
        ['NON_INTEREST', 'Sensacionalismo e clickbait', 5],
        ['NON_INTEREST', 'Promessas de retorno garantido', 5],
        ['NON_INTEREST', 'Keyword stuffing', 4],
    ];
    $ruleStmt = $pdo->prepare(
        'INSERT INTO editorial_rules (site_id, type, description, intensity) VALUES (:s, :t, :d, :i)'
    );
    foreach ($rules as [$type, $description, $intensity]) {
        $ruleStmt->execute(['s' => $siteId, 't' => $type, 'd' => $description, 'i' => $intensity]);
    }

    $pdo->prepare(
        "INSERT INTO goals (site_id, period, total_articles, general_guidelines)
         VALUES (:s, :p, 30, 'Priorizar comparativos e pautas de atualidade. Focar iniciantes. Evitar repetição de temas do mês anterior.')"
    )->execute(['s' => $siteId, 'p' => date('Y-m')]);
    $goalId = (int) $pdo->lastInsertId();

    $gcStmt = $pdo->prepare(
        'INSERT INTO goal_categories (goal_id, category_id, target_count) VALUES (:g, :c, :n)'
    );
    foreach (['Cartões' => 10, 'Investimentos' => 10, 'Empréstimos' => 10] as $name => $count) {
        $gcStmt->execute(['g' => $goalId, 'c' => $catIds[$name], 'n' => $count]);
    }

    $redatorEmail = getenv('REDATOR_EMAIL') ?: '';
    if ($redatorEmail !== '') {
        $u = $pdo->prepare("SELECT id FROM users WHERE email = :e AND role = 'REDATOR_CHEFE' LIMIT 1");
        $u->execute(['e' => $redatorEmail]);
        if ($userId = $u->fetchColumn()) {
            $pdo->prepare('INSERT IGNORE INTO user_site (user_id, site_id) VALUES (:u, :s)')
                ->execute(['u' => $userId, 's' => $siteId]);
            echo "Redator-Chefe {$redatorEmail} vinculado.\n";
        } else {
            fwrite(STDERR, "Aviso: nenhum REDATOR_CHEFE com e-mail {$redatorEmail}; site criado sem vínculo.\n");
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

echo "Site de exemplo criado (id {$siteId}): 3 categorias, 7 regras, 1 meta (30 artigos).\n";
