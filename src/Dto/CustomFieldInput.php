<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Nowa definicja pola niestandardowego (`POST /v2/custom-fields`). Wymagane
 * `entity`, `name`, `type`; założenie wymaga klucza API o uprawnieniach
 * administratora.
 *
 * PUŁAPKI (potwierdzone na żywym API):
 *  - ZAWSZE podawaj `editableBy` z użytkownikiem, na którym działa klucz API -
 *    pole założone bez tego przyjmuje odczyt, ale KAŻDY zapis wartości kończy
 *    się 422, także na rekordach własnych integracji, a definicji nie da się
 *    potem poprawić (trzeba założyć nowe pole),
 *  - wyszukiwanie duplikatów (`duplicateCheck: ["custom:<key>"]`) działa tylko
 *    dla typów INT/STR/VARCHAR - pole typu wyboru odpadnie przy zapisie,
 *  - etykieta (`name`) jest unikalna w encji - zakładanie rób idempotentnie:
 *    najpierw odczytaj listę pól, twórz tylko brakujące,
 *  - klucz pola nadaje CRM (wraca w odpowiedzi) - nie da się go narzucić.
 *
 * Pole typu URL (link): `config: ['anchor' => 'Otwórz w Tillio Calls', 'target' => CustomFieldInput::LINK_NEW_TAB]`
 * - tekst linku zamiast adresu i sposób otwarcia (API >= 2.18.0 sprawdza `target`; starsze instancje
 * zapisywały wartość spoza listy po cichu jako nową kartę). Zmiana po założeniu:
 * `customFields()->updateLink()`.
 */
final readonly class CustomFieldInput implements Arrayable
{
    /** Pole URL: link otwiera się w nowej karcie (domyślnie). */
    public const string LINK_NEW_TAB = '_blank';

    /** Pole URL: link otwiera się w tej samej karcie. */
    public const string LINK_SAME_TAB = '_self';

    /** Pole URL: link otwiera się w ramce w CRM (strona docelowa musi pozwalać na osadzanie). */
    public const string LINK_IFRAME = 'iframe';

    /**
     * @param string|null               $entity     encja pola (np. `contractor`)
     * @param list<string|CustomFieldOptionInput>|null $options
     *                                              opcje pól wyboru (SELECT/MULTISELECT): nazwy albo
     *                                              `CustomFieldOptionInput` z kolorem. Opcje do
     *                                              istniejącego pola: `customFields()->appendOptions()`
     * @param array<string, mixed>|null $config     konfiguracja typu: formatowanie liczb (`INT`:
     *                                              `option`, `precision`, `position`, `customLabel`,
     *                                              `currencyCode`) albo link pola `URL` (`anchor` - tekst
     *                                              linku, do 100 znaków; `target` - `LINK_*`). Klucz
     *                                              niepasujący do typu pola to 422 (API >= 2.18.0)
     * @param list<int>|'all'|null      $assignedTo id PODTYPÓW rekordów, w których pole działa - NIE
     *                                              użytkowników (dostęp ustawia `editableBy`). Wymagane
     *                                              dla encji z przypisaniami: `note` (typy notatek),
     *                                              `ticket` (procesy zgłoszeń), `service` (pozycje
     *                                              katalogu usług), `lead` (procesy leadowe),
     *                                              `pipeline` (lejki sprzedaży). Nieistniejący podtyp
     *                                              to 422 i pole nie powstaje (od API 2.14.0).
     *                                              `'all'` = wszystkie podtypy istniejące w chwili
     *                                              zapisu (API >= 2.17.0); podtyp założony później
     *                                              dopina `customFields()->update()` z tym samym `'all'`
     * @param array{userIds?: list<int>, departmentIds?: list<int>, groupIds?: list<int>}|null $editableBy
     *                                              ACL pola: kto widzi i edytuje wartości
     *                                              (jednolity kształt ACL API; null = wszyscy) -
     *                                              podaj ZAWSZE (patrz opis klasy). Inny kształt
     *                                              (`userId`, płaska lista id) to 422 od API 2.14.0
     */
    public function __construct(
        public ?string $entity = null,
        public ?string $name = null,
        public ?string $type = null,
        public ?array $options = null,
        public ?array $config = null,
        public ?bool $required = null,
        public array|string|null $assignedTo = null,
        public ?array $editableBy = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'entity' => $this->entity,
            'name' => $this->name,
            'type' => $this->type,
            'options' => $this->options === null ? null : CustomFieldOptionInput::listPayload($this->options),
            'config' => $this->config,
            'required' => $this->required,
            'assignedTo' => $this->assignedTo,
            'editableBy' => $this->editableBy,
        ]);
    }
}
