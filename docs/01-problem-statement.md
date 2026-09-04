# Sales Document Workflow — opis problemu i zakres zadania

## 1. Cel dokumentu

Celem dokumentu jest utrwalenie źródłowego zgłoszenia dotyczącego procesu obsługi dokumentów sprzedażowych oraz jego uporządkowanie na potrzeby analizy technicznej i dalszych prac implementacyjnych.

Dokument rozdziela:

- treść przekazaną przez zespół i support,
- obserwowane symptomy,
- oczekiwane zachowanie systemu,
- zakres zadania,
- ograniczenia architektoniczne,
- kryteria akceptacji.

Dokument nie zawiera jeszcze analizy przyczyn źródłowych ani projektu rozwiązania. Te elementy powinny zostać opisane osobno po wykonaniu analizy technicznej.

---

## 2. Kontekst systemu

System obsługuje dokumenty sprzedażowe, w szczególności oferty i zamówienia.

Operacje wykonywane są w architekturze CQRS, w której żądanie HTTP trafia do kontrolera, a następnie odpowiednia komenda jest przekazywana przez `command.bus` do handlera.

Podstawowy przepływ wygląda następująco:

```text
Żądanie HTTP
    ↓
SalesDocumentController
    ↓
command.bus
    ↓
Command
    ↓
Command Handler
    ↓
Persistencja / efekty uboczne
```

Zgodnie z założeniami projektu oczekiwane jest zachowanie stylu CQRS. Zadanie nie zakłada migracji aplikacji do DDD ani wprowadzania warstw `Domain`, `Application` i `Infrastructure` tylko dla potrzeb tej zmiany.

---

## 3. Źródło zgłoszenia

Źródłem niniejszego dokumentu jest specyfikacja przekazana zespołowi w pliku `TASK.MD`.

### 3.1. Oryginalny opis sytuacji

> System tworzy i zatwierdza dokumenty sprzedażowe (oferty i zamówienia)
> przez `SalesDocumentController` → `command.bus`. Kod działa i ma testy, ale zespół zgłasza kilka
> niezależnych, realnych problemów:

### 3.2. Oryginalna lista zgłoszonych problemów

> 1. Czasem zatwierdzenie "wygląda na nieudane" (klient dostaje błąd 500),
>    mimo że w bazie widać poprawnie zatwierdzony dokument.
>
> 2. `SalesDocumentController::approve()` zwraca **500 z surowym
>    komunikatem wyjątku** dla każdego możliwego błędu - także dla
>    sytuacji, które wcale nie są błędem serwera (np. nieistniejące ID
>    dokumentu powinno dać 404, nie 500). Do tego kontroler sam sobie robi
>    zapytanie SQL do bazy zamiast skorzystać z istniejącego repozytorium.
>
> 3. **Zgłoszenie od supportu, którego nikt jeszcze nie zdiagnozował:**
>
>    "część nowo utworzonych dokumentów sprzedażowych w raportach ma
>    pomieszane dane właściciela - kontrahent i osoba tworząca dokument są
>    jakby zamienione miejscami, ale nie za każdym razem i nie widać tego w
>    żadnym z naszych dotychczasowych testów".

Powyższa treść stanowi źródłowe zgłoszenie. Poniższe sekcje są jego uporządkowaną interpretacją na potrzeby pracy zespołu.

---

## 4. Problem 1 — zatwierdzenie wygląda na nieudane mimo poprawnego zapisu

### 4.1. Zgłoszony symptom

Podczas zatwierdzania dokumentu klient czasami otrzymuje odpowiedź:

```text
HTTP 500 Internal Server Error
```

Jednocześnie po sprawdzeniu bazy danych dokument widoczny jest jako poprawnie zatwierdzony.

### 4.2. Problem biznesowy

Powstaje rozbieżność pomiędzy odpowiedzią zwracaną klientowi a rzeczywistym stanem systemu:

```text
odpowiedź API: operacja nieudana
stan danych:   operacja wykonana
```

Klient nie może na podstawie odpowiedzi API jednoznacznie określić, czy powinien ponowić operację.

### 4.3. Potencjalny wpływ

Problem może prowadzić do:

- ponawiania operacji, która już została wykonana,
- wykonywania niepotrzebnych działań przez użytkownika,
- dodatkowych zgłoszeń do supportu,
- utraty zaufania do odpowiedzi API,
- trudności z ustaleniem rzeczywistego rezultatu operacji,
- ryzyka niepożądanych skutków przy ponownym wywołaniu procesu.

### 4.4. Oczekiwane zachowanie

Odpowiedź API powinna być zgodna z ostatecznym rezultatem głównej operacji biznesowej.

Jeżeli operacja zatwierdzenia została skutecznie zakończona i jej wynik został trwale zapisany, błąd operacji pomocniczej nie powinien powodować przedstawienia całej operacji klientowi jako nieudanej.

---

## 5. Problem 2 — wszystkie błędy zatwierdzania są zwracane jako HTTP 500

### 5.1. Zgłoszony symptom

`SalesDocumentController::approve()` zwraca `500 Internal Server Error` dla różnych rodzajów problemów, w tym dla sytuacji, które nie są błędami serwera.

Przykładem wskazanym w zgłoszeniu jest próba zatwierdzenia dokumentu o nieistniejącym identyfikatorze.

### 5.2. Problem klasyfikacji błędów

Następujące sytuacje mają odmienną semantykę i nie powinny być traktowane jednakowo:

- dokument nie istnieje,
- dokument istnieje, ale operacja jest niedozwolona w jego aktualnym stanie,
- wystąpił rzeczywisty, nieoczekiwany błąd techniczny.

### 5.3. Ujawnianie komunikatów wyjątków

Kontroler zwraca klientowi surowy komunikat pochodzący z wyjątku.

Publiczny kontrakt HTTP nie powinien być bezpośrednio zależny od wewnętrznych komunikatów wyjątków. Odpowiedź API powinna zawierać stabilną, kontrolowaną informację przeznaczoną dla klienta.

### 5.4. Bezpośredni dostęp do bazy z kontrolera

W zgłoszeniu wskazano również, że kontroler wykonuje własne zapytanie SQL mimo istnienia repozytorium.

Powoduje to przenikanie szczegółów warstwy persystencji do warstwy HTTP i omijanie istniejącej abstrakcji dostępu do danych.

### 5.5. Oczekiwane zachowanie

API powinno rozróżniać co najmniej:

#### Brak zasobu

Dla nieistniejącego dokumentu oczekiwane jest:

```text
404 Not Found
```

#### Niedozwolona operacja w aktualnym stanie

Dla istniejącego dokumentu, na którym nie można wykonać żądanej operacji, API powinno zwrócić kod odpowiadający konfliktowi lub błędowi semantycznemu, np.:

```text
409 Conflict
```

lub:

```text
422 Unprocessable Entity
```

Dokładny kod powinien wynikać z przyjętej konwencji API.

#### Nieoczekiwany błąd techniczny

Rzeczywisty błąd serwera powinien pozostać błędem klasy `5xx`, ale bez ujawniania klientowi niekontrolowanych szczegółów implementacyjnych.

---

## 6. Problem 3 — pomieszane dane kontrahenta i osoby tworzącej dokument

### 6.1. Oryginalne zgłoszenie supportu

> "część nowo utworzonych dokumentów sprzedażowych w raportach ma
> pomieszane dane właściciela - kontrahent i osoba tworząca dokument są
> jakby zamienione miejscami, ale nie za każdym razem i nie widać tego w
> żadnym z naszych dotychczasowych testów".

### 6.2. Zgłoszony symptom

W części nowo utworzonych dokumentów dane odpowiadające:

- kontrahentowi,
- osobie tworzącej dokument,

są raportowane tak, jakby zostały zamienione miejscami.

### 6.3. Charakter problemu

Zgłoszenie podkreśla, że problem:

- nie występuje dla każdego dokumentu,
- nie jest wykrywany przez obecny zestaw testów,
- nie został jeszcze zdiagnozowany przez zespół.

Na tym etapie nie należy zakładać przyczyny. Należy ustalić, od jakiej ścieżki wykonania zależy wystąpienie błędu oraz dlaczego istniejące testy go nie wykrywają.

### 6.4. Oczekiwane zachowanie

Dane kontrahenta oraz użytkownika tworzącego dokument muszą zachowywać swoje znaczenie na całej ścieżce przetwarzania.

Przykładowo dla danych wejściowych:

```json
{
  "contractor_id": 77,
  "created_by": 5
}
```

oczekiwany stan danych to:

```text
contractor_id = 77
created_by    = 5
```

niezależnie od wspieranej ścieżki utworzenia dokumentu.

---

## 7. Dodatkowe wymaganie funkcjonalne — odrzucenie dokumentu

Oprócz naprawy zgłoszonych problemów zakres zadania obejmuje dodanie nowej operacji w istniejącym stylu CQRS.

### 7.1. Oryginalne wymaganie

> Dodaj własną, nową operację w tym samym stylu - w
> `tests/Functional/RejectSalesDocumentHandlerTest.php` dostajesz test
> dla `RejectSalesDocument`/`RejectSalesDocumentHandler`, których **jeszcze
> nie ma w kodzie**. Na podstawie samego testu zaprojektuj i zaimplementuj
> brakujący kod.

### 7.2. Znaczenie wymagania

Test funkcjonalny stanowi kontrakt wymaganej funkcjonalności. Implementacja powinna zostać zaprojektowana na podstawie oczekiwanego zachowania wynikającego z testu oraz istniejącego stylu projektu.

Na tym etapie dokument nie przesądza szczegółów implementacyjnych.

---

## 8. Stan testów przekazanych wraz z zadaniem

Źródłowa specyfikacja wskazuje następujący stan początkowy:

### 8.1. `ApproveSalesDocumentTest`

`tests/Functional/ApproveSalesDocumentTest.php` nie przechodzi w jednym z dwóch przypadków. Niepowodzenie odpowiada Problemowi 1.

Treść asercji testu dotyczącego tego przypadku nie powinna zostać zmieniona w celu sztucznego uzyskania zielonego wyniku.

### 8.2. `SalesDocumentControllerTest`

`tests/Functional/SalesDocumentControllerTest.php` dokumentuje obecne, błędne zachowanie poprzez test:

```text
testApprovingMissingDocumentCurrentlyReturns500
```

Po poprawieniu zachowania kontrolera test powinien zostać zaktualizowany tak, aby opisywał poprawny kontrakt HTTP.

### 8.3. Happy path

Dwa wskazane w zadaniu scenariusze happy-path muszą pozostać poprawne przed i po zmianach:

```text
testApprovingAQuoteSpawnsALinkedOrder...
```

oraz:

```text
testCreateAndApproveThroughHttp
```

Ich istniejące asercje nie powinny być zmieniane tylko po to, aby dostosować test do implementacji.

### 8.4. `RejectSalesDocumentHandlerTest`

`tests/Functional/RejectSalesDocumentHandlerTest.php` opisuje oczekiwane zachowanie dla nieistniejących jeszcze elementów:

```text
RejectSalesDocument
RejectSalesDocumentHandler
```

Test ma zostać potraktowany jako kontrakt do implementacji.

---

## 9. Zakres prac

### 9.1. Diagnostyka

Należy:

- odtworzyć Problem 1,
- ustalić przyczynę rozbieżności między odpowiedzią API a stanem bazy,
- przeanalizować granice transakcji oraz operacje wykonywane w procesie zatwierdzania,
- zdiagnozować Problem 3,
- ustalić ścieżkę, która prowadzi do niepoprawnych danych,
- ustalić, dlaczego dotychczasowe testy nie wykrywają Problem 3.

### 9.2. Naprawa procesu zatwierdzania

Należy doprowadzić do sytuacji, w której wynik zwracany klientowi poprawnie odzwierciedla rezultat operacji biznesowej.

### 9.3. Obsługa błędów API

Należy:

- rozróżnić brak zasobu od błędu serwera,
- rozróżnić niedozwolony stan biznesowy od błędu technicznego,
- nie ujawniać surowych komunikatów wyjątków,
- zaktualizować test dokumentujący obecne `500` dla brakującego dokumentu.

### 9.4. Dostęp do danych

Kontroler nie powinien wykonywać własnego zapytania SQL, jeżeli wymagany dostęp do danych może zostać zrealizowany przez istniejące repozytorium.

### 9.5. Poprawność danych

Należy:

- znaleźć i usunąć przyczynę zamiany danych kontrahenta i twórcy dokumentu,
- dodać regresję testową zabezpieczającą poprawne zachowanie.

### 9.6. Nowa operacja

Należy zaprojektować i zaimplementować operację odrzucenia dokumentu zgodnie z kontraktem wynikającym z dostarczonego testu oraz stylem istniejących komend i handlerów.

---

## 10. Ograniczenia i założenia

### 10.1. Architektura

Projekt pozostaje aplikacją CQRS opartą o Command → Handler przez `command.bus`.

Nie jest wymagane wprowadzanie DDD ani nowego wielowarstwowego podziału architektonicznego.

### 10.2. Dodatkowa kolejka

Dla Problemu 1 nie należy wprowadzać drugiej szyny ani dodatkowej kolejki asynchronicznej tylko po to, aby rozwiązać zgłoszony symptom.

Specyfikacja wskazuje wprost, że problem można i należy rozwiązać bez tego.

### 10.3. Wersja Symfony

Projekt celowo wykorzystuje Symfony 7.4.

Aktualizacja zależności jest dopuszczalna tylko wtedy, gdy istnieje konkretny powód techniczny. Decyzja o aktualizacji albo pozostaniu przy obecnych wersjach powinna zostać później uzasadniona w `README.md`.

### 10.4. Diagnostyka Problemu 3

Specyfikacja rekomenduje uruchomienie projektu oraz wykorzystanie debugera, w szczególności Xdebug, podczas analizy Problemu 3.

---

## 11. Poza zakresem

Bez dodatkowego uzasadnienia poza zakresem pozostają:

- migracja projektu do DDD,
- budowa nowego systemu kolejkowego,
- event sourcing,
- gruntowna przebudowa całego modelu aplikacji,
- aktualizacja frameworka niezwiązana z rozwiązaniem problemu,
- zmiany w niezwiązanych obszarach systemu.

---

## 12. Kryteria akceptacji

Prace można uznać za zakończone, gdy:

1. test odtwarzający Problem 1 przechodzi bez zmiany jego istniejących asercji,
2. poprawnie wykonana operacja zatwierdzenia nie jest raportowana jako nieudana z powodu niezależnego błędu pomocniczego,
3. brak dokumentu nie jest zwracany jako `500 Internal Server Error`,
4. błędy wynikające ze stanu biznesowego są odróżnione od nieoczekiwanych błędów serwera,
5. API nie ujawnia surowych komunikatów wyjątków,
6. kontroler korzysta z istniejącej abstrakcji repozytorium zamiast wykonywać własne zapytanie SQL tam, gdzie repozytorium jest właściwą granicą,
7. przyczyna Problemu 3 jest zdiagnozowana i naprawiona,
8. poprawność danych kontrahenta oraz osoby tworzącej dokument jest zabezpieczona testem regresyjnym,
9. `RejectSalesDocument` i `RejectSalesDocumentHandler` są zaimplementowane zgodnie z kontraktem testu,
10. wskazane scenariusze happy-path pozostają zielone bez zmiany ich dotychczasowych asercji,
11. cały zestaw testów projektu przechodzi po zakończeniu zmian,
12. natura wszystkich problemów oraz sposób diagnozy Problemu 3 zostają opisane w końcowym `README.md`.

---

## 13. Oczekiwany rezultat końcowy

Rezultatem prac ma być repozytorium zawierające:

- poprawioną implementację,
- kompletną implementację wymaganej operacji odrzucenia,
- zielony zestaw testów,
- testy regresyjne dla naprawianych błędów,
- dokumentację techniczną zmian,
- końcowy `README.md` opisujący naturę problemów i sposób ich diagnozy.

---

## 14. Dalsza dokumentacja

Po zaakceptowaniu opisu problemu kolejnym etapem powinno być przygotowanie dokumentu:

```text
docs/02-technical-analysis.md
```

Powinien on zawierać między innymi:

- opis aktualnego przepływu wykonania,
- analizę konkretnych klas i odpowiedzialności,
- Root Cause Analysis dla Problemów 1–3,
- analizę pokrycia testowego,
- proponowany zakres zmian,
- ryzyka regresji,
- plan testów i weryfikacji.
