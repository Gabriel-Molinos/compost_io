<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Integrations\AIException;
use App\Integrations\Gemini\GeminiConfig;
use App\Integrations\Gemini\GeminiProvider;
use App\Services\CategoryService;
use App\Services\GoalService;
use App\Services\PromptBuilder;
use App\Support\Csrf;
use App\View;
use Throwable;

/**
 * Tela de teste da IA (Fase 4.2): monta o prompt em camadas e manda pro Gemini,
 * mostrando os dois lado a lado. Só ADMIN — cada execução custa uma chamada real.
 * Não grava nada: o pipeline de produção é a Fase 4.4.
 */
final class AiPlaygroundController extends Controller
{
    public function index(string $siteId): void
    {
        $site = $this->requireSite($siteId);
        $this->render($site, ['step' => 'planning', 'goal_id' => '', 'category_id' => '']);
    }

    public function run(string $siteId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();

        $input = [
            'step'        => (string) ($_POST['step'] ?? 'planning'),
            'goal_id'     => (string) ($_POST['goal_id'] ?? ''),
            'category_id' => (string) ($_POST['category_id'] ?? ''),
        ];

        if (!in_array($input['step'], PromptBuilder::STEPS, true)) {
            $this->render($site, $input, error: 'Passo inválido.');
            return;
        }

        try {
            $prompt = (new PromptBuilder())->build(
                $input['step'],
                (int) $site['id'],
                $input['goal_id'] !== '' ? (int) $input['goal_id'] : null,
                $input['category_id'] !== '' ? (int) $input['category_id'] : null,
                ['title' => 'EXEMPLO — a IA propõe o tema neste passo'],
            );
        } catch (Throwable $e) {
            $this->render($site, $input, error: 'Erro ao montar o prompt: ' . $e->getMessage());
            return;
        }

        try {
            $config = GeminiConfig::fromEnv();
            $started = microtime(true);
            $result = (new GeminiProvider($config))->generateText($prompt);
            $elapsed = round(microtime(true) - $started, 1);
        } catch (AIException $e) {
            $this->render($site, $input, prompt: $prompt, error: 'Gemini: ' . $e->getMessage());
            return;
        }

        $this->render($site, $input, prompt: $prompt, result: [
            'text'    => $result->text,
            'elapsed' => $elapsed,
            'model'   => $result->model,
            'tokens'  => [
                'prompt'   => $result->promptTokens,
                'thoughts' => $result->thoughtsTokens,
                'output'   => $result->outputTokens,
                'total'    => $result->totalTokens,
            ],
        ]);
    }

    /**
     * @param array<string,mixed>            $site
     * @param array{step:string,goal_id:string,category_id:string} $input
     * @param array<string,mixed>|null       $result
     */
    private function render(array $site, array $input, ?string $prompt = null, ?array $result = null, ?string $error = null): void
    {
        View::render('sites/ai/playground', [
            'title'      => 'IA (teste) · ' . $site['name'],
            'site'       => $site,
            'steps'      => PromptBuilder::STEPS,
            'goals'      => (new GoalService())->allForSite((int) $site['id']),
            'categories' => (new CategoryService())->allForSite((int) $site['id']),
            'input'      => $input,
            'prompt'     => $prompt,
            'result'     => $result,
            'error'      => $error,
        ]);
    }
}
