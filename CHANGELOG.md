# Historia zmian

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
