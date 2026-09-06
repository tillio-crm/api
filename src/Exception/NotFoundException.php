<?php

declare(strict_types=1);

namespace TillioCrm\Api\Exception;

/**
 * 404 - nie ma rekordu o tym id (albo ścieżka spoza API v2). Dla integracji to zwykle
 * sygnał, że mapowanie id po jej stronie jest nieaktualne: rekord skasowano w CRM.
 */
final class NotFoundException extends ApiException
{
}
