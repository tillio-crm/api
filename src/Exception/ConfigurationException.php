<?php

declare(strict_types=1);

namespace TillioCrm\Api\Exception;

/**
 * Błąd konfiguracji klienta - zgłaszany W KONSTRUKTORZE, nie przy pierwszym żądaniu.
 *
 * DLACZEGO tak wcześnie: klient bywa budowany przy starcie aplikacji, a pierwsze
 * żądanie leci dopiero w jobie w tle. Zła konfiguracja wykryta dopiero tam kosztuje
 * cały nieudany przebieg zamiast czerwonego deployu.
 */
final class ConfigurationException extends TillioApiException
{
}
