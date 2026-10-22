<div class="panel">
  <h3><i class="icon-print"></i> Centrum drukowania zamówień</h3>
  <p>Wersja <strong>{$pdpo_version|escape:'htmlall':'UTF-8'}</strong> · zapisane komentarze: <strong>{$pdpo_comments_count|escape:'htmlall':'UTF-8'}</strong> · schemat danych: <strong>{$pdpo_schema_version|escape:'htmlall':'UTF-8'}</strong></p>
  <div class="alert alert-info">
    Komentarze są przypisane do konkretnych pozycji zamówienia. Aktualizacja modułu nie usuwa komentarzy ani danych PrestaShop.
  </div>

  <form method="post" action="{$action_url|escape:'htmlall':'UTF-8'}" target="_blank">
    <div class="form-group">
      <label><strong>Typ dokumentu</strong></label>
      <select name="pdf_type" class="form-control" style="max-width:460px">
        <option value="packing">Wydruk magazynowy</option>
        <option value="invoice">Faktury</option>
        <option value="delivery">Dokumenty WZ</option>
      </select>
    </div>

    <div class="form-group">
      <label><strong>ID zamówień</strong> <span class="text-muted">— oddziel przecinkami, spacjami lub średnikami</span></label>
      <textarea class="form-control" name="order_ids" rows="4" placeholder="np. 123, 124, 130"></textarea>
      <p class="help-block">Możesz wydrukować jedno lub wiele zamówień w jednym pliku PDF.</p>
    </div>

    <button type="submit" name="submit_pdprintorder" class="btn btn-primary">
      <i class="icon-print"></i> Generuj PDF
    </button>
  </form>
</div>

<div class="panel">
  <h3><i class="icon-check"></i> Funkcje wersji {$pdpo_version|escape:'htmlall':'UTF-8'}</h3>
  <ul style="line-height:1.9;margin-bottom:0">
    <li>krótki komentarz przy każdej pozycji zamówienia,</li>
    <li>kolumna „Komentarz” obok „Nazwa” na wydruku magazynowym,</li>
    <li>drukowanie zbiorcze z listy zamówień,</li>
    <li>wydruki magazynowe, faktury i dokumenty WZ,</li>
    <li>zgodność z PrestaShop 8.1.x i PHP 8.2,</li>
    <li>brak destrukcyjnych operacji na danych podczas aktualizacji.</li>
  </ul>
</div>
