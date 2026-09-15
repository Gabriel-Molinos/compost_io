<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testRequiredFailsOnEmpty(): void
    {
        $v = new Validator(['name' => ''], ['name' => ['required']], ['name' => 'Nome']);

        $this->assertTrue($v->fails());
        $this->assertSame('Nome é obrigatório.', $v->errors()['name']);
    }

    public function testRequiredPassesWhenFilled(): void
    {
        $v = new Validator(['name' => 'Danilo'], ['name' => ['required']]);

        $this->assertFalse($v->fails());
    }

    public function testEmailRejectsInvalidFormat(): void
    {
        $v = new Validator(['email' => 'nao-e-email'], ['email' => ['email']]);

        $this->assertTrue($v->fails());
    }

    public function testEmailAcceptsValidFormat(): void
    {
        $v = new Validator(['email' => 'a@b.com'], ['email' => ['email']]);

        $this->assertFalse($v->fails());
    }

    /** @dataProvider invalidUrls */
    public function testUrlRejectsInvalidValues(string $value): void
    {
        $v = new Validator(['url' => $value], ['url' => ['url']]);

        $this->assertTrue($v->fails(), "Esperava falhar para: {$value}");
    }

    /** @return list<list<string>> */
    public static function invalidUrls(): array
    {
        return [
            ['nao-e-url'],
            ['ftp:/malformado'],
        ];
    }

    public function testUrlAcceptsValidHttpsUrl(): void
    {
        $v = new Validator(['url' => 'https://exemplo.gov.br/pagina'], ['url' => ['url']]);

        $this->assertFalse($v->fails());
    }

    public function testUrlAllowsEmptyWhenNotRequired(): void
    {
        $v = new Validator(['url' => ''], ['url' => ['url']]);

        $this->assertFalse($v->fails());
    }

    public function testMaxRejectsTooLong(): void
    {
        $v = new Validator(['note' => str_repeat('a', 10)], ['note' => ['max:5']]);

        $this->assertTrue($v->fails());
    }

    public function testMinRejectsTooShort(): void
    {
        $v = new Validator(['note' => 'ab'], ['note' => ['min:5']]);

        $this->assertTrue($v->fails());
    }

    public function testInRejectsValueOutsideSet(): void
    {
        $v = new Validator(['status' => 'x'], ['status' => ['in:a,b,c']]);

        $this->assertTrue($v->fails());
    }

    public function testInAcceptsValueInsideSet(): void
    {
        $v = new Validator(['status' => 'b'], ['status' => ['in:a,b,c']]);

        $this->assertFalse($v->fails());
    }

    public function testStopsAtFirstErrorPerField(): void
    {
        // required falha primeiro — 'email' não deveria nem ser avaliado.
        $v = new Validator(['email' => ''], ['email' => ['required', 'email']], ['email' => 'E-mail']);

        $this->assertSame('E-mail é obrigatório.', $v->errors()['email']);
    }
}
