const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const template = fs.readFileSync(path.join(__dirname, '../views/templates/admin/order_comments.tpl'), 'utf8');
const script = template.split('<script>')[1].split('</script>')[0].replaceAll('{literal}', '').replaceAll('{/literal}', '');
const settle = async () => { for (let i = 0; i < 5; i++) await new Promise(setImmediate); };

function harness(response) {
  let requests = 0;
  const status = {textContent: '', classList: {remove() {}, add() {}}};
  const input = {
    value: 'Nowy komentarz', maxLength: 255, original: 'Stary komentarz',
    getAttribute() { return this.original; },
    setAttribute(key, value) { this.original = value; },
    closest() { return row; }, addEventListener() {},
  };
  const button = {closest() { return row; }, addEventListener(event, fn) { this.click = fn; }};
  const print = {
    dataset: {}, classList: {contains() { return false; }, add() {}, remove() {}},
    getAttribute() { return '/print'; }, setAttribute() {}, removeAttribute() {},
    addEventListener(event, fn) { this.click = fn; },
  };
  const row = {
    getAttribute() { return '11'; },
    querySelector(selector) {
      return {'.pdpo-comment-input': input, '.pdpo-save-comment': button, '.pdpo-status': status}[selector] || null;
    },
  };
  const card = {
    dataset: {}, getAttribute(key) { return key === 'data-save-url' ? '/save' : '1'; },
    querySelector(selector) { return selector === '.pdpo-print-btn' ? print : null; },
    querySelectorAll(selector) {
      return {'.pdpo-comment-input': [input], '.pdpo-save-comment': [button], 'tbody tr': [row]}[selector] || [];
    },
  };
  vm.runInNewContext(script, {
    document: {querySelectorAll(selector) { return selector === '.pdpo-comments-card' ? [card] : []; }},
    FormData, Promise,
    fetch: async (url) => {
      requests++;
      assert.equal(url, '/save', 'PDF must not be requested after a failed save');
      return {ok: true, redirected: false, ...response, text: async () => response.body};
    },
  });
  return {input, button, print, status, requests: () => requests};
}

(async () => {
  const rejected = [
    {body: '<html><body>Access denied</body></html>'},
    {body: '<!doctype html><html>Login</html>', redirected: true},
    {body: ''},
    {body: 'not JSON'},
    {body: JSON.stringify({success: false, message: 'Brak uprawnień'})},
    {body: JSON.stringify({success: 1, comment: 'Nowy komentarz'})},
    {body: JSON.stringify({success: true})},
    {body: JSON.stringify({success: true, comment: 'Nowy komentarz'}), redirected: true},
    {body: JSON.stringify({success: true, comment: 'Nowy komentarz'}), ok: false},
  ];
  for (const response of rejected) {
    const h = harness(response);
    h.button.click();
    await settle();
    assert.notEqual(h.status.textContent, 'Zapisano');
    assert.equal(h.input.original, 'Stary komentarz', 'Keep the dirty flag after a failed save');
    assert.equal(h.input.value, 'Nowy komentarz', 'Keep unsaved text for retry');
    assert.equal(h.input.disabled, false);
  }
  const good = harness({body: JSON.stringify({success: true, comment: 'Znormalizowany komentarz'})});
  good.button.click();
  await settle();
  assert.equal(good.status.textContent, 'Zapisano');
  assert.equal(good.input.value, 'Znormalizowany komentarz');
  assert.equal(good.input.original, good.input.value);

  const failedPrint = harness(rejected[0]);
  failedPrint.print.click({preventDefault() {}});
  await settle();
  assert.equal(failedPrint.requests(), 1, 'Only attempt the save, never the PDF');
  assert.match(failedPrint.status.textContent, /nie wszystkie komentarze/);
  assert.equal(failedPrint.input.original, 'Stary komentarz');
  console.log('PASS: 11 JavaScript regression scenarios (invalid responses, successful save, blocked PDF)');
})().catch(error => { console.error(error); process.exitCode = 1; });
