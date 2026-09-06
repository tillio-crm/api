<?php

declare(strict_types=1);

namespace TillioCrm\Api\Exception;

/**
 * Proxy platformy NIE przepuszcza odpowiedzi binarnych (przepuszcza JSON, XML
 * i text/*) - pliki pobiera się podpisanymi URL-ami (`downloadUrl` z metadanych
 * dokumentu), zwykłym GET-em wprost do storage'u, bez nagłówków Tillio.
 *
 * Ten wyjątek zamienia ogólne 502 "nieobsługiwany typ treści" na jasny komunikat
 * z tą instrukcją. DETERMINISTYCZNY - retry nie ma sensu (w odróżnieniu od innych
 * 5xx z proxy), dlatego polityka ponowień traktuje go osobno.
 */
final class ProxyBinaryResponseException extends ProxyException
{
}
