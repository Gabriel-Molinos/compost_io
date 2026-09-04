<?php

declare(strict_types=1);

use App\View;

/** @var int $status */
/** @var string $message */
?>
<h1 class="font-display text-3xl font-bold text-text-primary"><?= View::e($status) ?></h1>
<p class="mt-2 text-sm text-text-secondary"><?= View::e($message) ?></p>
<p class="mt-6">
    <a href="/" class="text-sm text-cyan underline underline-offset-4 hover:text-cyan-bright">Voltar ao início</a>
</p>
