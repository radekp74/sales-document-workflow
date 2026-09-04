# P008 — Ownership Mapping Fix

## Status

**DONE_AND_VERIFIED** — 2026-09-04

Dowód: [`../evidence/P008-ownership-mapping-fix.md`](../evidence/P008-ownership-mapping-fix.md)

## Priorytet

**P0**

## Problem

HTTP create zamienia `contractor_id` i `created_by` w `resolveDocumentOwnership()`.

Direct command path jest poprawny, dlatego błąd nie występuje „za każdym razem”.

## Cel

Przywrócić semantykę 1:1 na ścieżce HTTP i dodać regresję wykrywającą tę klasę błędu.

## Zależności

- P005

## Scope

- poprawka `resolveDocumentOwnership()`,
- test HTTP zapisanych pól,
- udokumentowanie ścieżki diagnostycznej,
- sprawdzenie snapshotu po approve.

## Out of scope

- rename `sellerSnapshot`,
- zmiana modelu ownership,
- migracja istniejących danych,
- nowe endpointy.

## Acceptance Criteria

1. request `contractor_id=77` zapisuje contractorId=77,
2. request `created_by=5` zapisuje createdBy=5,
3. direct command path pozostaje bez regresji,
4. approval/order nie odwraca poprawionych wartości,
5. test wcześniej niewidzący problemu teraz jawnie asertywnie sprawdza oba pola,
6. README finalnie opisuje, dlaczego problem pojawiał się tylko w części ścieżek.

## Definition of Done

- minimalna poprawka mapowania,
- regresja zielona,
- diagnosis trail gotowy do P011,
- evidence zapisane,
- commit etapu wykonany.

## Refinement

[`../refinement/P008-refinement.md`](../refinement/P008-refinement.md)
