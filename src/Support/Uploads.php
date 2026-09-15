<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Upload de imagem enviada pelo usuário (foto de perfil, logo de site) —
 * pedido do responsável na sessão de redesign visual (2026-09-04). Reaproveita
 * o conversor que já existia pras imagens de artigo (ImageConverter, Fase 5),
 * só que salvando em disco (public/assets/uploads/) em vez de mandar pro WordPress.
 *
 * Nome de arquivo fixo por id (ex.: "assets/uploads/avatars/7.webp") — cada novo
 * envio sobrescreve o anterior, sem acumular lixo em disco nem precisar de
 * uma tabela/coluna de "arquivo antigo pra apagar depois".
 */
final class Uploads
{
    private const MAX_BYTES = 5 * 1024 * 1024; // 5 MB — foto de perfil/logo, não banco de imagens
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /**
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int}|null $file entrada crua de $_FILES['campo']
     * @return string|null caminho público relativo (ex.: "assets/uploads/avatars/7.webp"); null se nenhum arquivo foi escolhido
     * @throws RuntimeException mensagem já em pt-BR, pronta para virar erro de formulário
     */
    public static function image(?array $file, string $subdir, int $id, int $maxWidth = 480): ?string
    {
        if ($file === null || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null; // campo opcional — ninguém escolheu arquivo
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha no envio do arquivo — tente novamente.');
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new RuntimeException('Imagem maior que 5 MB — escolha um arquivo menor.');
        }
        if (!is_uploaded_file((string) $file['tmp_name'])) {
            throw new RuntimeException('Upload inválido.');
        }

        $bytes = file_get_contents((string) $file['tmp_name']);
        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('Não foi possível ler o arquivo enviado.');
        }

        return self::fromBytes($bytes, $subdir, $id, $maxWidth);
    }

    /**
     * Mesmo pipeline de `image()` (valida, redimensiona, salva), mas a
     * partir de um arquivo já em disco no servidor — usado pela biblioteca
     * de logos por domínio (`SiteLogoLibraryService`), que não passa por
     * `$_FILES`/upload HTTP nenhum, então não faz sentido (nem passaria)
     * pelo `is_uploaded_file()` de `image()`.
     *
     * @throws RuntimeException mensagem já em pt-BR, pronta para virar erro de formulário
     */
    public static function fromLocalFile(string $localPath, string $subdir, int $id, int $maxWidth = 480): string
    {
        if (!is_file($localPath)) {
            throw new RuntimeException('Arquivo de origem não encontrado.');
        }
        if (filesize($localPath) > self::MAX_BYTES) {
            throw new RuntimeException('Imagem maior que 5 MB.');
        }

        $bytes = file_get_contents($localPath);
        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('Não foi possível ler o arquivo de origem.');
        }

        return self::fromBytes($bytes, $subdir, $id, $maxWidth);
    }

    /** @throws RuntimeException mensagem já em pt-BR, pronta para virar erro de formulário */
    private static function fromBytes(string $bytes, string $subdir, int $id, int $maxWidth): string
    {
        // getimagesizefromstring() em vez de finfo/ext-fileinfo — essa extensão
        // não está garantida no ambiente (confirmado ausente aqui), enquanto
        // `gd` já é uma dependência obrigatória do upload (ImageConverter
        // abaixo). Também serve de validação: decodifica o cabeçalho de
        // verdade, não só confia numa extensão de nome de arquivo.
        $info = @getimagesizefromstring($bytes);
        $mime = $info['mime'] ?? null;
        if ($mime === null || !in_array($mime, self::ALLOWED_MIME, true)) {
            throw new RuntimeException('Formato não suportado — envie uma imagem JPG, PNG, WebP ou GIF.');
        }

        // Redimensiona + recodifica em WebP (ImageConverter::forWeb já cai pra
        // JPEG/original se `gd`/webp não estiver disponível — nunca quebra).
        $converted = ImageConverter::forWeb($bytes, $maxWidth, quality: 85);
        $useBytes = $converted['ext'] !== '' ? $converted['bytes'] : $bytes;
        $ext = $converted['ext'] !== '' ? $converted['ext'] : self::extFromMime($mime);

        $dir = dirname(__DIR__, 2) . '/public/assets/uploads/' . $subdir;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Não foi possível preparar a pasta de upload no servidor.');
        }

        // Remove qualquer versão anterior com outra extensão (ex.: trocou de
        // gd-com-webp pra sem-webp entre dois uploads) — nunca deixa órfão.
        foreach (glob($dir . '/' . $id . '.*') ?: [] as $old) {
            @unlink($old);
        }

        $relative = 'assets/uploads/' . $subdir . '/' . $id . '.' . $ext;
        if (file_put_contents(dirname(__DIR__, 2) . '/public/' . $relative, $useBytes) === false) {
            throw new RuntimeException('Não foi possível salvar a imagem no servidor.');
        }

        return $relative;
    }

    /** Remove o arquivo de avatar/logo atual, se existir (checkbox "remover foto"). */
    public static function delete(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }
        $full = dirname(__DIR__, 2) . '/public/' . $relativePath;
        if (is_file($full)) {
            @unlink($full);
        }
    }

    private static function extFromMime(string $mime): string
    {
        return match ($mime) {
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            default      => 'jpg',
        };
    }
}
