<?php

declare(strict_types=1);

namespace TillioCrm\Api\Exception;

/**
 * Błąd zgłoszony przez PROXY PLATFORMY, zanim żądanie dotarło do API v2 - albo
 * po drodze z odpowiedzią. Występuje wyłącznie w trybie proxy (marketplace).
 *
 * DLACZEGO osobny typ: apka, która dostaje 403, musi wiedzieć, KTO odmówił.
 * 403 z API v2 to uprawnienia klucza w CRM; 403 z proxy to allowlista ścieżek
 * adaptera platformy - naprawia się je w zupełnie innych miejscach. Podobnie 502
 * z proxy ("zewnętrzne API nieosiągalne") kontra 502 z samego API.
 *
 * JAK ROZPOZNAJEMY proxy: platforma zwraca tę samą kopertę `_error`, ale w polu
 * `message` niesie ludzkie zdanie ("Operacja GET ... nie jest dozwolona przez
 * adapter..."), podczas gdy API v2 zawsze daje tam kod symboliczny
 * (`validation.error`, `tenant.blocked`). Szczegóły w `ErrorMapper`.
 */
class ProxyException extends ApiException
{
}
