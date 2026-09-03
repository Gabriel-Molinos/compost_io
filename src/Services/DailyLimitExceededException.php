<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** Limite diário de gerações do site atingido (§95) — ver `ArticleService::createWithDailyLimit()`. */
final class DailyLimitExceededException extends RuntimeException
{
}
