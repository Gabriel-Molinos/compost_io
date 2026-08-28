<?php

declare(strict_types=1);

namespace App\Integrations\Image;

/**
 * Contrato do serviço de geração de imagens (integracoes.md §36 e §41). O resto
 * da aplicação depende desta interface, não do Nano Banana — trocar de
 * fornecedor/modelo não deve exigir mudança nos módulos editoriais.
 *
 * Implementação atual: `NanoBanana\NanoBananaProvider`.
 */
interface ImageProvider
{
    /**
     * Gera uma imagem a partir de um brief visual.
     *
     * @throws ImageException
     */
    public function generate(ImageRequest $request): ImageResult;
}
