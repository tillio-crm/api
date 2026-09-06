# Endpointy systemowe

```php
use TillioCrm\Api\TillioClient;

$client = new TillioClient([
    'apiKey'       => 'MojaIntegracja:sekret',
    'tenantDomain' => 'firma.tillio.app',
    'tenantId'     => 'firma-abc123',
]);

// health - trasa publiczna, działa też w trakcie przerwy serwisowej.
// Jedyne źródło wersji instancji (SDK wymaga >= 2.0.4).
$health = $client->health();
$version = $health['version'];          // np. "2.0.4"

// whoami - najtańszy test konfiguracji (klucz + nagłówki tenanta)
$who = $client->whoami();               // ['keyName' => ..., 'tenantDomain' => ..., 'userId' => ...]

// selfcheck - dryf między API a schematem CRM; HTTP 500 z raportem to WYNIK,
// nie awaria: metoda i tak zwraca raport (bez retry, dryf jest deterministyczny)
$report = $client->selfcheck();         // ['status' => 'ok'|'failed', 'core' => ..., 'database' => ...]

// openapi - pełna specyfikacja TEJ instancji (duża odpowiedź, nie wołaj w pętli)
$spec = $client->openapi();

// modules - które moduły CRM tenant ma włączone (sprawdź przed pisaniem w moduł)
foreach ($client->modules() as $module) {
    // $module->id ('tickets'), $module->active, $module->accessUntil
}
```
