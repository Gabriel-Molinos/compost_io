<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Flag;
use App\Support\Languages;
use PHPUnit\Framework\TestCase;

final class LanguagesTest extends TestCase
{
    public function testThreePresetsWithTheirFlags(): void
    {
        $presets = Languages::presets();

        $this->assertSame(['pt', 'en', 'es'], array_keys($presets));
        $this->assertSame('br', $presets['pt']['flag']);
        $this->assertSame('ca', $presets['en']['flag']);
        $this->assertSame('es', $presets['es']['flag']);
        // Valor gravado cabe no limite de 20 caracteres do campo (SiteController::validate).
        foreach ($presets as $preset) {
            $this->assertLessThanOrEqual(20, mb_strlen($preset['value']));
        }
    }

    /** @dataProvider storedValues */
    public function testStoredValueMapsToPresetOrNull(string $stored, ?string $expected): void
    {
        $this->assertSame($expected, Languages::presetFor($stored));
    }

    /** @return array<string, array{string, ?string}> */
    public static function storedValues(): array
    {
        return [
            'padrão do app'              => ['pt-BR', 'pt'],
            'valor real dos sites atuais' => ['English', 'en'],
            'caixa e espaços não contam' => ['  english ', 'en'],
            'apelido em português'       => ['Inglês', 'en'],
            'espanhol'                   => ['Español', 'es'],
            'idioma fora dos presets'    => ['Français', null],
            'vazio'                      => ['', null],
        ];
    }

    public function testEveryPresetValueMapsBackToItself(): void
    {
        foreach (Languages::presets() as $key => $preset) {
            $this->assertSame($key, Languages::presetFor($preset['value']), "O valor gravado de '{$key}' precisa ser reconhecido de volta.");
        }
    }

    public function testFlagRendersSvgAndFallsBackForUnknownCode(): void
    {
        foreach (['br', 'ca', 'es'] as $code) {
            $this->assertStringStartsWith('<svg', Flag::svg($code));
        }
        $this->assertStringContainsString('#8FA6BC', Flag::svg('xx'));
    }
}
