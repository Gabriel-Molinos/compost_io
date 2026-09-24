<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ImageUploadValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ImageUploadValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('imagewebp')) {
            $this->markTestSkipped('Extensão gd sem suporte a WebP.');
        }
    }

    private function webp(int $w, int $h): string
    {
        $img = imagecreatetruecolor($w, $h);
        ob_start();
        imagewebp($img, null, 60);

        return (string) ob_get_clean();
    }

    public function testAcceptsWebpInRange(): void
    {
        $this->assertSame(['width' => 1200, 'height' => 675], ImageUploadValidator::validate($this->webp(1200, 675)));
        $this->assertSame(['width' => 1600, 'height' => 900], ImageUploadValidator::validate($this->webp(1600, 900)));
    }

    public function testToleratesPixelRoundingInRatio(): void
    {
        $this->assertSame(1201, ImageUploadValidator::validate($this->webp(1201, 675))['width']);
    }

    public function testRejectsNonWebpEvenIfExtensionWouldLie(): void
    {
        $img = imagecreatetruecolor(1200, 675);
        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/não é WebP/');
        ImageUploadValidator::validate($png);
    }

    public function testRejectsTooNarrow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/800 px de largura.*1200/');
        ImageUploadValidator::validate($this->webp(800, 450));
    }

    public function testRejectsTooWide(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/3000 px de largura/');
        ImageUploadValidator::validate($this->webp(3000, 1688));
    }

    public function testRejectsWrongRatio(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/1200×1200.*16:9/');
        ImageUploadValidator::validate($this->webp(1200, 1200));
    }

    public function testRejectsEmptyAndOversize(): void
    {
        try {
            ImageUploadValidator::validate('');
            $this->fail('vazio deveria falhar');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('vazio', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/limite é 2 MB/');
        ImageUploadValidator::validate(str_repeat('x', ImageUploadValidator::MAX_BYTES + 1));
    }

    public function testMinHeightMatchesRatio(): void
    {
        $this->assertSame(675, ImageUploadValidator::minHeight());
    }
}
