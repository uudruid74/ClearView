// Parse mangled JSON string (e.g., "{key:value,arr:[a,b]}") into an object
function parseMangledJson(string) {
  if (!string.startsWith('{') || !string.endsWith('}')) return {};
  string = string.slice(1, -1);
  const obj = {};
  const regex = /([^:]+):([^,]+(?:\[[^\]]+\])?)(?:,|$)/g;
  let match;
  while (match = regex.exec(string)) {
    const key = match[1].trim();
    const value = match[2].trim();
    obj[key] = value.startsWith('[') ? value.slice(1, -1).split(',').map(v => v.trim().replace(/%2C/g, ',')) : value.replace(/%2C/g, ',');
  }
  return obj;
}

// Serialize object to mangled JSON string
function serializeToMangledJson(obj) {
  return `{${Object.entries(obj)
    .filter(([, value]) => value !== undefined)
    .map(([key, value]) => `${key}:${Array.isArray(value) ? `[${value.join(',')}]` : value.toString().replace(/,/g, '%2C')}`)
    .join(',')}}`;
}

function clearviewVarStorePlugin(element) {
  let _pane = null;
  let _inlay = 'pane';

  // Get the varstore element (priority: element if .varstore, _pane, or first .varstore)
  function getVarstore(element) {
    return element?.classList?.contains('varstore') ? element :
           _pane && me(`#${_pane}VarStore`)?.classList?.contains('varstore') ? me(`#${_pane}VarStore`) :
           me('.varstore') || null;
  }

  // Parse inlay and varname from element or variable string, pass to callback
  function parseName(element, variable, callback) {
    const varstore = getVarstore(element);
    const isVarstore = element?.classList?.contains('varstore');
    if (!varstore) return callback(null);
    let inlay = _inlay;
    let varname = variable;
    if (!isVarstore && element) {
      varname = variable || element.name;
      if (!variable?.includes('::') && element.id) {
        const match = element.id.match(/^([^-]+)VarStore-([^-]+)-(.+)$/);
        if (match) [inlay, varname] = [match[2], varname || match[3]];
      }
    }
    if (variable?.includes('::')) [inlay, varname] = variable.split('::');
    return callback(varstore, inlay, varname);
  }

  // Set the active pane name
  function pane(element, paneName) {
    _pane = paneName || null;
    return element;
  }

  // Set the default inlay
  function inlay(element, inlayName) {
    _inlay = inlayName || 'pane';
    return element;
  }

  // Get a variable’s JSON object
  function getVar(element, variable) {
    return parseName(element, variable, (varstore, inlay, varname) => {
      const input = me(`input[name="${inlay}-${varname}"]`, varstore);
      if (!input) return null;
      return new Proxy(parseMangledJson(input.value), {
        set(target, key, value) {
          target[key] = value;
          input.value = serializeToMangledJson(target);
          return true;
        }
      });
    });
  }

  // Find the DOM element linked to a variable’s id
  function element(element, variable) {
    return parseName(element, variable, (varstore, inlay, varname) => {
      const input = me(`input[name="${inlay}-${varname}"]`, varstore);
      if (!input) return null;
      const id = parseMangledJson(input.value).id;
      return id ? me(`#${_pane || varstore.id?.replace('VarStore', '') || 'pane'}VarStore-${inlay}-${id}`) : null;
    });
  }

  // Set a variable or field in the varstore
  function setVar(element, variable, field, value) {
    return parseName(element, variable, (varstore, inlay, varname) => {
      if (!varstore) return element;
      let input = me(`input[name="${inlay}-${varname}"]`, varstore);
      if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = `${inlay}-${varname}`;
        varstore.appendChild(input);
      }
      const obj = parseMangledJson(input.value);
      input.value = value === null ? serializeToMangledJson(field) : (obj[field] = value, serializeToMangledJson(obj));
      if (!element.classList?.contains('varstore') && field === 'name' && element && value !== undefined) element.name = value;
      return element;
    });
  }

  // Delete a variable or field from the varstore
  function delVar(element, variable, field) {
    return parseName(element, variable, (varstore, inlay, varname) => {
      if (!varstore) return element;
      const input = me(`input[name="${inlay}-${varname}"]`, varstore);
      if (!input) return element;
      if (field) {
        const obj = parseMangledJson(input.value);
        delete obj[field];
        input.value = serializeToMangledJson(obj);
        if (!element.classList?.contains('varstore') && field === 'name' && element) element.name = '';
      } else {
        input.remove();
      }
      return element;
    });
  }

  // Dump the varstore as a JSON string
  function dump(element, paneName) {
    const varstore = paneName ? me(`#${paneName}VarStore`) :
                     element?.classList?.contains('varstore') ? element : getVarstore();
    if (!varstore) return '{}';
    const tree = {};
    varstore.querySelectorAll('input[type="hidden"]').forEach(input => {
      const name = input.name;
      if (!name) return;
      const [inlay, varname] = name.includes('-') ? name.split('-') : [_inlay, name];
      tree[inlay] = tree[inlay] || {};
      tree[inlay][varname] = parseMangledJson(input.value);
    });
    return JSON.stringify(tree, null, 2);
  }

  // Sugar layer for chaining
  element.pane = paneName => pane(element, paneName);
  element.inlay = inlayName => inlay(element, inlayName);
  element.getVar = variable => getVar(element, variable);
  element.element = variable => element(element, variable);
  element.setVar = (variable, field, value) => setVar(element, variable, field, value);
  element.delVar = (variable, field) => delVar(element, variable, field);
  element.dump = paneName => dump(element, paneName);
}

surreal.plugins.push(clearviewVarStorePlugin);