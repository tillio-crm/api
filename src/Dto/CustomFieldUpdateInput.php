<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Zmiana istniejącego pola niestandardowego (`PUT /v2/{entity}/custom-fields/{key}`,
 * `customFields()->update()`): przypisania do podtypów rekordów, dopisanie opcji
 * selecta i link pola URL - osobno albo razem w jednym żądaniu. Potrzebne jest
 * `assignedTo`, `options` albo `config`; input bez żadnego z nich to 422 `body.required`
 * (od API 2.17.0, wcześniej `assignedTo` było zawsze wymagane; `config` od API 2.18.0).
 *
 * Pozostałych własności pola (etykieta, typ, wymagalność, `editableBy`, formatowanie
 * liczb) API nie zmienia - to operacje w panelu CRM. Wymaga klucza API superadmina.
 */
final readonly class CustomFieldUpdateInput implements Arrayable
{
    /**
     * @param list<int>|'all'|null                     $assignedTo    KOMPLETNA lista docelowa id podtypów
     *                                                                rekordów (typy notatek, procesy zgłoszeń,
     *                                                                pozycje katalogu usług, procesy leadowe,
     *                                                                lejki) albo `'all'` - wszystkie podtypy
     *                                                                istniejące w chwili zapisu (API >= 2.17.0).
     *                                                                Podtypu założonego później CRM sam nie dopnie:
     *                                                                powtórz zapis z `'all'`, lista tylko urośnie.
     *                                                                Tylko encje `note`, `ticket`, `service`,
     *                                                                `lead`, `pipeline` - przy innej encji to 422
     *                                                                na `assignedTo`
     * @param bool|null                                $allowUnassign zgoda na usunięcie przypisań - CRM KASUJE
     *                                                                wtedy wartości pola w rekordach usuwanych
     *                                                                podtypów; bez niej zwężenie listy to 422
     *                                                                `customField.unassignNotConfirmed`
     * @param list<string|CustomFieldOptionInput>|null $options       opcje do DOPISANIA do pola SELECT albo
     *                                                                MULTISELECT (API >= 2.17.0, każda encja).
     *                                                                Nazwy już obecne w polu API pomija, istniejące
     *                                                                opcje zostają bez zmian (id, kolor, kolejność),
     *                                                                więc pełną listę oczekiwanych opcji można
     *                                                                wysyłać przy każdej synchronizacji. Usunięcia
     *                                                                ani zmiany nazwy API nie robi. Pole innego
     *                                                                typu to 422 na `options`
     * @param array{anchor?: string|null, target?: string}|null $config link pola URL (API >= 2.18.0):
     *                                                                `anchor` - tekst linku (pusty albo null =
     *                                                                CRM pokazuje sam adres), `target` -
     *                                                                `CustomFieldInput::LINK_*`. Niepodany klucz
     *                                                                zostaje bez zmian. Inne klucze i pole innego
     *                                                                typu niż URL to 422 na `config`
     */
    public function __construct(
        public array|string|null $assignedTo = null,
        public ?bool $allowUnassign = null,
        public ?array $options = null,
        public ?array $config = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'assignedTo' => $this->assignedTo,
            'allowUnassign' => $this->allowUnassign,
            'options' => $this->options === null ? null : CustomFieldOptionInput::listPayload($this->options),
            'config' => $this->config,
        ]);
    }
}
