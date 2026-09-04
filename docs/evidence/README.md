# Evidence

Katalog przeznaczony na krótkie, tekstowe dowody zamknięcia etapów.

Nie przechowujemy tu:

- binarnych logów,
- `vendor/`,
- dumpów bazy,
- artefaktów runtime.

## Konwencja nazw

Dla każdego zamykanego etapu powstaje jeden plik nazwany identyfikatorem i slugiem karty backlogu:

```text
P005-infrastructure-bootstrap.md
P006-application-error-handling.md
...
P011-final-documentation-and-delivery.md
```

## Wymagana zawartość

Dowód powinien zawierać:

- identyfikator zadania i datę,
- `START_HEAD` oraz `END_HEAD`,
- stan working tree przed pracą,
- listę wykonanych komend z kodami wyjścia i wynikiem PASS/FAIL,
- najważniejsze liczby testów,
- świadome odchylenia od pełnego PASS,
- SHA-256 artefaktu, jeśli etap generuje paczkę.

Jeżeli podczas etapu znaleziono błąd, opisujemy: objaw, root cause, klasyfikację, fix i wynik ponownej weryfikacji.

Nie kopiujemy całych logów — wystarczą rozstrzygające fragmenty.

## Klasyfikacja niepowodzeń

```text
INFRASTRUCTURE_FAILURE        awaria środowiska, naprawiana w bieżącym etapie
QUALITY_TOOLING_FAILURE       błędna konfiguracja narzędzia jakościowego
APPLICATION_BASELINE_FAILURE  czerwony wynik wynikający z kodu biznesowego objętego późniejszym etapem
```

`APPLICATION_BASELINE_FAILURE` nie blokuje zamknięcia etapu infrastrukturalnego, o ile narzędzie zostało poprawnie uruchomione, a kod wyjścia został przekazany.

## Dostępne dowody

- [`P005-infrastructure-bootstrap.md`](P005-infrastructure-bootstrap.md) — środowisko DEV/TEST, quality tooling, eksport źródeł
