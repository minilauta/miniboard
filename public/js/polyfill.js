import 'whatwg-fetch';

// Element.prototype.matches
// IE: 9+ (msMatchesSelector), FF: 3.6+ (mozMatchesSelector), standard: FF34+
(function (proto) {
  if (proto.hasOwnProperty('matches')) {
    return;
  }
  Object.defineProperty(proto, 'matches', {
    configurable: true,
    enumerable: true,
    writable: true,
    value: proto.msMatchesSelector ||
      proto.mozMatchesSelector ||
      proto.webkitMatchesSelector
  });
})(Element.prototype);

// Element.prototype.closest
// IE: none, FF: 35+
(function (proto) {
  if (proto.hasOwnProperty('closest')) {
    return;
  }
  Object.defineProperty(proto, 'closest', {
    configurable: true,
    enumerable: true,
    writable: true,
    value: function closest(s) {
      var el = this;
      do {
        if (el.matches(s)) return el;
        el = el.parentElement;
      } while (el !== null);
      return null;
    }
  });
})(Element.prototype);

// Element.prototype.replaceChildren
// IE: none, FF: 78+
(function (proto) {
  if (proto.hasOwnProperty('replaceChildren')) {
    return;
  }
  Object.defineProperty(proto, 'replaceChildren', {
    configurable: true,
    enumerable: true,
    writable: true,
    value: function replaceChildren() {
      while (this.lastChild) this.removeChild(this.lastChild);
      this.append.apply(this, arguments);
    }
  });
})(Element.prototype);

// HTMLFormElement.prototype.requestSubmit
// IE: none, FF: 75+
(function (proto) {
  if (proto.hasOwnProperty('requestSubmit')) {
    return;
  }
  Object.defineProperty(proto, 'requestSubmit', {
    configurable: true,
    enumerable: true,
    writable: true,
    value: function requestSubmit(submitter) {
      if (submitter) {
        submitter.click();
      } else {
        this.submit();
      }
    }
  });
})(HTMLFormElement.prototype);

// window.scrollX / window.scrollY
// IE: none (use pageXOffset/pageYOffset)
(function (win) {
  if (!('scrollX' in win)) {
    Object.defineProperty(win, 'scrollX', { get: function () { return win.pageXOffset; } });
    Object.defineProperty(win, 'scrollY', { get: function () { return win.pageYOffset; } });
  }
})(window);

// Source: https://github.com/jserz/js_piece/blob/master/DOM/ParentNode/append()/append().md
(function (arr) {
  arr.forEach(function (item) {
    if (item.hasOwnProperty('append')) {
      return;
    }
    Object.defineProperty(item, 'append', {
      configurable: true,
      enumerable: true,
      writable: true,
      value: function append() {
        var argArr = Array.prototype.slice.call(arguments),
          docFrag = document.createDocumentFragment();

        argArr.forEach(function (argItem) {
          var isNode = argItem instanceof Node;
          docFrag.appendChild(isNode ? argItem : document.createTextNode(String(argItem)));
        });

        this.appendChild(docFrag);
      }
    });
  });
})([Element.prototype, Document.prototype, DocumentFragment.prototype]);

// Source: https://github.com/jserz/js_piece/blob/master/DOM/ParentNode/prepend()/prepend().md
(function (arr) {
  arr.forEach(function (item) {
    if (item.hasOwnProperty('prepend')) {
      return;
    }
    Object.defineProperty(item, 'prepend', {
      configurable: true,
      enumerable: true,
      writable: true,
      value: function prepend() {
        var argArr = Array.prototype.slice.call(arguments),
          docFrag = document.createDocumentFragment();

        argArr.forEach(function (argItem) {
          var isNode = argItem instanceof Node;
          docFrag.appendChild(isNode ? argItem : document.createTextNode(String(argItem)));
        });

        this.insertBefore(docFrag, this.firstChild);
      }
    });
  });
})([Element.prototype, Document.prototype, DocumentFragment.prototype]);

// Source: https://github.com/jserz/js_piece/blob/master/DOM/ChildNode/remove()/remove().md
(function (arr) {
	arr.forEach(function (item) {
		if (item.hasOwnProperty('remove')) {
			return;
		}
		Object.defineProperty(item, 'remove', {
			configurable: true,
			enumerable: true,
			writable: true,
			value: function remove() {
				this.parentNode.removeChild(this);
			}
		});
	});
})([Element.prototype, CharacterData.prototype, DocumentType.prototype]);

(function (ls) {
  if (ls == null) {
    console.log('polyfill.js: window.localStorage does not exist, using in-memory polyfill');

    const lsMock = (() => {
      let _data = {};
    
      return {
        getItem(key) {
          return _data[key] || null;
        },
        setItem(key, value) {
          _data[key] = value.toString();
        },
        removeItem(key) {
          delete _data[key];
        },
        clear() {
          _data = {};
        }
      };
    })();

    Object.defineProperty(window, 'localStorage', { value: lsMock });
  }
})(window.localStorage);
