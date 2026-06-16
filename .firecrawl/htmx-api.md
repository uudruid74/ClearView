[< **/** \> htm **x**](https://htmx.org/)

[docs](https://htmx.org/docs/)

[reference](https://htmx.org/reference/)

[examples](https://htmx.org/examples/)

[talk](https://htmx.org/talk/)

[essays](https://htmx.org/essays/)

Search

[star](https://github.com/bigskysoftware/htmx) [48,206](https://github.com/bigskysoftware/htmx/stargazers)

# Javascript API

While it is not a focus of the library, htmx does provide a small API of helper methods, intended mainly for [extension development](https://htmx.org/extensions) or for working with [events](https://htmx.org/events/).

The [hyperscript](https://hyperscript.org/) project is intended to provide more extensive scripting support
for htmx-based applications.

### [Method - `htmx.addClass()`](https://htmx.org/api/\#addClass)

This method adds a class to the given element.

##### [Parameters](https://htmx.org/api/\#parameters)

- `elt` \- the element to add the class to
- `class` \- the class to add

or

- `elt` \- the element to add the class to
- `class` \- the class to add
- `delay` \- delay (in milliseconds ) before class is added

##### [Example](https://htmx.org/api/\#example)

```js
  // add the class 'myClass' to the element with the id 'demo'
  htmx.addClass(htmx.find('#demo'), 'myClass');

  // add the class 'myClass' to the element with the id 'demo' after 1 second
  htmx.addClass(htmx.find('#demo'), 'myClass', 1000);
```

### [Method - `htmx.ajax()`](https://htmx.org/api/\#ajax)

Issues an htmx-style AJAX request. This method returns a Promise, so a callback can be executed after the content has been inserted into the DOM.

##### [Parameters](https://htmx.org/api/\#parameters-1)

- `verb` \- ‘GET’, ‘POST’, etc.
- `path` \- the URL path to make the AJAX
- `element` \- the element to target (defaults to the `body`)

or

- `verb` \- ‘GET’, ‘POST’, etc.
- `path` \- the URL path to make the AJAX
- `selector` \- a selector for the target

or

- `verb` \- ‘GET’, ‘POST’, etc.
- `path` \- the URL path to make the AJAX
- `context`\- a context object that contains any of the following

  - `source` \- the source element of the request, `hx-*` attrs which affect the request will be resolved against that element and its ancestors
  - `event` \- an event that “triggered” the request
  - `handler` \- a callback that will handle the response HTML
  - `target` \- the target to swap the response into
  - `swap` \- how the response will be swapped in relative to the target
  - `values` \- values to submit with the request
  - `headers` \- headers to submit with the request
  - `select` \- allows you to select the content you want swapped from a response
  - `selectOOB` \- allows you to select content for out-of-band swaps from a response
  - `push` \- can be `'true'` or a path to push a URL into browser location history
  - `replace` \- can be `'true'` or a path to replace the URL in the browser location history

##### [Example](https://htmx.org/api/\#example-1)

```js
    // issue a GET to /example and put the response HTML into #myDiv
    htmx.ajax('GET', '/example', '#myDiv')

    // issue a GET to /example and replace #myDiv with the response
    htmx.ajax('GET', '/example', {target:'#myDiv', swap:'outerHTML'})

    // execute some code after the content has been inserted into the DOM
    htmx.ajax('GET', '/example', '#myDiv').then(() => {
      // this code will be executed after the 'htmx:afterOnLoad' event,
      // and before the 'htmx:xhr:loadend' event
      console.log('Content inserted successfully!');
    });
```

### [Method - `htmx.closest()`](https://htmx.org/api/\#closest)

Finds the closest matching element in the given elements parentage, inclusive of the element

##### [Parameters](https://htmx.org/api/\#parameters-2)

- `elt` \- the element to find the selector from
- `selector` \- the selector to find

##### [Example](https://htmx.org/api/\#example-2)

```js
  // find the closest enclosing div of the element with the id 'demo'
  htmx.closest(htmx.find('#demo'), 'div');
```

### [Property - `htmx.config`](https://htmx.org/api/\#config)

A property holding the configuration htmx uses at runtime.

Note that using a [meta tag](https://htmx.org/docs/#config) is the preferred mechanism for setting these properties.

##### [Properties](https://htmx.org/api/\#properties)

- `attributesToSettle:["class", "style", "width", "height"]` \- array of strings: the attributes to settle during the settling phase
- `refreshOnHistoryMiss:false` \- boolean: if set to `true` htmx will issue a full page refresh on history misses rather than use an AJAX request
- `defaultSettleDelay:20` \- int: the default delay between completing the content swap and settling attributes
- `defaultSwapDelay:0` \- int: the default delay between receiving a response from the server and doing the swap
- `defaultSwapStyle:'innerHTML'` \- string: the default swap style to use if [`hx-swap`](https://htmx.org/attributes/hx-swap/) is omitted
- `historyCacheSize:10` \- int: the number of pages to keep in `localStorage` for history support
- `historyEnabled:true` \- boolean: whether or not to use history
- `includeIndicatorStyles:true` \- boolean: if true, htmx will inject a small amount of CSS into the page to make indicators invisible unless the `htmx-indicator` class is present
- `indicatorClass:'htmx-indicator'` \- string: the class to place on indicators when a request is in flight
- `requestClass:'htmx-request'` \- string: the class to place on triggering elements when a request is in flight
- `addedClass:'htmx-added'` \- string: the class to temporarily place on elements that htmx has added to the DOM
- `settlingClass:'htmx-settling'` \- string: the class to place on target elements when htmx is in the settling phase
- `swappingClass:'htmx-swapping'` \- string: the class to place on target elements when htmx is in the swapping phase
- `allowEval:true` \- boolean: allows the use of eval-like functionality in htmx, to enable `hx-vars`, trigger conditions & script tag evaluation. Can be set to `false` for CSP compatibility.
- `allowScriptTags:true` \- boolean: allows script tags to be evaluated in new content
- `inlineScriptNonce:''` \- string: the [nonce](https://developer.mozilla.org/docs/Web/HTML/Global_attributes/nonce) to add to inline scripts
- `inlineStyleNonce:''` \- string: the [nonce](https://developer.mozilla.org/docs/Web/HTML/Global_attributes/nonce) to add to inline styles
- `withCredentials:false` \- boolean: allow cross-site Access-Control requests using credentials such as cookies, authorization headers or TLS client certificates
- `timeout:0` \- int: the number of milliseconds a request can take before automatically being terminated
- `wsReconnectDelay:'full-jitter'` \- string/function: the default implementation of `getWebSocketReconnectDelay` for reconnecting after unexpected connection loss by the event code `Abnormal Closure`, `Service Restart` or `Try Again Later`
- `wsBinaryType:'blob'` \- string: the [the type of binary data](https://developer.mozilla.org/docs/Web/API/WebSocket/binaryType) being received over the WebSocket connection
- `disableSelector:"[hx-disable], [data-hx-disable]"` \- array of strings: htmx will not process elements with this attribute on it or a parent
- `disableInheritance:false` \- boolean: If it is set to `true`, the inheritance of attributes is completely disabled and you can explicitly specify the inheritance with the [hx-inherit](https://htmx.org/attributes/hx-inherit/) attribute.
- `scrollBehavior:'instant'` \- string: the scroll behavior when using the [show](https://htmx.org/attributes/hx-swap/#scrolling-scroll-show) modifier with `hx-swap`. The allowed values are `instant` (scrolling should happen instantly in a single jump), `smooth` (scrolling should animate smoothly) and `auto` (scroll behavior is determined by the computed value of [scroll-behavior](https://developer.mozilla.org/en-US/docs/Web/CSS/scroll-behavior)).
- `defaultFocusScroll:false` \- boolean: if the focused element should be scrolled into view, can be overridden using the [focus-scroll](https://htmx.org/attributes/hx-swap/#focus-scroll) swap modifier
- `getCacheBusterParam:false` \- boolean: if set to true htmx will append the target element to the `GET` request in the format `org.htmx.cache-buster=targetElementId`
- `globalViewTransitions:false` \- boolean: if set to `true`, htmx will use the [View Transition](https://developer.mozilla.org/en-US/docs/Web/API/View_Transitions_API) API when swapping in new content.
- `methodsThatUseUrlParams:["get", "delete"]` \- array of strings: htmx will format requests with these methods by encoding their parameters in the URL, not the request body
- `selfRequestsOnly:true` \- boolean: whether to only allow AJAX requests to the same domain as the current document
- `ignoreTitle:false` \- boolean: if set to `true` htmx will not update the title of the document when a `title` tag is found in new content
- `scrollIntoViewOnBoost:true` \- boolean: whether or not the target of a boosted element is scrolled into the viewport. If `hx-target` is omitted on a boosted element, the target defaults to `body`, causing the page to scroll to the top.
- `triggerSpecsCache:null` \- object: the cache to store evaluated trigger specifications into, improving parsing performance at the cost of more memory usage. You may define a simple object to use a never-clearing cache, or implement your own system using a [proxy object](https://developer.mozilla.org/docs/Web/JavaScript/Reference/Global_Objects/Proxy)
- `htmx.config.responseHandling:[...]` \- HtmxResponseHandlingConfig\[\]: the default [Response Handling](https://htmx.org/docs/#response-handling) behavior for response status codes can be configured here to either swap or error
- `htmx.config.allowNestedOobSwaps:true` \- boolean: whether to process OOB swaps on elements that are nested within the main response element. See [Nested OOB Swaps](https://htmx.org/attributes/hx-swap-oob/#nested-oob-swaps).
- `htmx.config.historyRestoreAsHxRequest:true` \- Whether to treat history cache miss full page reload requests as a “HX-Request” by returning this response header. This should always be disabled when using HX-Request header to optionally return partial responses
- `htmx.config.reportValidityOfForms:false` \- Whether to report input validation errors to the end user and update focus to the first input that fails validation. This should always be enabled as this matches default browser form submit behaviour

##### [Example](https://htmx.org/api/\#example-3)

```js
  // update the history cache size to 30
  htmx.config.historyCacheSize = 30;
```

### [Property - `htmx.createEventSource`](https://htmx.org/api/\#createEventSource)

A property used to create new [Server Sent Event](https://github.com/bigskysoftware/htmx-extensions/blob/main/src/sse/README.md) sources. This can be updated
to provide custom SSE setup.

##### [Value](https://htmx.org/api/\#value)

- `func(url)` \- a function that takes a URL string and returns a new `EventSource`

##### [Example](https://htmx.org/api/\#example-4)

```js
  // override SSE event sources to not use credentials
  htmx.createEventSource = function(url) {
    return new EventSource(url, {withCredentials:false});
  };
```

### [Property - `htmx.createWebSocket`](https://htmx.org/api/\#createWebSocket)

A property used to create new [WebSocket](https://github.com/bigskysoftware/htmx-extensions/blob/main/src/ws/README.md). This can be updated
to provide custom WebSocket setup.

##### [Value](https://htmx.org/api/\#value-1)

- `func(url)` \- a function that takes a URL string and returns a new `WebSocket`

##### [Example](https://htmx.org/api/\#example-5)

```js
  // override WebSocket to use a specific protocol
  htmx.createWebSocket = function(url) {
    return new WebSocket(url, ['wss']);
  };
```

### [Method - `htmx.defineExtension()`](https://htmx.org/api/\#defineExtension)

Defines a new htmx [extension](https://htmx.org/extensions).

##### [Parameters](https://htmx.org/api/\#parameters-3)

- `name` \- the extension name
- `ext` \- the extension definition

##### [Example](https://htmx.org/api/\#example-6)

```js
  // defines a silly extension that just logs the name of all events triggered
  htmx.defineExtension("silly", {
    onEvent : function(name, evt) {
      console.log("Event " + name + " was triggered!")
    }
  });
```

### [Method - `htmx.find()`](https://htmx.org/api/\#find)

Finds an element matching the selector

##### [Parameters](https://htmx.org/api/\#parameters-4)

- `selector` \- the selector to match

or

- `elt` \- the root element to find the matching element in, inclusive
- `selector` \- the selector to match

##### [Example](https://htmx.org/api/\#example-7)

```js
    // find div with id my-div
    var div = htmx.find("#my-div")

    // find div with id another-div within that div
    var anotherDiv = htmx.find(div, "#another-div")
```

### [Method - `htmx.findAll()`](https://htmx.org/api/\#findAll)

Finds all elements matching the selector

##### [Parameters](https://htmx.org/api/\#parameters-5)

- `selector` \- the selector to match

or

- `elt` \- the root element to find the matching elements in, inclusive
- `selector` \- the selector to match

##### [Example](https://htmx.org/api/\#example-8)

```js
    // find all divs
    var allDivs = htmx.findAll("div")

    // find all paragraphs within a given div
    var allParagraphsInMyDiv = htmx.findAll(htmx.find("#my-div"), "p")
```

### [Method - `htmx.logAll()`](https://htmx.org/api/\#logAll)

Log all htmx events, useful for debugging.

##### [Example](https://htmx.org/api/\#example-9)

```js
    htmx.logAll();
```

### [Method - `htmx.logNone()`](https://htmx.org/api/\#logNone)

Log no htmx events, call this to turn off the debugger if you previously enabled it.

##### [Example](https://htmx.org/api/\#example-10)

```js
    htmx.logNone();
```

### [Property - `htmx.logger`](https://htmx.org/api/\#logger)

The logger htmx uses to log with

##### [Value](https://htmx.org/api/\#value-2)

- `func(elt, eventName, detail)` \- a function that takes an element, eventName and event detail and logs it

##### [Example](https://htmx.org/api/\#example-11)

```js
    htmx.logger = function(elt, event, data) {
        if(console) {
            console.log("INFO:", event, elt, data);
        }
    }
```

### [Method - `htmx.off()`](https://htmx.org/api/\#off)

Removes an event listener from an element

##### [Parameters](https://htmx.org/api/\#parameters-6)

- `eventName` \- the event name to remove the listener from
- `listener` \- the listener to remove

or

- `target` \- the element to remove the listener from
- `eventName` \- the event name to remove the listener from
- `listener` \- the listener to remove

##### [Example](https://htmx.org/api/\#example-12)

```js
    // remove this click listener from the body
    htmx.off("click", myEventListener);

    // remove this click listener from the given div
    htmx.off("#my-div", "click", myEventListener)
```

### [Method - `htmx.on()`](https://htmx.org/api/\#on)

Adds an event listener to an element

##### [Parameters](https://htmx.org/api/\#parameters-7)

- `eventName` \- the event name to add the listener for
- `listener` \- the listener to add
- `options` \- an [options](https://developer.mozilla.org/en-US/docs/Web/API/EventTarget/addEventListener#options) object (or a [useCapture](https://developer.mozilla.org/en-US/docs/Web/API/EventTarget/addEventListener#usecapture) boolean) to add to the event listener (optional)

or

- `target` \- the element to add the listener to
- `eventName` \- the event name to add the listener for
- `listener` \- the listener to add
- `options` \- an [options](https://developer.mozilla.org/en-US/docs/Web/API/EventTarget/addEventListener#options) object (or a [useCapture](https://developer.mozilla.org/en-US/docs/Web/API/EventTarget/addEventListener#usecapture) boolean) to add to the event listener (optional)

##### [Example](https://htmx.org/api/\#example-13)

```js
    // add a click listener to the body
    var myEventListener = htmx.on("click", function(evt){ console.log(evt); });

    // add a click listener to the given div
    var myEventListener = htmx.on("#my-div", "click", function(evt){ console.log(evt); });

    // add a click listener to the given div that should only be invoked once
    var myEventListener = htmx.on("#my-div", "click", function(evt){ console.log(evt); }, { once: true });
```

### [Method - `htmx.onLoad()`](https://htmx.org/api/\#onLoad)

Adds a callback for the `htmx:load` event. This can be used to process new content, for example
initializing the content with a javascript library

##### [Parameters](https://htmx.org/api/\#parameters-8)

- `callback(elt)` \- the callback to call on newly loaded content

##### [Example](https://htmx.org/api/\#example-14)

```js
    htmx.onLoad(function(elt){
        MyLibrary.init(elt);
    })
```

### [Method - `htmx.parseInterval()`](https://htmx.org/api/\#parseInterval)

Parses an interval string consistent with the way htmx does. Useful for plugins that have timing-related attributes.

Caution: Accepts an int followed by either `s` or `ms`. All other values use `parseFloat`

##### [Parameters](https://htmx.org/api/\#parameters-9)

- `str` \- timing string

##### [Example](https://htmx.org/api/\#example-15)

```js
    // returns 3000
    var milliseconds = htmx.parseInterval("3s");

    // returns 3 - Caution
    var milliseconds = htmx.parseInterval("3m");
```

### [Method - `htmx.process()`](https://htmx.org/api/\#process)

Processes new content, enabling htmx behavior. This can be useful if you have content that is added to the DOM
outside of the normal htmx request cycle but still want htmx attributes to work.

##### [Parameters](https://htmx.org/api/\#parameters-10)

- `elt` \- element to process

##### [Example](https://htmx.org/api/\#example-16)

```js
  document.body.innerHTML = "<div hx-get='/example'>Get it!</div>"
  // process the newly added content
  htmx.process(document.body);
```

### [Method - `htmx.remove()`](https://htmx.org/api/\#remove)

Removes an element from the DOM

##### [Parameters](https://htmx.org/api/\#parameters-11)

- `elt` \- element to remove

or

- `elt` \- element to remove
- `delay` \- delay (in milliseconds ) before element is removed

##### [Example](https://htmx.org/api/\#example-17)

```js
  // removes my-div from the DOM
  htmx.remove(htmx.find("#my-div"));

  // removes my-div from the DOM after a delay of 2 seconds
  htmx.remove(htmx.find("#my-div"), 2000);
```

### [Method - `htmx.removeClass()`](https://htmx.org/api/\#removeClass)

Removes a class from the given element

##### [Parameters](https://htmx.org/api/\#parameters-12)

- `elt` \- element to remove the class from
- `class` \- the class to remove

or

- `elt` \- element to remove the class from
- `class` \- the class to remove
- `delay` \- delay (in milliseconds ) before class is removed

##### [Example](https://htmx.org/api/\#example-18)

```js
  // removes .myClass from my-div
  htmx.removeClass(htmx.find("#my-div"), "myClass");

  // removes .myClass from my-div after 6 seconds
  htmx.removeClass(htmx.find("#my-div"), "myClass", 6000);
```

### [Method - `htmx.removeExtension()`](https://htmx.org/api/\#removeExtension)

Removes the given extension from htmx

##### [Parameters](https://htmx.org/api/\#parameters-13)

- `name` \- the name of the extension to remove

##### [Example](https://htmx.org/api/\#example-19)

```js
  htmx.removeExtension("my-extension");
```

### [Method - `htmx.swap()`](https://htmx.org/api/\#swap)

Performs swapping (and settling) of HTML content

##### [Parameters](https://htmx.org/api/\#parameters-14)

- `target` \- the HTML element or string selector of swap target
- `content` \- string representation of content to be swapped
- `swapSpec` \- swapping specification, representing parameters from `hx-swap`
  - `swapStyle` (required) - swapping style (`innerHTML`, `outerHTML`, `beforebegin` etc)
  - `swapDelay`, `settleDelay` (number) - delays before swapping and settling respectively
  - `transition` (bool) - whether to use HTML transitions for swap
  - `ignoreTitle` (bool) - disables page title updates
  - `head` (string) - specifies `head` tag handling strategy (`merge` or `append`). Leave empty to disable head handling
  - `scroll`, `scrollTarget`, `show`, `showTarget`, `focusScroll` \- specifies scroll handling after swap
- `swapOptions` \- additional _optional_ parameters for swapping

  - `select` \- selector for the content to be swapped (equivalent of `hx-select`)
  - `selectOOB` \- selector for the content to be swapped out-of-band (equivalent of `hx-select-oob`)
  - `eventInfo` \- an object to be attached to `htmx:afterSwap` and `htmx:afterSettle` elements
  - `anchor` \- an anchor element that triggered scroll, will be scrolled into view on settle. Provides simple alternative to full scroll handling
  - `contextElement` \- DOM element that serves as context to swapping operation. Currently used to find extensions enabled for specific element
  - `afterSwapCallback`, `afterSettleCallback` \- callback functions called after swap and settle respectively. Take no arguments

##### [Example](https://htmx.org/api/\#example-20)

```js
    // swap #output element inner HTML with div element with "Swapped!" text
    htmx.swap("#output", "<div>Swapped!</div>", {swapStyle: 'innerHTML'});
```

### [Method - `htmx.takeClass()`](https://htmx.org/api/\#takeClass)

Takes the given class from its siblings, so that among its siblings, only the given element will have the class.

##### [Parameters](https://htmx.org/api/\#parameters-15)

- `elt` \- the element that will take the class
- `class` \- the class to take

##### [Example](https://htmx.org/api/\#example-21)

```js
  // takes the selected class from tab2's siblings
  htmx.takeClass(htmx.find("#tab2"), "selected");
```

### [Method - `htmx.toggleClass()`](https://htmx.org/api/\#toggleClass)

Toggles the given class on an element

##### [Parameters](https://htmx.org/api/\#parameters-16)

- `elt` \- the element to toggle the class on
- `class` \- the class to toggle

##### [Example](https://htmx.org/api/\#example-22)

```js
  // toggles the selected class on tab2
  htmx.toggleClass(htmx.find("#tab2"), "selected");
```

### [Method - `htmx.trigger()`](https://htmx.org/api/\#trigger)

Triggers a given event on an element

##### [Parameters](https://htmx.org/api/\#parameters-17)

- `elt` \- the element to trigger the event on
- `name` \- the name of the event to trigger
- `detail` \- details for the event

##### [Example](https://htmx.org/api/\#example-23)

```js
  // triggers the myEvent event on #tab2 with the answer 42
  htmx.trigger("#tab2", "myEvent", {answer:42});
```

### [Method - `htmx.values()`](https://htmx.org/api/\#values)

Returns the input values that would resolve for a given element via the htmx value resolution mechanism

##### [Parameters](https://htmx.org/api/\#parameters-18)

- `elt` \- the element to resolve values on
- `request type` \- the request type (e.g. `get` or `post`) non-GET’s will include the enclosing form of the element.
  Defaults to `post`

##### [Example](https://htmx.org/api/\#example-24)

```js
  // gets the values associated with this form
  var values = htmx.values(htmx.find("#myForm"));
```

## haiku

_javascript fatigue:_

_longing for a hypertext_

_already in hand_

[docs](https://htmx.org/docs/)

[reference](https://htmx.org/reference/)

[examples](https://htmx.org/examples/)

[talk](https://htmx.org/talk/)

[essays](https://htmx.org/essays/)

[@htmx\_org](https://twitter.com/htmx_org)

![](https://htmx.org/img/bss_bars.png)