# Refinement P011 — Final Documentation and Delivery

## Status

**Gotowy, blokowany przez P005–P010**

## Cel

Zamknąć zadanie w stanie możliwym do review bez znajomości historii naszej pracy.

## README finalne

README musi samodzielnie wyjaśnić:

- jak uruchomić projekt,
- jak uruchomić testy i quality gate,
- co było przyczyną problemu 1,
- jak naprawiono problem 2,
- jak zdiagnozowano problem 3,
- jak działa RejectSalesDocument,
- dlaczego pozostaliśmy na Symfony 7.4,
- jakie kompromisy były świadome.

## Uwaga o problemie 3

Opis diagnozy musi być prawdziwy.

Jeżeli finalnie diagnoza była wykonana przez audyt przepływu i regresję, piszemy dokładnie to.

Nie deklarujemy Xdebug tylko dlatego, że TASK go sugerował.

## Dokumenty

Do stanu finalnego aktualizujemy:

```text
docs/README.md
docs/04-solution-design.md
docs/05-test-plan.md
docs/06-implementation-summary.md
docs/backlog/BACKLOG.md
```

Karty P005–P011 mają rzeczywiste statusy.

## Quality gate

Wymagane przed finalnym commitem:

```bash
make cs-check
make phpstan
make deptrac
make test
make verify
```

Wszystkie muszą zakończyć się 0.

## Git

- working tree clean,
- final commit wypchnięty,
- brak przypadkowych plików runtime,
- brak sekretów.

## Eksport

Finalny artefakt:

```bash
make export-source-committed
```

Nie używamy working-tree export jako finalnego deliverable.

Wynik zapisujemy:

```text
EXPORT_PATH
EXPORT_SHA256
EXPORT_COMMIT
```

## Manual review

Przed wysyłką:

- otworzyć ZIP,
- sprawdzić brak `vendor/`, `var/`, `.git/`,
- sprawdzić obecność `README.md`, `TASK.MD`, `src/`, `tests/`, `docs/`, `Makefile`, `infrastructure/`,
- potwierdzić, że instrukcje README działają od clean checkout.

## Done

P011 = `DONE_AND_VERIFIED` oznacza „gotowe do wysłania”, nie tylko „kod działa lokalnie”.
