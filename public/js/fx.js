import surface from './surface';

// NOTE: an effect is defined in its own fx_<name>.js file as:
//
//   export default fx.define({
//     name: 'example',     // used in error messages
//     icon: '<svg …>',     // the icon of its toggle button
//     label: 'mod.example', // the i18n key of its toggle button
//     opaque: false,       // true if it covers every pixel, so that the surface is not cleared under it
//     interactive: false,  // true if it takes keyboard and pointer input while it is active
//     create(host) {       // host is { player, canvas, close }, close() removes the effect
//       return {
//         render(surface, frame) {},        // required, called every frame, frame is { time, dt } in seconds
//         resize(surface) {},               // called after create and whenever the surface is recreated
//         on_row(event) {},                 // the playing row changed, event is { order, pattern, row, notes }
//         on_pattern(event) {},             // the order or pattern changed, called before on_row
//         on_key(event, time) {},           // interactive: keydown and keyup, return true if handled
//         on_pointer(event, x, y, time) {}, // interactive: pointer events on the canvas, x and y are 0..1
//         on_blur() {},                     // interactive: the window lost the focus
//         on_button() {},                   // the toggle button was clicked while active, removes it by default
//         destroy() {},                     // the effect was removed
//       };
//     },
//   });
//
// the hooks that are left out do nothing, time is the time of the input event in seconds, in the time base of
// performance.now(), and frame.time is the time since the effect was created
const MAX_DT = 0.1;
const NON_TEXT_INPUTS = ['range', 'checkbox', 'radio', 'button', 'submit', 'reset', 'image', 'color', 'file'];

function noop() {}

function is_editable(target) {
  if (target == null) {
    return false;
  }
  if (target.tagName === 'INPUT') {
    return NON_TEXT_INPUTS.indexOf((target.type || '').toLowerCase()) === -1;
  }
  return target.isContentEditable || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT';
}

// returns the time of an input event in seconds, in the time base of performance.now()
function event_time(event) {
  const now = performance.now();
  // NOTE: old browsers have timestamps since the epoch
  return (event.timeStamp > 0 && event.timeStamp <= now ? event.timeStamp : now) / 1000;
}

function define(spec) {
  if (typeof spec.create !== 'function') {
    throw new Error('fx: ' + spec.name + ' has no create function');
  }

  return {
    name: spec.name,
    icon: spec.icon || '',
    label: spec.label || spec.name,
    opaque: spec.opaque === true,
    interactive: spec.interactive === true,
    create: (host) => {
      const instance = spec.create(host);
      if (instance == null || typeof instance.render !== 'function') {
        throw new Error('fx: ' + spec.name + ' has no render function');
      }
      return Object.assign({
        resize: noop,
        on_row: noop,
        on_pattern: noop,
        on_key: () => false,
        on_pointer: () => false,
        on_blur: noop,
        on_button: host.close,
        destroy: noop,
      }, instance);
    },
  };
}

// returns the stack of active effects of a canvas, effects is the list of the effects that can be added to it,
// they are drawn in the order of the list
function create_stack(options) {
  const player = options.player;
  const canvas = options.canvas;
  const ctx = options.ctx;
  const effects = options.effects;
  const on_change = options.on_change || noop;
  const num_channels = player.getNumChannels();

  let width = options.width;
  let height = options.height;
  let target = null;
  let layers = [];
  let last_row = null;
  let last_frame = null;
  let listening = false;
  let saved_cursor = '';
  let saved_touch_action = '';

  const find = (def) => {
    for (const layer of layers) {
      if (layer.def === def) {
        return layer;
      }
    }
    return null;
  };

  const interactive = () => layers.some((layer) => layer.def.interactive);

  // calls handler for the interactive layers from the top down, until it returns true
  const dispatch = (handler) => {
    const targets = layers.filter((layer) => layer.def.interactive);
    for (let i = targets.length - 1; i >= 0; i--) {
      // NOTE: a handler may remove layers, e.g. when the game is quit
      if (layers.indexOf(targets[i]) !== -1 && handler(targets[i].instance)) {
        return true;
      }
    }
    return false;
  };

  const on_key = (event) => {
    if (event.ctrlKey || event.altKey || event.metaKey || is_editable(event.target)) {
      return;
    }
    const time = event_time(event);
    if (dispatch((instance) => instance.on_key(event, time))) {
      event.preventDefault();
    }
  };

  // NOTE: key releases are not seen while the window does not have focus
  const on_blur = () => {
    dispatch((instance) => {
      instance.on_blur();
      return false;
    });
  };

  const on_pointer = (event) => {
    const rect = canvas.getBoundingClientRect();
    const x = rect.width > 0 ? (event.clientX - rect.left) / rect.width : 0;
    const y = rect.height > 0 ? (event.clientY - rect.top) / rect.height : 0;
    const time = event_time(event);
    dispatch((instance) => instance.on_pointer(event, x, y, time));
  };

  const focus = () => {
    canvas.focus({ preventScroll: true });
  };

  // NOTE: the input is only taken while there are interactive layers
  const update_input = () => {
    if (interactive() === listening) {
      return;
    }
    listening = !listening;
    if (listening) {
      document.addEventListener('keydown', on_key);
      document.addEventListener('keyup', on_key);
      window.addEventListener('blur', on_blur);
      saved_cursor = canvas.style.cursor;
      saved_touch_action = canvas.style.touchAction;
      // NOTE: touches go to the effects instead of scrolling or zooming the page
      canvas.style.touchAction = 'none';
      canvas.style.cursor = 'default';
    } else {
      document.removeEventListener('keydown', on_key);
      document.removeEventListener('keyup', on_key);
      window.removeEventListener('blur', on_blur);
      canvas.style.cursor = saved_cursor;
      canvas.style.touchAction = saved_touch_action;
    }
  };

  // NOTE: focusable so that the interactive effects can take the keyboard focus, without a focus outline
  canvas.tabIndex = -1;
  canvas.style.outline = 'none';
  canvas.addEventListener('pointerdown', (event) => {
    if (!listening || (event.pointerType === 'mouse' && event.button !== 0)) {
      return;
    }
    // NOTE: also keeps the click from moving the focus, so the canvas is focused by hand
    event.preventDefault();
    focus();
    try {
      // NOTE: so that the release is noticed outside of the canvas too
      canvas.setPointerCapture(event.pointerId);
    } catch (error) {
      // the pointer may already be gone
    }
    on_pointer(event);
  });
  for (const type of ['pointerup', 'pointercancel', 'lostpointercapture']) {
    canvas.addEventListener(type, (event) => {
      if (listening) {
        on_pointer(event);
      }
    });
  }

  const count_notes = (position) => {
    let notes = 0;
    for (let channel = 0; channel < num_channels; channel++) {
      if (player.getPatternRowChannel(position.pattern, position.row, channel).highlight[0] === 'n') {
        notes++;
      }
    }
    return notes;
  };

  const remove_layer = (layer) => {
    const index = layers.indexOf(layer);
    if (index === -1) {
      return;
    }
    layers.splice(index, 1);
    layer.instance.destroy();
    if (layers.length === 0) {
      target = null;
    }
    update_input();
    on_change();
  };

  const push = (def) => {
    const existing = find(def);
    if (existing != null) {
      return existing.instance;
    }
    if (effects.indexOf(def) === -1) {
      throw new Error('fx: ' + def.name + ' is not in the effects of the stack');
    }

    if (layers.length === 0) {
      // NOTE: the first row is not a pattern change
      target = surface.create(width, height);
      last_row = null;
      last_frame = null;
    }
    const layer = { def, instance: null, start: performance.now() / 1000 };
    layer.instance = def.create({
      player,
      canvas,
      close: () => remove_layer(layer),
    });
    layer.instance.resize(target);
    layers.push(layer);
    layers.sort((a, b) => effects.indexOf(a.def) - effects.indexOf(b.def));

    update_input();
    if (def.interactive) {
      focus();
    }
    on_change();
    return layer.instance;
  };

  const remove = (def) => {
    const layer = find(def);
    if (layer != null) {
      remove_layer(layer);
    }
  };

  const clear = () => {
    const removed = layers;
    layers = [];
    target = null;
    for (const layer of removed) {
      layer.instance.destroy();
    }
    update_input();
    on_change();
  };

  const resize = (new_width, new_height) => {
    width = new_width;
    height = new_height;
    if (target != null) {
      target = surface.create(width, height);
      for (const layer of layers) {
        layer.instance.resize(target);
      }
    }
  };

  // passes the changes of the playing position to the layers and draws them on the canvas
  const render = (position) => {
    if (layers.length === 0) {
      return;
    }

    if (last_row == null || position.order !== last_row.order || position.pattern !== last_row.pattern || position.row !== last_row.row) {
      const event = { order: position.order, pattern: position.pattern, row: position.row, notes: count_notes(position) };
      const pattern_changed = last_row != null && (position.order !== last_row.order || position.pattern !== last_row.pattern);
      last_row = position;
      for (const layer of layers.slice()) {
        if (pattern_changed) {
          layer.instance.on_pattern(event);
        }
        layer.instance.on_row(event);
      }
    }

    const now = performance.now() / 1000;
    const dt = last_frame != null ? Math.min(MAX_DT, now - last_frame) : 0;
    last_frame = now;

    // NOTE: the hooks may remove layers, e.g. when an effect closes itself
    if (layers.length > 0 && !layers[0].def.opaque) {
      target.clear(0);
    }
    for (const layer of layers.slice()) {
      if (layers.indexOf(layer) !== -1) {
        layer.instance.render(target, { time: now - layer.start, dt });
      }
    }
    if (target != null) {
      target.blit(ctx, width, height);
    }
  };

  return {
    push,
    remove,
    clear,
    resize,
    render,
    interactive,
    has: (def) => find(def) != null,
    get: (def) => {
      const layer = find(def);
      return layer != null ? layer.instance : null;
    },
    empty: () => layers.length === 0,
  };
}

const fx = {
  define,
  create_stack,
};

export default fx;
