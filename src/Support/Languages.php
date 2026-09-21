<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Idiomas pré-definidos do seletor da tela do site (pedido do responsável,
 * 2026-09-18: português, inglês e espanhol prontos pra escolher, com as
 * bandeiras do Brasil, Estados Unidos e Espanha, e uma opção de escrever
 * outro). O inglês usava a bandeira do Canadá antes — trocada pra dos EUA
 * (pedido do responsável, 2026-09-21).
 *
 * `sites.language` continua sendo texto livre (é colado no prompt da IA —
 * PromptBuilder: "Idioma de publicação: …" — e dois sites já têm "English"),
 * então o preset só decide QUE TEXTO gravar. Um valor já salvo que seja um
 * apelido de um preset ("English", "en", "inglês"…) marca aquele preset sem
 * ser reescrito: abrir e salvar a tela nunca troca "English" por outra grafia.
 */
final class Languages
{
    /**
     * @return array<string, array{label: string, country: string, value: string, flag: string, aliases: list<string>}>
     */
    public static function presets(): array
    {
        return [
            'pt' => [
                'label' => 'Português', 'country' => 'Brasil', 'value' => 'pt-BR', 'flag' => 'br',
                'aliases' => ['pt-br', 'pt', 'pt_br', 'português', 'portugues', 'portuguese', 'português (brasil)', 'portugues (brasil)'],
            ],
            'en' => [
                'label' => 'English', 'country' => 'Estados Unidos', 'value' => 'English', 'flag' => 'us',
                'aliases' => ['english', 'en', 'en-us', 'en-ca', 'en-gb', 'inglês', 'ingles'],
            ],
            'es' => [
                'label' => 'Español', 'country' => 'Espanha', 'value' => 'Español', 'flag' => 'es',
                'aliases' => ['español', 'espanol', 'spanish', 'espanhol', 'es', 'es-es'],
            ],
        ];
    }

    /** Chave do preset (`pt`/`en`/`es`) que o texto salvo representa, ou null quando é um idioma "outro". */
    public static function presetFor(string $stored): ?string
    {
        $needle = mb_strtolower(trim($stored));
        if ($needle === '') {
            return null;
        }
        foreach (self::presets() as $key => $preset) {
            if (in_array($needle, $preset['aliases'], true)) {
                return $key;
            }
        }

        return null;
    }
}
