# Zgłoszenia

```php
use TillioCrm\Api\Dto\TicketInput;
use TillioCrm\Api\Dto\TicketMessageInput;

// Odczyt (flagi open/archived są boolami od API 2.0.4; SDK czyta też
// stringi "0"/"1" ze starszych instancji)
$page = $client->tickets()->list(['open' => true, 'contractorId' => 12345]);
foreach ($client->tickets()->iterate(['updatedAfter' => '2026-08-01T00:00:00+00:00']) as $ticket) {
    $ticket->title;
    $ticket->ticketStageId;    // etap w procesie obsługi (dictionaries()->ticketProcesses())
    $ticket->clientPanelUrl;   // link do panelu klienta, gdy założony
}
$ticket = $client->tickets()->get(31);

// Tworzenie (title wymagane; email wiąże zgłoszenie z nadawcą spoza CRM)
$result = $client->tickets()->create(new TicketInput(
    title: 'Awaria drukarki',
    description: 'Nie drukuje od rana.',
    email: 'zglaszajacy@przyklad.example',
    contractorId: 12345,
    createClientPanel: true,
));

// Aktualizacja: status/etap zgłoszenia zmienia PROCES obsługi, nie goły zapis -
// PUT przyjmuje tylko pola robocze (patrz PHPDoc TicketInput)
$client->tickets()->update($result->id ?? 0, new TicketInput(priority: 2, ownerUserId: 7));

// Wątek wiadomości
$messages = $client->tickets()->messages($result->id ?? 0);
$client->tickets()->addMessage($result->id ?? 0, new TicketMessageInput(
    text: 'Przyjęliśmy zgłoszenie, odezwiemy się w ciągu 24 h.',
    visibility: 'public',          // 'internal' = notatka wewnętrzna zespołu
));
```
