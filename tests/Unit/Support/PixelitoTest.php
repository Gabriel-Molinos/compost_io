<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Services\NotificationService;
use App\Support\Pixelito;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PixelitoTest extends TestCase
{
    private const DIR = __DIR__ . '/../../../public/assets/pixelito/';

    /** @return list<string> os valores de NotificationService::TYPE_* */
    private function notificationTypes(): array
    {
        $types = [];
        foreach ((new ReflectionClass(NotificationService::class))->getConstants() as $name => $value) {
            if (str_starts_with($name, 'TYPE_')) {
                $types[] = (string) $value;
            }
        }

        return $types;
    }

    public function testEveryExpressionHasItsImageFile(): void
    {
        foreach (Pixelito::EXPRESSIONS as $expression) {
            $this->assertFileExists(self::DIR . $expression . '.webp', "falta a imagem da expressão \"{$expression}\"");
        }
    }

    public function testNoImageInTheFolderIsMissingFromTheRegistry(): void
    {
        $files = array_map(static fn (string $f): string => basename($f, '.webp'), glob(self::DIR . '*.webp') ?: []);
        sort($files);
        $expected = Pixelito::EXPRESSIONS;
        sort($expected);

        $this->assertSame($expected, $files, 'imagem sem registro em Pixelito::EXPRESSIONS (ou registro sem imagem)');
    }

    public function testEveryNotificationTypeHasItsOwnExpression(): void
    {
        $types = $this->notificationTypes();
        $this->assertNotEmpty($types);

        foreach ($types as $type) {
            $expression = Pixelito::forNotificationType($type);
            $this->assertContains($expression, Pixelito::EXPRESSIONS, "tipo {$type}");
            $this->assertNotSame(Pixelito::DEFAULT, $expression, "tipo {$type} esquecido no mapa — cairia na expressão padrão");
        }
    }

    public function testUnknownTypeAndUnknownExpressionFallBackToTheDefault(): void
    {
        $this->assertSame(Pixelito::DEFAULT, Pixelito::forNotificationType('TIPO_QUE_NAO_EXISTE'));
        $this->assertSame(Pixelito::BASE . Pixelito::DEFAULT . '.webp', Pixelito::url('nao-existe'));
        $this->assertSame(Pixelito::BASE . 'sem-animo.webp', Pixelito::url('sem-animo'));
    }

    public function testJsConfigCarriesTheSameMapAsPhp(): void
    {
        $config = Pixelito::jsConfig();

        $this->assertSame(Pixelito::BASE, $config['base']);
        $this->assertSame(Pixelito::DEFAULT, $config['default']);
        foreach ($this->notificationTypes() as $type) {
            $this->assertSame(Pixelito::forNotificationType($type), $config['byType'][$type] ?? null, "tipo {$type} fora do mapa do JS");
        }
    }

    public function testBubbleIsDecorativeAndEscapesTheSize(): void
    {
        $html = Pixelito::bubble('falando', 'lg');

        $this->assertStringContainsString('pixelito-bubble--lg', $html);
        $this->assertStringContainsString('src="/assets/pixelito/falando.webp"', $html);
        $this->assertStringContainsString('alt=""', $html);

        $this->assertStringNotContainsString('"><script', Pixelito::bubble('falando', '"><script>'));
    }
}
