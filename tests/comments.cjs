const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const template = fs.readFileSync(path.join(__dirname, '../views/templates/admin/order_comments.tpl'), 'utf8');
const script = template.split('<script>')[1].split('</script>')[0].replaceAll('{literal}', '').replaceAll('{/literal}', '');
const settle = async () => { for (let i = 0; i < 5; i++) await new Promise(setImmediate); };

function harness(response) {
  let requests = 0;
  let downloads = 0;
  const calls = [];
  const status = {textContent: '', classList: {remove() {}, add() {}}};
  const input = {
    value: 'Nowy komentarz', maxLength: 255, original: 'Stary komentarz',
    getAttribute() { return this.original; },
    setAttribute(key, value) { this.original = value; },
    closest() { return row; }, addEventListener() {},
  };
  const button = {closest() { return row; }, addEventListener(event, fn) { this.click = fn; }};
  const all = {addEventListener(event, fn) { this.click = fn; }};
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
    querySelector(selector) { return selector === '.pdpo-print-btn' ? print : selector === '.pdpo-save-all' ? all : null; },
    querySelectorAll(selector) {
      return {'.pdpo-comment-input': [input], '.pdpo-save-comment': [button], 'tbody tr': [row]}[selector] || [];
    },
  };
  vm.runInNewContext(script, {
    document: {
      querySelectorAll(selector) { return selector === '.pdpo-comments-card' ? [card] : []; },
      body: {appendChild() {}},
      createElement() { return {style: {}, click() { downloads++; }, remove() {}}; },
    },
    URL: {createObjectURL() { return 'blob:test'; }, revokeObjectURL() {}},
    window: {setTimeout(fn) { fn(); }},
    FormData, Promise,
    fetch: async (url, options) => {
      requests++;
      calls.push({url, options});
      if (typeof response === 'function') return response(url, options);
      assert.equal(url, '/save', 'PDF must not be requested after a failed save');
      return {ok: true, redirected: false, ...response, text: async () => response.body};
    },
  });
  return {input, button, print, all, status, calls, requests: () => requests, downloads: () => downloads};
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
  let finishSave;
  const concurrent = harness(url => url === '/save'
    ? new Promise(resolve => { finishSave = resolve; })
    : Promise.resolve({ok: true, headers: {get: name => name === 'content-type' ? 'application/pdf' : ''}, blob: async () => ({})}));
  concurrent.button.click();
  concurrent.all.click();
  concurrent.print.click({preventDefault() {}});
  await settle();
  assert.equal(concurrent.requests(), 1, 'Save, Save all and Print share one in-flight save');
  assert.equal(concurrent.input.disabled, true);
  finishSave({ok: true, redirected: false, text: async () => JSON.stringify({success: true, comment: 'Nowy komentarz'})});
  await settle();
  assert.equal(concurrent.requests(), 2, 'Fetch PDF only after the shared save completes');
  assert.equal(concurrent.calls[1].url, '/print');
  assert.equal(concurrent.downloads(), 1);
  assert.equal(concurrent.input.disabled, false);
  concurrent.input.value = 'Nowsza zmiana';
  await settle();
  assert.equal(concurrent.input.value, 'Nowsza zmiana', 'No stale response overwrites later input');

  let finishOld;
  const changedDuringSave = harness(() => new Promise(resolve => { finishOld = resolve; }));
  changedDuringSave.print.click({preventDefault() {}});
  changedDuringSave.input.value = 'Zmiana podczas zapisu';
  finishOld({ok: true, redirected: false, text: async () => JSON.stringify({success: true, comment: 'Nowy komentarz'})});
  await settle();
  assert.equal(changedDuringSave.input.value, 'Zmiana podczas zapisu');
  assert.equal(changedDuringSave.input.original, 'Nowy komentarz');
  assert.equal(changedDuringSave.requests(), 1, 'Do not print when newer unsaved content exists');

  let failSave;
  let attempt = 0;
  const retry = harness(() => ++attempt === 1
    ? new Promise((resolve, reject) => { failSave = reject; })
    : Promise.resolve({ok: true, redirected: false, text: async () => JSON.stringify({success: true, comment: 'Nowy komentarz'})}));
  retry.button.click();
  retry.all.click();
  failSave(new Error('Network failure'));
  await settle();
  assert.equal(retry.requests(), 1);
  assert.equal(retry.input.original, 'Stary komentarz');
  retry.button.click();
  await settle();
  assert.equal(retry.requests(), 2, 'Failed in-flight save can be retried');
  assert.equal(retry.status.textContent, 'Zapisano');
  console.log('PASS: 14 JavaScript regression scenarios (including concurrent saves, stale responses and retry)');
})().catch(error => { console.error(error); process.exitCode = 1; });
