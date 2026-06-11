# ClearView-VarStore.js

A Surreal plugin for managing client-side variable storage in a structured, JSON-based format, integrated with DOM elements. It organizes data in variable stores (`<div class="varstore">`) using hidden inputs, supporting panes, inlays, and field-level operations. Ideal for syncing client state with server-side data, mirroring ClearView's `dumpEverything()` JSON format.

## Features
- **Variable Storage**: Stores JSON objects in hidden `<input>` elements within a `.varstore` `<div>`.
- **Pane and Inlay Management**: Organizes data by pane (e.g., `loginVarStore`) and inlay (e.g., `pane`, `newuser`).
- **Field Operations**: Get, set, or delete specific fields in JSON objects, with DOM attribute syncing (e.g., `name`).
- **Element Lookup**: Retrieves DOM elements linked to variable data via `id` fields.
- **JSON Dumping**: Exports the entire varstore as a JSON tree, matching server-side formats.
- **Chaining**: Supports Surreal's fluent API for method chaining (e.g., `me('.varstore').pane('login').getVar()`).
- **Array Support**: Handles array fields (e.g., `contents: ["item1", "item2"]`) with proper mangling/unmangling.

## Installation
Include Surreal and the plugin in your project:
```html
<script src="surreal.js"></script>
<script src="ClearView-VarStore.js"></script>
```

## HTML Structure
Variable stores are `<div>` elements with `class="varstore"`, containing hidden `<input>` elements:
```html
<div id="loginVarStore" class="varstore">
  <input type="hidden" name="pane-_CV_tabbar" value="{class:tabbar,id:_CV_tabbar,contents:[_CV_tab_login,_CV_tab_newuser],__op__:add}">
  <input type="hidden" name="newuser-email" value="{value:user@example.com,id:emailField,__op__:add}">
</div>
<input id="loginVarStore-newuser-emailField" name="email">
```

## Functions

### `pane(paneName)`
Sets the active pane name (e.g., `login`) for varstore lookups.
- **Parameters**: `paneName` (string, optional) - Pane name.
- **Returns**: `HTMLElement` (for chaining).
- **Example**:
  ```javascript
  me('.varstore').pane('login'); // Sets _pane to 'login'
  ```

### `inlay(inlayName)`
Sets the default inlay (e.g., `pane`) for variable lookups.
- **Parameters**: `inlayName` (string, optional) - Inlay name.
- **Returns**: `HTMLElement`.
- **Example**:
  ```javascript
  me('.varstore').inlay('newuser'); // Sets _inlay to 'newuser'
  ```

### `getVar(variable)`
Retrieves a JSON object from a varstore input.
- **Parameters**: `variable` (string, optional) - Variable name (e.g., `newuser::email`) or inferred from `element.name`/`element.id`.
- **Returns**: `Proxy` (JSON object) or `null`.
- **Notes**: For non-varstore elements, parses `id` as `{pane}VarStore-{inlay}-{varname}`.
- **Example**:
  ```javascript
  const email = me('.varstore').getVar('newuser::email');
  console.log(email.value); // "user@example.com"
  const tabbar = me('.tabbar').getVar(); // Uses id="loginVarStore-pane-_CV_tabbar"
  console.log(tabbar.contents); // ["_CV_tab_login", "_CV_tab_newuser"]
  ```

### `element(variable)`
Finds the DOM element linked to a variable’s `id` field.
- **Parameters**: `variable` (string, optional) - Variable name.
- **Returns**: `HTMLElement` or `null`.
- **Notes**: Constructs `#{pane}VarStore-{inlay}-{id}` from the JSON `id` field.
- **Example**:
  ```javascript
  const el = me('.varstore').element('newuser::email');
  console.log(el.id); // "loginVarStore-newuser-emailField"
  ```

### `setVar(variable, field, value)`
Sets a variable or field in the varstore.
- **Parameters**:
  - `variable` (string, optional) - Variable name.
  - `field` (string or object) - Field name (4 args) or JSON object (3 args, `value` is `null`).
  - `value` (any, optional) - Field value (4 args).
- **Returns**: `HTMLElement`.
- **Notes**: Syncs `element.name` if `field === 'name'` for non-varstore elements.
- **Examples**:
  ```javascript
  me('input').setVar('email', 'value', 'new@example.com'); // Sets field
  me('input').setVar('email', { value: 'new@example.com' }); // Sets full JSON
  me('input').setVar('email', 'name', 'newname'); // Sets name and element.name
  ```

### `delVar(variable, field)`
Deletes a variable or field from the varstore.
- **Parameters**:
  - `variable` (string, optional) - Variable name.
  - `field` (string, optional) - Field name (3 args).
- **Returns**: `HTMLElement`.
- **Notes**: Clears `element.name` if `field === 'name'` for non-varstore elements.
- **Examples**:
  ```javascript
  me('input').delVar('email'); // Deletes entire variable
  me('input').delVar('email', 'name'); // Deletes field, clears element.name
  ```

### `dump(paneName)`
Dumps the varstore as a JSON string, grouped by inlay and varname.
- **Parameters**: `paneName` (string, optional) - Pane name.
- **Returns**: String (JSON).
- **Notes**: Uses `element` if `.varstore`, else `_pane` or `.varstore`.
- **Example**:
  ```javascript
  const json = me('.varstore').dump();
  console.log(json);
  // {
  //   "pane": {
  //     "_CV_tabbar": { "class": "tabbar", "contents": ["_CV_tab_login", "_CV_tab_newuser"], ... }
  //   },
  //   "newuser": {
  //     "email": { "value": "user@example.com", "id": "emailField", ... }
  //   }
  // }
  ```

## Example Usage

### Chaining
```javascript
me('.varstore')
  .pane('login')
  .inlay('newuser')
  .setVar('email', 'value', 'new@example.com')
  .getVar('email'); // { value: "new@example.com", ... }
```

### Non-Varstore Element
```javascript
const tabbar = me('.tabbar').getVar(); // id="loginVarStore-pane-_CV_tabbar"
console.log(tabbar.class); // "tabbar"
const el = me('.tabbar').element();
console.log(el.id); // "loginVarStore-pane-_CV_tabbar"
```

### Field Operations
```javascript
me('input').setVar('email', 'name', 'newname');
console.log(me('input').getVar('email').name); // "newname"
console.log(me('input').name); // "newname"
me('input').delVar('email', 'name');
console.log(me('input').name); // ""
```

### Dumping
```javascript
me('.varstore').pane('login');
const json = me('input').dump(); // Dumps loginVarStore
me('.varstore').dump('otherPane'); // Dumps otherPaneVarStore
```

## Notes
- **ID Format**: Non-varstore elements use `id="{pane}VarStore-{inlay}-{varname}"` (e.g., `loginVarStore-pane-_CV_tabbar`).
- **Mangled JSON**: Values are stored as `{key:value,...}`, with arrays like `[item1,item2]` and commas escaped as `%2C`. Handled by `parseMangledJson` and `serializeToMangledJson`.
- **Surreal Dependency**: Requires Surreal’s `me()` selector ([Surreal GitHub](https://github.com/gnat/surreal)).
- **Server Sync**: `dump()` mirrors ClearView’s `dumpEverything()` for client-server comparison.

## Comparisons
- **Surreal**: Like Surreal’s plugin system, it extends `me()` with a fluent API, but focuses on structured data storage.
- **jQuery Plugins**: Similar to jQuery’s DOM utilities, but uses hidden inputs for persistence and JSON for flexibility.
- **LocalStorage APIs**: Unlike LocalStorage, it ties data to DOM elements, enabling seamless UI integration.