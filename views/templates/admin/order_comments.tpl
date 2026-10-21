<div id="pdprintorder-comments-{$pdpo_id_order|escape:'htmlall':'UTF-8'}"
     class="pdpo-comments-card"
     data-save-url="{$pdpo_save_comment_url|escape:'htmlall':'UTF-8'}"
     data-order-id="{$pdpo_id_order|escape:'htmlall':'UTF-8'}">
  <div class="pdpo-comments-head">
    <div>
      <div class="pdpo-eyebrow">DRUKOWANIE ZAMÓWIENIA</div>
      <h3>Komentarze do produktów</h3>
      <p>Krótka uwaga zapisana przy pozycji zamówienia pojawi się na wydruku w kolumnie <strong>Komentarz</strong>, obok nazwy produktu.</p>
    </div>
    <div class="pdpo-head-actions">
      <button type="button" class="btn btn-outline-primary pdpo-save-all">Zapisz wszystkie</button>
      <a class="btn btn-primary pdpo-print-btn" href="{$pdpo_print_link|escape:'htmlall':'UTF-8'}">
        <i class="material-icons">print</i> Drukuj zamówienie
      </a>
    </div>
  </div>

  {if empty($pdpo_products)}
    <div class="pdpo-empty">To zamówienie nie zawiera pozycji, do których można dodać komentarz.</div>
  {else}
    <div class="pdpo-table-wrap">
      <table class="pdpo-table">
        <thead>
          <tr>
            <th class="pdpo-col-name">Nazwa</th>
            <th>Komentarz do wydruku</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$pdpo_products item=product}
            <tr data-detail-id="{$product.id_order_detail|escape:'htmlall':'UTF-8'}">
              <td class="pdpo-product-cell">
                <strong>{$product.product_name|escape:'htmlall':'UTF-8'}</strong>
                <span>
                  ID produktu: {$product.id_product|escape:'htmlall':'UTF-8'}
                  {if $product.product_reference != ''}
                    · Indeks: {$product.product_reference|escape:'htmlall':'UTF-8'}
                  {/if}
                </span>
              </td>
              <td>
                <div class="pdpo-comment-line">
                  <input type="text"
                         class="form-control pdpo-comment-input"
                         maxlength="{$pdpo_comment_max_length|escape:'htmlall':'UTF-8'}"
                         value="{$product.comment|escape:'htmlall':'UTF-8'}"
                         data-original="{$product.comment|escape:'htmlall':'UTF-8'}"
                         aria-label="Komentarz do produktu {$product.product_name|escape:'htmlall':'UTF-8'}"
                         placeholder="Np. sprawdzić okładkę, dołożyć zabezpieczenie...">
                  <button type="button" class="btn btn-outline-primary pdpo-save-comment">Zapisz</button>
                </div>
                <div class="pdpo-comment-meta">
                  <span class="pdpo-counter">0 / {$pdpo_comment_max_length|escape:'htmlall':'UTF-8'}</span>
                  <span class="pdpo-status" aria-live="polite"></span>
                </div>
              </td>
            </tr>
          {/foreach}
        </tbody>
      </table>
    </div>
  {/if}
</div>

<style>
{literal}
.pdpo-comments-card{background:#fff;border:1px solid #dce3ea;border-radius:14px;box-shadow:0 6px 20px rgba(31,45,61,.06);margin:16px 0;overflow:hidden}.pdpo-comments-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;padding:20px 22px;border-bottom:1px solid #e9edf2;background:linear-gradient(180deg,#fff,#fafcfe)}.pdpo-comments-head h3{font-size:20px;line-height:1.25;margin:2px 0 5px;color:#25313c}.pdpo-comments-head p{margin:0;color:#657382;max-width:780px}.pdpo-eyebrow{font-size:11px;font-weight:800;letter-spacing:.12em;color:#6c868e}.pdpo-head-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end}.pdpo-print-btn{display:inline-flex;align-items:center;gap:6px;white-space:nowrap}.pdpo-print-btn .material-icons{font-size:18px}.pdpo-table-wrap{overflow-x:auto}.pdpo-table{width:100%;border-collapse:separate;border-spacing:0}.pdpo-table th{padding:12px 14px;background:#f4f7fa;color:#4b5967;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #dfe6ed}.pdpo-table td{padding:13px 14px;vertical-align:middle;border-bottom:1px solid #edf1f5}.pdpo-table tbody tr:last-child td{border-bottom:0}.pdpo-col-name{width:42%}.pdpo-product-cell strong{display:block;color:#263442;font-size:13px;line-height:1.35}.pdpo-product-cell span{display:block;margin-top:4px;font-size:11px;color:#8492a0}.pdpo-comment-line{display:flex;gap:8px;align-items:center;min-width:390px}.pdpo-comment-input{min-height:38px}.pdpo-save-comment{min-width:74px;font-weight:700}.pdpo-comment-meta{display:flex;justify-content:space-between;gap:10px;min-height:19px;padding-top:4px;font-size:11px}.pdpo-counter{color:#97a4b1}.pdpo-status{font-weight:700;color:#617280}.pdpo-status.is-ok{color:#16834d}.pdpo-status.is-error{color:#c0392b}.pdpo-status.is-saving{color:#8a6d1d}.pdpo-empty{padding:22px;color:#6d7a87}@media(max-width:900px){.pdpo-comments-head{flex-direction:column}.pdpo-head-actions{width:100%;justify-content:flex-start}.pdpo-col-name{width:36%}.pdpo-comment-line{min-width:330px}}@media(max-width:650px){.pdpo-table{min-width:720px}.pdpo-comments-head{padding:16px}.pdpo-head-actions{width:100%}.pdpo-head-actions .btn{width:100%;justify-content:center}.pdpo-print-btn{display:flex}}
{/literal}
</style>

<script>
{literal}
(function(){
  var cards=document.querySelectorAll('.pdpo-comments-card');
  cards.forEach(function(card){
    if(card.dataset.pdpoReady==='1'){return;}
    card.dataset.pdpoReady='1';
    var saveUrl=card.getAttribute('data-save-url');
    var idOrder=card.getAttribute('data-order-id');

    function updateCounter(input){
      var row=input.closest('tr');
      var counter=row ? row.querySelector('.pdpo-counter') : null;
      if(counter){counter.textContent=input.value.length+' / '+input.maxLength;}
    }

    function setStatus(row,text,type){
      var status=row.querySelector('.pdpo-status');
      if(!status){return;}
      status.textContent=text;
      status.classList.remove('is-ok','is-error','is-saving');
      if(type){status.classList.add(type);}
    }

    function saveRow(row,force){
      var input=row.querySelector('.pdpo-comment-input');
      var button=row.querySelector('.pdpo-save-comment');
      if(!input || !button || !saveUrl){return Promise.resolve(false);}
      if(!force && input.value===input.getAttribute('data-original')){return Promise.resolve(true);}

      var data=new FormData();
      data.append('id_order',idOrder);
      data.append('id_order_detail',row.getAttribute('data-detail-id'));
      data.append('comment',input.value);

      button.disabled=true;
      input.disabled=true;
      setStatus(row,'Zapisywanie…','is-saving');

      return fetch(saveUrl,{
        method:'POST',
        credentials:'same-origin',
        headers:{'X-Requested-With':'XMLHttpRequest'},
        body:data
      })
      .then(function(response){
        return response.text().then(function(body){
          var json=null;
          try{
            json=body ? JSON.parse(body) : null;
          }catch(parseError){
            var htmlBody=/<!doctype|<html/i.test(body || '');
            var loginBody=/name=[\"']passwd[\"']|id=[\"']login_form[\"']|zaloguj\s+si[eę]|connexion/i.test(body || '');

            // Na części instalacji PrestaShop 8.1 legacy kontroler zapisuje dane,
            // a następnie warstwa BO zwraca HTML panelu. Sam zapis jest wtedy
            // prawidłowy. Traktujemy odpowiedź 2xx bez strony logowania jako
            // udany zapis, aby nie blokować wydruku wymagającego zapisanych uwag.
            if(response.ok && htmlBody && !loginBody){
              return {
                success:true,
                comment:input.value,
                legacy_html_response:true
              };
            }

            if(response.redirected || htmlBody){
              throw new Error('Sesja panelu lub endpoint zapisu wymaga odświeżenia strony zamówienia.');
            }
            throw new Error('Nieprawidłowa odpowiedź serwera podczas zapisu komentarza.');
          }

          if(!json || !response.ok || !json.success){
            throw new Error((json && json.message) ? json.message : 'Nie udało się zapisać komentarza.');
          }

          return json;
        });
      })
      .then(function(json){
        if(typeof json.comment==='string'){
          input.value=json.comment;
        }
        input.setAttribute('data-original',input.value);
        updateCounter(input);
        setStatus(row,'Zapisano','is-ok');
        return true;
      })
      .catch(function(error){
        setStatus(row,error.message || 'Błąd zapisu','is-error');
        return false;
      })
      .finally(function(){
        button.disabled=false;
        input.disabled=false;
      });
    }

    function saveAllChanged(){
      var rows=Array.prototype.slice.call(card.querySelectorAll('tbody tr'));
      return Promise.all(rows.map(function(row){return saveRow(row,false);}));
    }

    card.querySelectorAll('.pdpo-comment-input').forEach(function(input){
      updateCounter(input);
      input.addEventListener('input',function(){updateCounter(input);setStatus(input.closest('tr'),'','');});
      input.addEventListener('keydown',function(event){
        if(event.key==='Enter'){
          event.preventDefault();
          saveRow(input.closest('tr'));
        }
      });
    });

    card.querySelectorAll('.pdpo-save-comment').forEach(function(button){
      button.addEventListener('click',function(){saveRow(button.closest('tr'),true);});
    });

    var saveAllButton=card.querySelector('.pdpo-save-all');
    if(saveAllButton){
      saveAllButton.addEventListener('click',function(){
        saveAllButton.disabled=true;
        saveAllChanged().finally(function(){saveAllButton.disabled=false;});
      });
    }

    function getDownloadFilename(disposition){
      var fallback='zamowienie_'+idOrder+'.pdf';
      if(!disposition){return fallback;}
      var utfMatch=disposition.match(/filename\*=UTF-8''([^;]+)/i);
      if(utfMatch && utfMatch[1]){
        try{return decodeURIComponent(utfMatch[1].replace(/["']/g,''));}catch(e){}
      }
      var simpleMatch=disposition.match(/filename=["']?([^"';]+)["']?/i);
      return simpleMatch && simpleMatch[1] ? simpleMatch[1].trim() : fallback;
    }

    function downloadPdf(url){
      return fetch(url,{
        method:'GET',
        credentials:'same-origin',
        headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/pdf,application/json;q=0.9,*/*;q=0.8'}
      }).then(function(response){
        var contentType=(response.headers.get('content-type') || '').toLowerCase();
        if(response.ok && contentType.indexOf('application/pdf')!==-1){
          var filename=getDownloadFilename(response.headers.get('content-disposition') || '');
          return response.blob().then(function(blob){
            var blobUrl=URL.createObjectURL(blob);
            var link=document.createElement('a');
            link.href=blobUrl;
            link.download=filename;
            link.style.display='none';
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(function(){URL.revokeObjectURL(blobUrl);},1500);
            return true;
          });
        }

        return response.text().then(function(body){
          var json=null;
          try{json=body ? JSON.parse(body) : null;}catch(e){}
          if(json && json.message){throw new Error(json.message);}
          if(response.redirected || /<!doctype|<html/i.test(body || '')){
            throw new Error('Serwer zwrócił stronę panelu zamiast pliku PDF. Odśwież stronę zamówienia i spróbuj ponownie.');
          }
          throw new Error('Nie udało się pobrać pliku PDF z wydrukiem.');
        });
      });
    }

    function bindPrintButton(printButton){
      if(!printButton || printButton.dataset.pdpoPrintReady==='1'){return;}
      printButton.dataset.pdpoPrintReady='1';
      printButton.addEventListener('click',function(event){
        event.preventDefault();
        if(printButton.classList.contains('disabled')){return;}

        var targetUrl=printButton.getAttribute('href');
        printButton.classList.add('disabled');
        printButton.setAttribute('aria-disabled','true');

        saveAllChanged()
          .then(function(results){
            var ok=results.every(function(result){return result===true;});
            if(!ok){
              throw new Error('Nie pobrano PDF, ponieważ nie wszystkie komentarze zostały zapisane.');
            }
            return downloadPdf(targetUrl);
          })
          .catch(function(error){
            var rows=card.querySelectorAll('tbody tr');
            if(rows.length){
              setStatus(rows[0],error.message || 'Błąd pobierania PDF','is-error');
            }else{
              window.alert(error.message || 'Błąd pobierania PDF');
            }
          })
          .finally(function(){
            printButton.classList.remove('disabled');
            printButton.removeAttribute('aria-disabled');
          });
      });
    }

    bindPrintButton(card.querySelector('.pdpo-print-btn'));
    document.querySelectorAll('.pdpo-direct-print-btn').forEach(bindPrintButton);
  });
})();
{/literal}
</script>
