<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Integrations\AIException;
use App\Integrations\Gemini\GeminiPricing;
use App\Integrations\Gemini\GeminiProvider;
use App\Integrations\AIProvider;
use DateTimeImmutable;
use RuntimeException;

/**
 * Centro de Inteligência Editorial (RF-014, fluxo-editorial §33).
 *
 * Diferente do relatório mensal (8.1/8.2), que é só agregação de SQL, aqui a
 * análise é NARRATIVA: juntamos os números dos últimos meses + as justificativas
 * de rejeição e pedimos ao Gemini que responda às seis perguntas da §33. Roda
 * só sob demanda (botão) porque cada geração é uma chamada paga.
 *
 * O resultado fica salvo em `editorial_insights` até alguém regenerar.
 */
final class IntelligenceService
{
    /** As seis perguntas da §33 — chave do JSON => rótulo exibido. */
    public const QUESTIONS = [
        'funcionando'  => 'O que está funcionando?',
        'dando_errado' => 'O que está dando errado?',
        'melhorou'     => 'O que melhorou?',
        'nao_melhorou' => 'O que não melhorou?',
        'ia_aprendeu'  => 'O que a IA aprendeu?',
        'ajustes'      => 'O que precisa ser ajustado?',
    ];

    /** Meses de histórico que entram na análise. */
    private const MONTHS = 3;

    private ReportService $reports;
    private FeedbackService $feedback;
    private AIProvider $ai;

    public function __construct(?ReportService $reports = null, ?FeedbackService $feedback = null, ?AIProvider $ai = null)
    {
        $this->reports = $reports ?? new ReportService();
        $this->feedback = $feedback ?? new FeedbackService();
        $this->ai = $ai ?? new GeminiProvider();
    }

    /** @return array<string, mixed>|null a análise mais recente do site, ou null se nunca gerou */
    public function latest(int $siteId): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT ei.*, u.name AS generated_by_name
             FROM editorial_insights ei
             LEFT JOIN users u ON u.id = ei.generated_by
             WHERE ei.site_id = :s
             ORDER BY ei.id DESC LIMIT 1'
        );
        $stmt->execute(['s' => $siteId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $row['answers'] = $this->decode($row['content']);

        return $row;
    }

    /** Análises geradas para o site nas últimas 24h (guarda de custo). */
    public function countLast24h(int $siteId): int
    {
        $stmt = Connection::get()->prepare(
            'SELECT COUNT(*) FROM editorial_insights
             WHERE site_id = :s AND created_at >= (NOW() - INTERVAL 1 DAY)'
        );
        $stmt->execute(['s' => $siteId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Gera uma nova análise e salva. Devolve a linha salva (com `answers`).
     *
     * @throws AIException     se o Gemini falhar
     * @throws RuntimeException se a resposta não vier no formato esperado
     * @return array<string, mixed>
     */
    public function generate(int $siteId, ?int $userId): array
    {
        $context = $this->buildContext($siteId);

        $result = $this->ai->generateJson(
            $this->prompt($context),
            $this->schema(),
            $this->systemInstruction(),
        );

        $answers = $result->json;
        if (!is_array($answers) || !isset($answers['funcionando'], $answers['ajustes'])) {
            throw new RuntimeException('A IA respondeu fora do formato esperado.');
        }

        $pdo = Connection::get();
        $pdo->prepare(
            'INSERT INTO editorial_insights
                (site_id, content, model, cost, prompt_tokens, output_tokens, generated_by)
             VALUES (:s, :c, :m, :cost, :pt, :ot, :u)'
        )->execute([
            's'    => $siteId,
            'c'    => json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'm'    => $result->model,
            'cost' => GeminiPricing::estimate($result),
            'pt'   => $result->promptTokens,
            'ot'   => $result->outputTokens + $result->thoughtsTokens,
            'u'    => $userId,
        ]);

        $latest = $this->latest($siteId);
        if ($latest === null) {
            throw new RuntimeException('Falha ao salvar a análise.');
        }

        return $latest;
    }

    /** @param string $json coluna `content` */
    private function decode(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<string, mixed> */
    private function buildContext(int $siteId): array
    {
        $now = new DateTimeImmutable('now');
        $months = [];
        for ($i = 0; $i < self::MONTHS; $i++) {
            $period = $now->modify("-{$i} months")->format('Y-m');
            $months[$period] = $this->reports->monthly($siteId, $period);
        }
        $periods = array_keys($months);
        $comparison = count($periods) >= 2
            ? $this->reports->compare($months[$periods[0]], $months[$periods[1]])
            : null;

        return [
            'months'      => $months,
            'comparison'  => $comparison,
            'rejections'  => $this->feedback->recentForSite($siteId, 15),
        ];
    }

    private function systemInstruction(): string
    {
        return <<<TXT
            Você é um analista editorial. Recebe os números de produção de um site
            (metas, aprovações, rejeições, custo de IA, tempo de revisão) e os
            motivos das rejeições recentes. Sua tarefa é responder, em português
            do Brasil, às seis perguntas do Centro de Inteligência Editorial.

            Regras:
            - Baseie-se APENAS nos dados fornecidos. Não invente números, causas
              nem tendências que os dados não sustentam.
            - Se não houver dado suficiente para uma pergunta, diga isso com
              franqueza (ex.: "ainda não há histórico para comparar").
            - Seja concreto e direto: 2 a 4 frases por resposta. Cite os números
              quando forem relevantes.
            - "ajustes" é uma lista de ações práticas para o Redator-Chefe.
            TXT;
    }

    /** @param array<string, mixed> $context */
    private function prompt(array $context): string
    {
        $lines = ["## Produção mês a mês (mais recente primeiro)"];
        foreach ($context['months'] as $period => $m) {
            $lines[] = sprintf(
                "- %s: meta %s, produzidos %d, aprovados %d, rejeitados %d, publicados %d, "
                . "taxa de aprovação %s, tempo médio de revisão %s, custo de IA US$ %.2f",
                $period,
                $m['goal_total'] ?? 'sem meta',
                $m['produced'],
                $m['approved'],
                $m['rejected'],
                $m['published'],
                $m['approval_rate'] === null ? 'sem base' : $m['approval_rate'] . '%',
                $m['avg_review_hours'] === null ? 'sem dado' : $m['avg_review_hours'] . ' h',
                $m['ai_cost'],
            );
            foreach ($m['reject_reasons'] as $r) {
                $lines[] = sprintf('    · motivo "%s": %d', $r['reason'], (int) $r['total']);
            }
        }

        if ($context['comparison'] !== null && $context['comparison']['had_data']) {
            $c = $context['comparison'];
            $lines[] = "\n## Variação do mês atual vs. o anterior";
            foreach (['produced', 'approved', 'rejected', 'published', 'approval_rate', 'avg_review_hours', 'ai_cost'] as $k) {
                if ($c[$k] !== null) {
                    $lines[] = sprintf('- %s: %.2f → %.2f', $k, $c[$k]['from'], $c[$k]['to']);
                }
            }
        }

        $lines[] = "\n## Justificativas das rejeições recentes";
        if ($context['rejections'] === []) {
            $lines[] = '- (nenhuma rejeição registrada)';
        } else {
            foreach ($context['rejections'] as $r) {
                $lines[] = sprintf('- [%s] %s', $r['reason'], trim((string) $r['justification']));
            }
        }

        $lines[] = "\nResponda às seis perguntas no formato JSON pedido.";

        return implode("\n", $lines);
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        $str = ['type' => 'string'];

        return [
            'type'       => 'object',
            'properties' => [
                'funcionando'  => $str,
                'dando_errado' => $str,
                'melhorou'     => $str,
                'nao_melhorou' => $str,
                'ia_aprendeu'  => $str,
                'ajustes'      => ['type' => 'array', 'items' => $str],
            ],
            'required' => ['funcionando', 'dando_errado', 'melhorou', 'nao_melhorou', 'ia_aprendeu', 'ajustes'],
        ];
    }
}
