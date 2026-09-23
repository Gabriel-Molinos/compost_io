<?php

declare(strict_types=1);

namespace App\Controllers;

use App\View;

/**
 * Página de conteúdo puro (sem dado de banco) com as regras de compliance de
 * conteúdo/AdSense e os elementos obrigatórios de um post — pedido do
 * responsável (2026-09-23) pra que qualquer pessoa que gera ou aprova um
 * artigo saiba exatamente o que precisa, sem depender de abrir os docs/
 * técnicos do repositório. Fonte das regras: docs/editorial/compliance.md +
 * docs/ai/compliance.md (o que a IA verifica no passo de compliance) — mantida
 * em prosa aqui em vez de puxar o .md em runtime porque é conteúdo estável e
 * a página já tem exemplos/tom voltados a quem revisa, não a quem programa.
 */
final class ComplianceController extends Controller
{
    public function index(): void
    {
        View::render('compliance/index', [
            'title' => 'Regras de compliance',
        ]);
    }
}
