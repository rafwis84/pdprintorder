# pdprintorder 1.2.6

Drukowanie zamówień, faktur i dokumentów WZ oraz komentarze magazynowe
przy pozycjach zamówień. Moduł dla PrestaShop 8.1.x i PHP 8.2.

## Instalacja / aktualizacja

Wgraj `pdprintorder-1.2.6.zip` zawierający katalog `pdprintorder/` przez menedżer modułów PrestaShop.
Przy aktualizacji wykonaj aktualizację istniejącego modułu do wersji 1.2.6;
nie trzeba go odinstalowywać. Aktualizacja nie usuwa komentarzy i nie zmienia
schematu bazy danych. W razie potrzeby wyczyść pamięć podręczną PrestaShop.
Paczka zawiera autoloader; nie wymaga uruchamiania Composer na serwerze.

## Poprawki w 1.2.6

- Podsumowanie kwot uwzględnia zwroty produktów zapisane przy pozycjach
  zamówienia bez dokumentu korekty. Kwoty są porównywane osobno dla każdej
  pozycji, aby uwzględnić mieszane zwroty i nie odejmować ich podwójnie.
  Koszty zwróconej wysyłki są odczytywane z zapisanych dokumentów korekt.
- Generowanie PDF bezpiecznie obsługuje zagnieżdżone i nieusuwalne bufory
  wyjścia. Nieusuwalny bufor jest czyszczony, jeśli pozwala na to serwer.
  Gdy nie da się usunąć wcześniejszej treści, generowanie zostaje przerwane
  komunikatem błędu zamiast zawieszenia lub pliku PDF z domieszką HTML.

## Konwencja paczek

Paczka ma nazwę `nazwa-modułu-wersja.zip`, a w środku jeden katalog
`nazwa-modułu/` ze wszystkimi plikami instalacyjnymi.

## Poprawki w 1.2.5

- Ilość „do wydania” uwzględnia zwroty zapisane w pozycjach zamówienia także
  bez dokumentu korekty. Zwrot i odpowiadająca mu korekta nie są sumowane
  podwójnie; ilości są ograniczone do liczby zamówionych sztuk.
- Przyciski „Zapisz”, „Zapisz wszystkie” i „Drukuj” oczekują na ten sam trwający
  zapis danej pozycji. Starsza odpowiedź nie nadpisuje nowszej treści komentarza,
  a wydruk nie rusza, gdy pozostaje niezapisana zmiana.
- Linki zapisu i pojedynczego wydruku jawnie zawierają token modułu również
  przy wyłączonym `PS_SECURITY_TOKEN`. Kontrola tokenu i uprawnień pozostaje aktywna.

## Poprawki w 1.2.4

- Odpowiedzi JSON zgodne z PrestaShop 8.1 i PHP 8.2, bez `Tools::jsonEncode()`.
- Odczyt i wydruki wymagają prawa przeglądania zamówień, a zapis komentarzy
  dodatkowo prawa edycji zamówień w profilu pracownika.
- Każde zamówienie jest sprawdzane pod kątem dostępu pracownika do jego sklepu.
  Cała paczka zamówień jest sprawdzana przed rozpoczęciem generowania PDF.
- Odpowiedź HTML, przekierowanie lub nieprawidłowy JSON nie potwierdzają zapisu
  komentarza. Niezapisana treść pozostaje w polu do ponownej próby.

## Testy regresji (repozytorium)

`php tests/security.php`, `php tests/refunds.php`, `php tests/output.php`
i `node tests/comments.cjs` sprawdzają zabezpieczenia, kwoty zwrotów, bufory
oraz obsługę odpowiedzi przez rzeczywisty kod modułu z kontrolowanymi atrapami
usług PrestaShop i przeglądarki.

`php tests/refunds.php --database` wykonuje zapytania SQL na bazie wskazanej
przez `PDPO_TEST_DSN` (opcjonalnie `PDPO_TEST_DB_USER`, `PDPO_TEST_DB_PASSWORD`).
Test używa wyłącznie tabel tymczasowych `pdpo_test_*` w swoim połączeniu.
Nie zastępuje to testów integracyjnych w działającym sklepie.
