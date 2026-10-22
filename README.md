# pdprintorder 1.2.4

Drukowanie zamówień, faktur i dokumentów WZ oraz komentarze magazynowe
przy pozycjach zamówień. Moduł dla PrestaShop 8.1.x i PHP 8.2.

## Instalacja / aktualizacja

Wgraj ZIP zawierający katalog `pdprintorder/` przez menedżer modułów PrestaShop.
Przy aktualizacji wykonaj aktualizację istniejącego modułu do wersji 1.2.4;
nie trzeba go odinstalowywać. Aktualizacja nie usuwa komentarzy i nie zmienia
schematu bazy danych. W razie potrzeby wyczyść pamięć podręczną PrestaShop.
Paczka zawiera autoloader; nie wymaga uruchamiania Composer na serwerze.

## Poprawki w 1.2.4

- Odpowiedzi JSON zgodne z PrestaShop 8.1 i PHP 8.2, bez `Tools::jsonEncode()`.
- Odczyt i wydruki wymagają prawa przeglądania zamówień, a zapis komentarzy
  dodatkowo prawa edycji zamówień w profilu pracownika.
- Każde zamówienie jest sprawdzane pod kątem dostępu pracownika do jego sklepu.
  Cała paczka zamówień jest sprawdzana przed rozpoczęciem generowania PDF.
- Odpowiedź HTML, przekierowanie lub nieprawidłowy JSON nie potwierdzają zapisu
  komentarza. Niezapisana treść pozostaje w polu do ponownej próby.

## Testy regresji (repozytorium)

`php tests/security.php` i `node tests/comments.cjs` sprawdzają zabezpieczenia
oraz obsługę odpowiedzi przez rzeczywisty kod modułu z kontrolowanymi atrapami
usług PrestaShop i przeglądarki. Nie zastępują testów integracyjnych w sklepie.
