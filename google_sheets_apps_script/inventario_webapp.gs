const INVENTARIO_SHEET_NAME = 'inventario';
const HISTORICO_SHEET_NAME = 'historico';
const CENTROS_NUEVO_ORIGEN_SHEET_NAME = 'centros';
const SECRET_TOKEN = 'congregaciones_sync_2026';

function doGet(e) {
  const action = normalizeCell_(e && e.parameter ? e.parameter.action : '');
  if (action === 'delete') {
    const id = normalizeCell_(e && e.parameter ? e.parameter.id : '');
    deleteInventoryRowById_(id);
    return ContentService.createTextOutput('OK');
  }

  return handleRequest_(e, 'GET');
}

function doPost(e) {
  try {
    const rawBody = (e && e.postData && e.postData.contents) || '';
    const data = rawBody ? JSON.parse(rawBody) : {};

    if (String(data.action || '').trim() === 'reset') {
      resetSheets_();
      return ContentService.createTextOutput('OK');
    }
  } catch (error) {
  }

  return handleRequest_(e, 'POST');
}

function handleRequest_(e, method) {
  try {
    const request = parseRequest_(e, method);

    if (!validateToken_(request.token)) {
      return jsonOutput_({
        success: false,
        message: 'Unauthorized',
      });
    }

    const action = String(request.action || '').trim();
    const payload = Array.isArray(request.payload) ? request.payload : [];

    switch (action) {
      case 'get_inventory':
        return jsonOutput_({
          success: true,
          payload: readInventoryRows_(),
        });
      case 'get_history':
        return jsonOutput_({
          success: true,
          payload: readHistoryRows_(),
        });
      case 'get_centros_nuevo_origen':
        return jsonOutput_({
          success: true,
          payload: readCentrosNuevoOrigenRows_(),
        });
      case 'append_inventory_rows':
        return jsonOutput_(appendInventoryRows_(payload));
      case 'upsert_history_rows':
        return jsonOutput_(upsertHistoryRows_(payload));
      case 'replace_inventory_rows':
        return jsonOutput_(replaceInventoryRows_(payload));
      default:
        return jsonOutput_({
          success: false,
          message: 'Accion no soportada.',
        });
    }
  } catch (error) {
    return jsonOutput_({
      success: false,
      message: error && error.message ? error.message : 'Error no controlado.',
    });
  }
}

function parseRequest_(e, method) {
  const parameters = e && e.parameter ? e.parameter : {};

  if (method === 'GET') {
    return {
      token: normalizeCell_(parameters.token),
      action: normalizeCell_(parameters.action),
      payload: [],
    };
  }

  const rawBody = (e && e.postData && e.postData.contents) || '';
  let body = {};

  if (rawBody) {
    try {
      body = JSON.parse(rawBody);
    } catch (error) {
      body = {};
    }
  }

  return {
    token: normalizeCell_(body.token || parameters.token),
    action: normalizeCell_(body.action || parameters.action),
    payload: Array.isArray(body.payload) ? body.payload : [],
  };
}

function validateToken_(token) {
  return normalizeCell_(token) !== '' && normalizeCell_(token) === SECRET_TOKEN;
}

function jsonOutput_(data) {
  return ContentService
    .createTextOutput(JSON.stringify(data))
    .setMimeType(ContentService.MimeType.JSON);
}

function getSheetOrThrow_(name) {
  const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(name);
  if (!sheet) {
    throw new Error('No existe la pestana "' + name + '".');
  }
  return sheet;
}

function resetSheets_() {
  const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  const sheets = spreadsheet.getSheets();

  sheets.forEach(sheet => {
    const lastRow = sheet.getLastRow();
    const lastColumn = Math.max(sheet.getLastColumn(), 9);
    if (lastRow > 0 && lastColumn > 0) {
      sheet.clearContents();
    }
  });

  const firstSheet = sheets[0];
  firstSheet.appendRow(['UBICACION','DESTINO','ID','EDITORIAL','FECHA ENTRADA','CODIGO CENTRO','CENTRO','FECHA SALIDA','ORDEN']);
}

function deleteInventoryRowById_(id) {
  if (!id) {
    return;
  }

  const sheet = getSheetOrThrow_(INVENTARIO_SHEET_NAME);
  const lastRow = sheet.getLastRow();
  if (lastRow < 2) {
    return;
  }

  const values = sheet.getRange(2, 1, lastRow - 1, Math.max(sheet.getLastColumn(), 9)).getDisplayValues();
  for (let i = values.length - 1; i >= 0; i--) {
    if (normalizeCell_(values[i][2]) === id) {
      sheet.deleteRow(i + 2);
    }
  }
}

function readInventoryRows_() {
  const sheet = getSheetOrThrow_(INVENTARIO_SHEET_NAME);
  const lastRow = sheet.getLastRow();
  if (lastRow < 2) {
    return [];
  }

  const values = sheet.getRange(2, 1, lastRow - 1, 12).getDisplayValues();

  return values
    .filter(row => String(row[2] || '').trim() !== '')
    .map(row => ({
      ubicacion: normalizeCell_(row[0]),
      destino: normalizeCell_(row[1]),
      id: normalizeCell_(row[2]),
      editorial: normalizeCell_(row[3]),
      fecha_entrada: normalizeCell_(row[4]),
      codigo_centro: normalizeCell_(row[5]),
      colegio: normalizeCell_(row[6]),
      fecha_salida: normalizeCell_(row[7]),
      orden: normalizeCell_(row[8]),
      bultos: normalizeCell_(row[9]),
      huecos: normalizeCell_(row[10]),
      total_hueco: normalizeCell_(row[11]),
    }));
}

function readHistoryRows_() {
  const sheet = getSheetOrThrow_(HISTORICO_SHEET_NAME);
  const lastRow = sheet.getLastRow();
  if (lastRow < 2) {
    return [];
  }

  const values = sheet.getRange(2, 1, lastRow - 1, 12).getDisplayValues();

  return values
    .filter(row => String(row[2] || '').trim() !== '')
    .map(row => ({
      ubicacion: normalizeCell_(row[0]),
      destino: normalizeCell_(row[1]),
      id: normalizeCell_(row[2]),
      editorial: normalizeCell_(row[3]),
      fecha_entrada: normalizeCell_(row[4]),
      codigo_centro: normalizeCell_(row[5]),
      colegio: normalizeCell_(row[6]),
      fecha_salida: normalizeCell_(row[7]),
      orden: normalizeCell_(row[8]),
      bultos: normalizeCell_(row[9]),
      empresa_recogida: normalizeCell_(row[10]),
      total_bultos: normalizeCell_(row[11]),
    }));
}

function readCentrosNuevoOrigenRows_() {
  const sheet = getCentrosNuevoOrigenSheet_();
  const lastRow = sheet.getLastRow();
  const lastColumn = sheet.getLastColumn();

  if (lastRow < 2 || lastColumn < 1) {
    return [];
  }

  const values = sheet.getRange(1, 1, lastRow, lastColumn).getDisplayValues();
  const headers = values[0].map(normalizeHeader_);
  const index = buildHeaderIndex_(headers);

  return values
    .slice(1)
    .map(row => normalizeCentroNuevoOrigenRow_(row, index))
    .filter(row => row.codigo_centro !== '');
}

function getCentrosNuevoOrigenSheet_() {
  const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  const exactSheet = spreadsheet.getSheetByName(CENTROS_NUEVO_ORIGEN_SHEET_NAME);
  if (exactSheet) {
    return exactSheet;
  }

  const normalizedName = normalizeCell_(CENTROS_NUEVO_ORIGEN_SHEET_NAME).toLowerCase();
  const matchedSheet = spreadsheet
    .getSheets()
    .find(sheet => normalizeCell_(sheet.getName()).toLowerCase() === normalizedName);

  if (!matchedSheet) {
    throw new Error('No existe la pestana "' + CENTROS_NUEVO_ORIGEN_SHEET_NAME + '".');
  }

  return matchedSheet;
}

function normalizeCentroNuevoOrigenRow_(row, index) {
  const almacen = getHeaderValue_(row, index, 'almacen');

  return {
    codigo_centro: getHeaderValue_(row, index, 'codigocentro'),
    nombre_centro: getHeaderValue_(row, index, 'nombrecentro'),
    localidad: getHeaderValue_(row, index, 'localidad'),
    codigo_congregacion: getHeaderValue_(row, index, 'codigocongregacion'),
    congregacion: getHeaderValue_(row, index, 'congregacion'),
    entrada: getHeaderValue_(row, index, 'entrada'),
    almacen: almacen,
    destino: calcularDestinoCentro_(almacen),
  };
}

function buildHeaderIndex_(headers) {
  return headers.reduce((acc, header, index) => {
    if (header !== '' && acc[header] === undefined) {
      acc[header] = index;
    }
    return acc;
  }, {});
}

function getHeaderValue_(row, index, headerKey) {
  const columnIndex = index[headerKey];
  if (columnIndex === undefined) {
    return '';
  }

  return normalizeCell_(row[columnIndex]);
}

function normalizeHeader_(value) {
  return normalizeCell_(value)
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '');
}

function calcularDestinoCentro_(almacen) {
  const codigoAlmacen = normalizeCodigoAlmacenCentro_(almacen);

  if (codigoAlmacen === '1901') {
    return 'EDV';
  }

  if (codigoAlmacen === '1905') {
    return 'EPL';
  }

  return '';
}

function normalizeCodigoAlmacenCentro_(almacen) {
  const texto = normalizeCell_(almacen).replace(/\s+/g, '');
  const decimalMatch = texto.match(/^(\d+)(?:[.,]0+)?$/);

  if (decimalMatch) {
    return decimalMatch[1];
  }

  const knownCodeMatch = texto.match(/(1901|1905)/);
  if (knownCodeMatch) {
    return knownCodeMatch[1];
  }

  return texto.replace(/\D+/g, '');
}

function appendInventoryRows_(rows) {
  const sheet = getSheetOrThrow_(INVENTARIO_SHEET_NAME);
  const existingIds = buildIdSet_(readInventoryRows_());
  const rowsToAppend = [];
  let existing = 0;

  rows.forEach(row => {
    const id = normalizeCell_(row.id);
    if (!id) {
      return;
    }

    if (existingIds[id]) {
      existing++;
      return;
    }

    existingIds[id] = true;
    rowsToAppend.push(toInventoryRowArray_(row));
  });

  if (rowsToAppend.length > 0) {
    sheet.getRange(sheet.getLastRow() + 1, 1, rowsToAppend.length, 12).setValues(rowsToAppend);
  }

  return {
    success: true,
    inserted: rowsToAppend.length,
    existing: existing,
  };
}

function upsertHistoryRows_(rows) {
  const sheet = getSheetOrThrow_(HISTORICO_SHEET_NAME);
  const existingIds = buildIdSet_(readHistoryRows_());
  const rowsToAppend = [];
  let existing = 0;

  rows.forEach(row => {
    const id = normalizeCell_(row.id);
    if (!id) {
      return;
    }

    if (existingIds[id]) {
      existing++;
      return;
    }

    existingIds[id] = true;
    rowsToAppend.push(toHistoryRowArray_(row));
  });

  if (rowsToAppend.length > 0) {
    sheet.getRange(sheet.getLastRow() + 1, 1, rowsToAppend.length, 12).setValues(rowsToAppend);
  }

  return {
    success: true,
    inserted: rowsToAppend.length,
    existing: existing,
  };
}

function replaceInventoryRows_(rows) {
  const sheet = getSheetOrThrow_(INVENTARIO_SHEET_NAME);
  const lastRow = sheet.getLastRow();

  if (lastRow >= 2) {
    sheet.getRange(2, 1, lastRow - 1, 12).clearContent();
  }

  if (rows.length > 0) {
    const values = rows.map(toInventoryRowArray_);
    sheet.getRange(2, 1, values.length, 12).setValues(values);
  }

  return {
    success: true,
    replaced: rows.length,
  };
}

function buildIdSet_(rows) {
  return rows.reduce((acc, row) => {
    const id = normalizeCell_(row.id);
    if (id) {
      acc[id] = true;
    }
    return acc;
  }, {});
}

function toInventoryRowArray_(row) {
  return [
    normalizeCell_(row.ubicacion),
    normalizeCell_(row.destino),
    normalizeCell_(row.id),
    normalizeCell_(row.editorial),
    normalizeCell_(row.fecha_entrada),
    normalizeCell_(row.codigo_centro),
    normalizeCell_(row.colegio),
    normalizeCell_(row.fecha_salida),
    normalizeCell_(row.orden),
    normalizeCell_(row.bultos),
    normalizeCell_(row.huecos),
    normalizeCell_(row.total_hueco),
  ];
}

function toHistoryRowArray_(row) {
  return [
    normalizeCell_(row.ubicacion),
    normalizeCell_(row.destino),
    normalizeCell_(row.id),
    normalizeCell_(row.editorial),
    normalizeCell_(row.fecha_entrada),
    normalizeCell_(row.codigo_centro),
    normalizeCell_(row.colegio),
    normalizeCell_(row.fecha_salida),
    normalizeCell_(row.orden),
    normalizeCell_(row.bultos),
    normalizeCell_(row.empresa_recogida),
    normalizeCell_(row.total_bultos),
  ];
}

function normalizeCell_(value) {
  if (value === null || value === undefined) {
    return '';
  }
  return String(value).trim();
}
