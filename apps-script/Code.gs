/**
 * Stock Camion : serveur Google Apps Script.
 * Les données vivent dans le Google Sheet auquel ce script est rattaché :
 *   - « Articles »   : un article par ligne (outils et consommables)
 *   - « Historique » : un mouvement par ligne (qui, quoi, quand)
 *   - « Réglages »   : nom du véhicule et code d'équipe (facultatif)
 * Les onglets sont créés automatiquement à la première ouverture de l'app.
 */

var SHEET_ITEMS = 'Articles';
var SHEET_LOG = 'Historique';
var SHEET_CONF = 'Réglages';
var ITEM_COLS = ['id', 'name', 'kind', 'cat', 'loc', 'qty', 'unit', 'min', 'status', 'holder', 'note', 'createdAt', 'updatedAt', 'updatedBy'];
var LOG_COLS = ['at', 'by', 'item', 'text'];
var SHEET_SITES = 'Chantiers';
var SHEET_LINES = 'À prendre';
var SITE_COLS = ['id', 'name', 'info', 'note', 'done', 'createdAt', 'updatedAt', 'updatedBy'];
var LINE_COLS = ['id', 'siteId', 'label', 'qty', 'unit', 'type', 'itemId', 'ready', 'readyBy', 'readyAt', 'createdAt', 'by'];
var LINE_TYPES = ['outils', 'visserie', 'materiaux', 'materiel', 'autre'];
var LOG_SHOWN = 200;   // mouvements renvoyés à l'app
var LOG_KEEP = 2000;   // mouvements conservés dans le Sheet
// Code d'équipe par défaut (laisser vide = pas de code). Une valeur dans l'onglet « Réglages » du Sheet est prioritaire.
var TEAM_CODE = '';
var STATUSES = { camion: 'dans le camion', chantier: 'sur chantier', atelier: 'en réparation', manquant: 'manquant' };

function doGet() {
  return HtmlService.createHtmlOutputFromFile('Index')
    .setTitle('Le bubus à gaga')
    .addMetaTag('viewport', 'width=device-width, initial-scale=1, viewport-fit=cover');
}

/** Point d'entrée unique appelé par l'app (google.script.run.api). */
function api(req) {
  req = req || {};
  checkCode_(req.code);
  if (req.action === 'load') return state_();
  var lock = LockService.getScriptLock();
  lock.waitLock(20000);
  try {
    var by = clean_(req.by, 30) || "Quelqu'un";
    switch (req.action) {
      case 'save':   saveItem_(req.item || {}, by); break;
      case 'delete': deleteItem_(String(req.id || ''), by); break;
      case 'adjust': adjustQty_(String(req.id || ''), parseInt(req.delta, 10) || 0, by); break;
      case 'status': setStatus_(String(req.id || ''), req.status, req.holder, by); break;
      case 'config': setConf_('vehicle', clean_(req.vehicle, 40)); break;
      case 'bulk':   addBulk_(req.items || [], by); break;
      case 'siteSave':   siteSave_(req.site || {}, by); break;
      case 'siteDelete': siteDelete_(String(req.id || ''), by); break;
      case 'siteReset':  siteReset_(String(req.id || ''), by); break;
      case 'lineAdd':    lineAdd_(String(req.siteId || ''), req.line || {}, by); break;
      case 'lineSave':   lineSave_(req.line || {}); break;
      case 'lineToggle': lineToggle_(String(req.id || ''), !!req.ready, by); break;
      case 'lineDelete': lineDelete_(String(req.id || '')); break;
      default: throw new Error('ACTION_INCONNUE');
    }
    SpreadsheetApp.flush();
  } finally {
    lock.releaseLock();
  }
  return state_();
}

/* ---------- Feuilles ---------- */

var SS_CACHE_ = null;
/**
 * Le Sheet des données : celui auquel le script est rattaché (Extensions → Apps Script),
 * sinon un Sheet « Stock Camion – données » créé automatiquement dans ton Drive
 * (projet créé directement sur script.google.com, pratique sur iPad).
 */
function ss_() {
  if (SS_CACHE_) return SS_CACHE_;
  var active = SpreadsheetApp.getActiveSpreadsheet();
  if (active) return (SS_CACHE_ = active);
  var props = PropertiesService.getScriptProperties();
  var id = props.getProperty('SHEET_ID');
  if (id) { try { return (SS_CACHE_ = SpreadsheetApp.openById(id)); } catch (e) {} }
  var lock = LockService.getScriptLock();
  lock.waitLock(20000);
  try {
    id = props.getProperty('SHEET_ID');
    if (id) { try { return (SS_CACHE_ = SpreadsheetApp.openById(id)); } catch (e) {} }
    var ss = SpreadsheetApp.create('Stock Camion – données');
    props.setProperty('SHEET_ID', ss.getId());
    return (SS_CACHE_ = ss);
  } finally {
    lock.releaseLock();
  }
}

/** À lancer une fois depuis l'éditeur (bouton Exécuter) : crée les onglets et affiche le lien du Sheet. */
function installer() {
  sheet_(SHEET_ITEMS, ITEM_COLS); sheet_(SHEET_LOG, LOG_COLS); sheet_(SHEET_CONF, ['clé', 'valeur']);
  Logger.log('Données : ' + ss_().getUrl());
}

function sheet_(name, cols) {
  var sh = ss_().getSheetByName(name);
  if (!sh) {
    sh = ss_().insertSheet(name);
    sh.getRange(1, 1, 1, cols.length).setValues([cols]).setFontWeight('bold');
    sh.setFrozenRows(1);
    if (name === SHEET_CONF) {
      sh.getRange(2, 1, 2, 2).setValues([['vehicle', ''], ['code', '']]);
    }
  }
  return sh;
}

function headIndex_(sh) {
  var head = sh.getRange(1, 1, 1, sh.getLastColumn()).getValues()[0];
  var idx = {};
  head.forEach(function (h, i) { if (h) idx[String(h).trim()] = i; });
  return { head: head, idx: idx };
}

function conf_() {
  var v = sheet_(SHEET_CONF, ['clé', 'valeur']).getDataRange().getValues();
  var o = {};
  for (var i = 1; i < v.length; i++) if (v[i][0]) o[String(v[i][0]).trim()] = v[i][1];
  return o;
}

function setConf_(key, val) {
  var sh = sheet_(SHEET_CONF, ['clé', 'valeur']);
  var v = sh.getDataRange().getValues();
  for (var i = 1; i < v.length; i++) {
    if (String(v[i][0]).trim() === key) { sh.getRange(i + 1, 2).setValue(val); return; }
  }
  sh.appendRow([key, val]);
}

function checkCode_(code) {
  var expected = String(conf_().code || '').trim() || String(TEAM_CODE || '').trim();
  if (expected && String(code || '').trim() !== expected) throw new Error('CODE_EQUIPE');
}

/* ---------- Lecture ---------- */

function num_(v) { return v instanceof Date ? v.getTime() : (Number(v) || 0); }
function clean_(v, max) { return String(v == null ? '' : v).replace(/[\u0000-\u001f]/g, ' ').trim().slice(0, max || 200); }

function rowToItem_(r, idx) {
  var g = function (k) { return idx[k] == null ? '' : r[idx[k]]; };
  return {
    id: String(g('id')), name: String(g('name')), kind: g('kind') === 'conso' ? 'conso' : 'outil',
    cat: String(g('cat')), loc: String(g('loc')), qty: Number(g('qty')) || 0, unit: String(g('unit')),
    min: Number(g('min')) || 0, status: String(g('status')), holder: String(g('holder')), note: String(g('note')),
    createdAt: num_(g('createdAt')), updatedAt: num_(g('updatedAt')), updatedBy: String(g('updatedBy'))
  };
}

function readItems_() {
  var sh = sheet_(SHEET_ITEMS, ITEM_COLS);
  var h = headIndex_(sh);
  var v = sh.getDataRange().getValues();
  var out = [];
  for (var i = 1; i < v.length; i++) if (v[i][h.idx.id]) out.push(rowToItem_(v[i], h.idx));
  return out;
}

function state_() {
  var lsh = sheet_(SHEET_LOG, LOG_COLS);
  var last = lsh.getLastRow();
  var log = [];
  if (last > 1) {
    var start = Math.max(2, last - LOG_SHOWN + 1);
    lsh.getRange(start, 1, last - start + 1, 4).getValues().forEach(function (r) {
      if (r[0]) log.push({ at: num_(r[0]), by: String(r[1]), item: String(r[2]), text: String(r[3]) });
    });
    log.reverse();
  }
  var c = conf_();
  return { items: readItems_(), sites: readSites_(), lines: readLines_(), log: log, config: { vehicle: String(c.vehicle || '') }, serverTime: Date.now() };
}

/* ---------- Écritures ---------- */

function findRow_(sh, idx, id) {
  if (!id) return -1;
  var last = sh.getLastRow();
  if (last < 2) return -1;
  var ids = sh.getRange(2, idx.id + 1, last - 1, 1).getValues();
  for (var i = 0; i < ids.length; i++) if (String(ids[i][0]) === id) return i + 2;
  return -1;
}

function writeRow_(sh, h, row, item) {
  var arr = h.head.map(function (col) {
    var k = String(col).trim();
    if (/At$/.test(k)) return item[k] ? new Date(item[k]) : '';
    return item.hasOwnProperty(k) ? item[k] : '';
  });
  sh.getRange(row, 1, 1, arr.length).setValues([arr]);
}

function sanitize_(it) {
  var kind = it.kind === 'conso' ? 'conso' : 'outil';
  var status = kind === 'outil' ? (STATUSES[it.status] ? it.status : 'camion') : '';
  var qty = Math.max(0, parseInt(it.qty, 10) || 0);
  return {
    name: clean_(it.name, 80), kind: kind, cat: clean_(it.cat, 40) || 'Divers', loc: clean_(it.loc, 40),
    qty: kind === 'outil' ? Math.max(1, qty || 1) : qty, unit: kind === 'conso' ? clean_(it.unit, 20) : '',
    min: kind === 'conso' ? Math.max(0, parseInt(it.min, 10) || 0) : 0, status: status,
    holder: status && status !== 'camion' ? clean_(it.holder, 80) : '', note: clean_(it.note, 300)
  };
}

function addLog_(by, item, text) {
  var sh = sheet_(SHEET_LOG, LOG_COLS);
  sh.appendRow([new Date(), by, clean_(item, 120), clean_(text, 200)]);
  var extra = sh.getLastRow() - 1 - LOG_KEEP;
  if (extra > 50) sh.deleteRows(2, extra);
}

function unitOf_(it) { return it.unit || 'u'; }

function saveItem_(raw, by) {
  var data = sanitize_(raw);
  if (!data.name) throw new Error('NOM_MANQUANT');
  var sh = sheet_(SHEET_ITEMS, ITEM_COLS);
  var h = headIndex_(sh);
  var now = Date.now();
  var row = findRow_(sh, h.idx, String(raw.id || ''));
  if (row > 0) {
    var old = rowToItem_(sh.getRange(row, 1, 1, h.head.length).getValues()[0], h.idx);
    var item = Object.assign({}, old, data, { updatedAt: now, updatedBy: by });
    writeRow_(sh, h, row, item);
    var changes = [];
    if (data.kind === 'outil' && old.status !== data.status) changes.push('→ ' + STATUSES[data.status] + (data.holder ? ' (' + data.holder + ')' : ''));
    if (data.kind === 'conso' && old.qty !== data.qty) changes.push(': stock corrigé à ' + data.qty + ' ' + unitOf_(data));
    addLog_(by, data.name, changes.length ? changes.join(', ') : 'fiche modifiée');
  } else {
    var fresh = Object.assign({ id: Utilities.getUuid().slice(0, 13) }, data, { createdAt: now, updatedAt: now, updatedBy: by });
    writeRow_(sh, h, sh.getLastRow() + 1, fresh);
    addLog_(by, data.name, 'ajouté au stock' + (data.kind === 'conso' ? ' (' + data.qty + ' ' + unitOf_(data) + ')' : ''));
  }
}

function addBulk_(list, by) {
  var sh = sheet_(SHEET_ITEMS, ITEM_COLS);
  var h = headIndex_(sh);
  var now = Date.now();
  var rows = [];
  list.slice(0, 150).forEach(function (raw) {
    var d = sanitize_(raw);
    if (!d.name) return;
    var it = Object.assign({ id: Utilities.getUuid().slice(0, 13) }, d, { createdAt: now, updatedAt: now, updatedBy: by });
    rows.push(h.head.map(function (col) {
      var k = String(col).trim();
      if (k === 'createdAt' || k === 'updatedAt') return new Date(now);
      return it.hasOwnProperty(k) ? it[k] : '';
    }));
  });
  if (!rows.length) return;
  sh.getRange(sh.getLastRow() + 1, 1, rows.length, h.head.length).setValues(rows);
  addLog_(by, 'Liste type couvreur', 'chargée (' + rows.length + ' articles)');
}

function getItemRow_(id) {
  var sh = sheet_(SHEET_ITEMS, ITEM_COLS);
  var h = headIndex_(sh);
  var row = findRow_(sh, h.idx, id);
  if (row < 0) throw new Error('ARTICLE_INTROUVABLE');
  return { sh: sh, h: h, row: row, item: rowToItem_(sh.getRange(row, 1, 1, h.head.length).getValues()[0], h.idx) };
}

function deleteItem_(id, by) {
  var r = getItemRow_(id);
  r.sh.deleteRow(r.row);
  addLog_(by, r.item.name, 'supprimé du stock');
}

function adjustQty_(id, delta, by) {
  if (!delta) return;
  var r = getItemRow_(id);
  var from = r.item.qty;
  var qty = Math.max(0, from + delta);
  if (qty === from) return;
  r.item.qty = qty; r.item.updatedAt = Date.now(); r.item.updatedBy = by;
  writeRow_(r.sh, r.h, r.row, r.item);
  var d = qty - from, u = unitOf_(r.item);
  addLog_(by, r.item.name, d < 0
    ? ': ' + (-d) + ' ' + u + ' utilisé' + (-d > 1 ? 's' : '') + ' (reste ' + qty + ')'
    : ': +' + d + ' ' + u + ' ajouté' + (d > 1 ? 's' : '') + ' (total ' + qty + ')');
}

function setStatus_(id, status, holder, by) {
  if (!STATUSES[status]) throw new Error('STATUT_INCONNU');
  var r = getItemRow_(id);
  holder = status === 'camion' ? '' : clean_(holder, 80);
  if (r.item.status === status && r.item.holder === holder) return;
  r.item.status = status; r.item.holder = holder; r.item.updatedAt = Date.now(); r.item.updatedBy = by;
  writeRow_(r.sh, r.h, r.row, r.item);
  addLog_(by, r.item.name, status === 'camion' ? 'rangé dans le camion' : '→ ' + STATUSES[status] + (holder ? ' (' + holder + ')' : ''));
}


/* ---------- Chantiers et listes « à prendre » ---------- */

function bool_(v) { return v === true || String(v).toUpperCase() === 'TRUE' || v === 1 || v === '1'; }

function readTable_(name, cols, map) {
  var sh = sheet_(name, cols);
  var h = headIndex_(sh);
  var v = sh.getDataRange().getValues();
  var out = [];
  for (var i = 1; i < v.length; i++) {
    if (!v[i][h.idx.id]) continue;
    var r = {};
    cols.forEach(function (c) { r[c] = h.idx[c] == null ? '' : v[i][h.idx[c]]; });
    out.push(map(r));
  }
  return out;
}

function readSites_() {
  return readTable_(SHEET_SITES, SITE_COLS, function (r) {
    return { id: String(r.id), name: String(r.name), info: String(r.info), note: String(r.note), done: bool_(r.done),
      createdAt: num_(r.createdAt), updatedAt: num_(r.updatedAt), updatedBy: String(r.updatedBy) };
  });
}

function readLines_() {
  return readTable_(SHEET_LINES, LINE_COLS, function (r) {
    return { id: String(r.id), siteId: String(r.siteId), label: String(r.label), qty: String(r.qty), unit: String(r.unit),
      type: LINE_TYPES.indexOf(String(r.type)) >= 0 ? String(r.type) : 'autre', itemId: String(r.itemId),
      ready: bool_(r.ready), readyBy: String(r.readyBy), readyAt: num_(r.readyAt), createdAt: num_(r.createdAt), by: String(r.by) };
  });
}

function tableRow_(name, cols, id) {
  var sh = sheet_(name, cols);
  var h = headIndex_(sh);
  var row = findRow_(sh, h.idx, id);
  if (row < 0) throw new Error('ELEMENT_INTROUVABLE');
  var vals = sh.getRange(row, 1, 1, h.head.length).getValues()[0];
  var obj = {};
  h.head.forEach(function (c, i) { obj[String(c).trim()] = vals[i] instanceof Date ? vals[i].getTime() : vals[i]; });
  return { sh: sh, h: h, row: row, obj: obj };
}

function siteSave_(raw, by) {
  var name = clean_(raw.name, 80);
  if (!name) throw new Error('NOM_MANQUANT');
  var data = { name: name, info: clean_(raw.info, 120), note: clean_(raw.note, 300), done: !!raw.done, updatedAt: Date.now(), updatedBy: by };
  var sh = sheet_(SHEET_SITES, SITE_COLS);
  var h = headIndex_(sh);
  var row = findRow_(sh, h.idx, String(raw.id || ''));
  if (row > 0) {
    var r = tableRow_(SHEET_SITES, SITE_COLS, String(raw.id));
    var wasDone = bool_(r.obj.done);
    writeRow_(sh, h, row, Object.assign(r.obj, data));
    if (wasDone !== data.done) addLog_(by, 'Chantier ' + name, data.done ? 'marqué terminé' : 'réouvert');
  } else {
    writeRow_(sh, h, sh.getLastRow() + 1, Object.assign({ id: Utilities.getUuid().slice(0, 13), createdAt: Date.now() }, data));
    addLog_(by, 'Chantier ' + name, 'créé');
  }
}

function siteDelete_(id, by) {
  var r = tableRow_(SHEET_SITES, SITE_COLS, id);
  r.sh.deleteRow(r.row);
  var lsh = sheet_(SHEET_LINES, LINE_COLS);
  var lh = headIndex_(lsh);
  var last = lsh.getLastRow();
  if (last > 1) {
    var sids = lsh.getRange(2, lh.idx.siteId + 1, last - 1, 1).getValues();
    for (var i = sids.length - 1; i >= 0; i--) if (String(sids[i][0]) === id) lsh.deleteRow(i + 2);
  }
  addLog_(by, 'Chantier ' + r.obj.name, 'supprimé');
}

function siteReset_(id, by) {
  var site = tableRow_(SHEET_SITES, SITE_COLS, id);
  var lsh = sheet_(SHEET_LINES, LINE_COLS);
  var lh = headIndex_(lsh);
  var last = lsh.getLastRow();
  if (last < 2) return;
  var rng = lsh.getRange(2, 1, last - 1, lh.head.length);
  var v = rng.getValues();
  var changed = 0;
  v.forEach(function (r) {
    if (String(r[lh.idx.siteId]) === id && bool_(r[lh.idx.ready])) {
      r[lh.idx.ready] = false; r[lh.idx.readyBy] = ''; r[lh.idx.readyAt] = ''; changed++;
    }
  });
  if (changed) { rng.setValues(v); addLog_(by, 'Chantier ' + site.obj.name, 'liste remise à préparer (' + changed + ' lignes)'); }
}

function lineData_(raw) {
  return { label: clean_(raw.label, 100), qty: clean_(raw.qty, 12), unit: clean_(raw.unit, 20),
    type: LINE_TYPES.indexOf(raw.type) >= 0 ? raw.type : 'autre', itemId: clean_(raw.itemId, 40) };
}

function lineAdd_(siteId, raw, by) {
  tableRow_(SHEET_SITES, SITE_COLS, siteId);
  var d = lineData_(raw);
  if (!d.label) throw new Error('NOM_MANQUANT');
  var sh = sheet_(SHEET_LINES, LINE_COLS);
  var h = headIndex_(sh);
  writeRow_(sh, h, sh.getLastRow() + 1, Object.assign({ id: Utilities.getUuid().slice(0, 13), siteId: siteId, ready: false, readyBy: '', readyAt: 0, createdAt: Date.now(), by: by }, d));
}

function lineSave_(raw) {
  var r = tableRow_(SHEET_LINES, LINE_COLS, String(raw.id || ''));
  var d = lineData_(raw);
  if (!d.label) throw new Error('NOM_MANQUANT');
  writeRow_(r.sh, r.h, r.row, Object.assign(r.obj, d));
}

function lineToggle_(id, ready, by) {
  var r = tableRow_(SHEET_LINES, LINE_COLS, id);
  Object.assign(r.obj, { ready: ready, readyBy: ready ? by : '', readyAt: ready ? Date.now() : 0 });
  writeRow_(r.sh, r.h, r.row, r.obj);
}

function lineDelete_(id) {
  var r = tableRow_(SHEET_LINES, LINE_COLS, id);
  r.sh.deleteRow(r.row);
}
