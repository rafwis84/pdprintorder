# Historia zmian

## 1.2.6 — 2026-10-22

- Podsumowanie wydruku magazynowego uwzględnia kwoty zwrotów produktów bez
  dokumentu korekty, zapisane w `order_detail.total_refunded_tax_incl`.
  Zachowano kwoty korekt i wysyłki; tylko brakująca część zwrotu danej pozycji
  jest dodawana do podsumowania, bez podwójnego odejmowania.
- Błąd odczytu danych zwrotów nie jest traktowany jako zerowy zwrot.
- Wspólna obsługa buforów dla wydruków magazynowych, faktur i WZ usuwa ryzyko
  nieskończonej pętli przy buforze, którego nie można zamknąć. Bezpieczne
  czyszczenie zachowuje bufory zarządzane przez serwer. Brak możliwości
  usunięcia wcześniejszej treści powoduje kontrolowany błąd, a nie uszkodzony PDF.
- Dodano testy kwot (także mieszanych zwrotów i korekt), rzeczywistych zapytań
  SQL oraz buforów usuwalnych, nieusuwalnych i zagnieżdżonych dla obu ścieżek PDF.
- Paczka instalacyjna ma nazwę `pdprintorder-1.2.6.zip` i katalog główny
  `pdprintorder/`, zgodnie z konwencją użytkownika dla wszystkich projektów.

Aktualizacja nie zmienia schematu bazy ani nie usuwa komentarzy. Kwoty wysyłki
są uwzględniane na podstawie zapisanych korekt; nie są szacowane na podstawie
liczby zwróconych sztuk. Pełny test w działającym sklepie pozostaje odrębny.

## 1.2.5 — 2026-10-22

- Poprawiono liczbę sztuk do wydania po zwrotach bez dokumentu korekty.
  Uwzględniane są `product_quantity_refunded` i `product_quantity_return`.
  Dane dokumentów korekt pozostają obsługiwane bez podwójnego odejmowania.
- Wyeliminowano równoległe zapisy tej samej pozycji uruchamiane przez zapis,
  zapis wszystkich komentarzy i drukowanie. Obsługa wydruku czeka na wynik zapisu.
  Starsza odpowiedź nie usuwa nowszej zmiany; po błędzie można ponowić zapis.
- Usunięto konflikt generowania linków z wyłączonymi tokenami URL w panelu
  PrestaShop. Token modułu jest przekazywany jawnie i nadal sprawdzany.
- Dodano testy regresji ilości po zwrotach, tokenów URL, równoczesnego zapisu,
  ochrony nowszej treści i ponawiania po błędzie sieci.

Aktualizacja nie zmienia schematu bazy ani nie usuwa komentarzy.
Weryfikacja obejmuje testy regresji i składnię PHP 8.2; nie zastępuje testów
integracyjnych w działającym sklepie z jego pozostałymi modułami.

## 1.2.4

- Zastąpiono niedostępne `Tools::jsonEncode()` standardowym kodowaniem JSON.
- Dodano sprawdzanie uprawnień odczytu i edycji zamówień oraz dostępu do sklepu.
- Wszystkie zamówienia w wydruku zbiorczym są sprawdzane przed generowaniem PDF.
- Odpowiedź HTML i nieprawidłowy JSON nie są uznawane za potwierdzenie zapisu.
