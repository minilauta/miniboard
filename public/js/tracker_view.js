import t from './i18n';
import fx from './fx';
import fx_plasma from './fx_plasma';
import fx_dycp from './fx_dycp';
import fx_minimania from './fx_minimania';

const COLOR_BACKGROUND = '#000000';
const COLOR_BEAT_ROW = '#1c1c1c';
const COLOR_PLAY_ROW = '#504080';
const COLOR_DIVIDER = '#585858';
const COLOR_ROW_NUMBER = '#ffffff';
const COLOR_BEAT_ROW_NUMBER = '#ffff00';
const COLOR_EMPTY = '#606060';
const COLOR_CELL = {
  n: '#ffffff', // note
  m: '#ffffff', // note off/cut/fade
  i: '#80e0ff', // instrument
  u: '#80ff80', // volume command
  v: '#80ff80', // volume
  e: '#ff80e0', // effect
  f: '#ffe080', // effect parameter
};
const ROWS_PER_BEAT = 4;
const DIVIDER_WIDTH = 2;
const FONT_FAMILY = '"DejaVu Sans Mono", Consolas, Menlo, monospace';
const FONT_SIZE_MIN = 12;
const FONT_SIZE_MAX = 24;
const LINE_HEIGHT = 1.15;
const PANEL_FONT_SIZE = 12;
const PANEL_SCROLLBAR_WIDTH = 17;
const BUTTON_SIZE = 24;
const BUTTON_OPACITY = '0.5';
const FULLSCREEN_ICON_SVG = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" style="display: block"><path d=":path"/></svg>';
const FULLSCREEN_ICON_ENTER = FULLSCREEN_ICON_SVG.replace(':path', 'M1 5V1h4M11 1h4v4M15 11v4h-4M5 15H1v-4');
const FULLSCREEN_ICON_EXIT = FULLSCREEN_ICON_SVG.replace(':path', 'M5 1v4H1M15 5h-4V1M11 15v-4h4M1 11h4v4');
const CONTROL_ICON_SVG = '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" style="display: block"><path d=":path"/></svg>';
const CONTROL_ICON_PREVIOUS = CONTROL_ICON_SVG.replace(':path', 'M2 2h2v12H2zM14 2v12L5 8z');
const CONTROL_ICON_PLAY = CONTROL_ICON_SVG.replace(':path', 'M4 2v12l10-6z');
const CONTROL_ICON_PAUSE = CONTROL_ICON_SVG.replace(':path', 'M3 2h4v12H3zM9 2h4v12H9z');
const CONTROL_ICON_STOP = CONTROL_ICON_SVG.replace(':path', 'M3 3h10v10H3z');
const CONTROL_ICON_NEXT = CONTROL_ICON_SVG.replace(':path', 'M12 2h2v12h-2zM2 2v12l9-6z');
const BAR_PADDING = 4;
const PROGRESS_HEIGHT = 8;
const PLAYHEAD_WIDTH = 2;
const SEEK_KEYS = { ArrowLeft: -5, Left: -5, ArrowDown: -5, Down: -5, ArrowRight: 5, Right: 5, ArrowUp: 5, Up: 5, PageDown: -30, PageUp: 30 };
const FX = [fx_plasma, fx_dycp, fx_minimania];
const FX_CLICK = [fx_plasma, fx_dycp];
const CELL_LAYOUTS = [
  [[0, 13]],         // note, instrument, volume, effect
  [[0, 6], [9, 13]], // note, instrument, effect
  [[0, 6]],          // note, instrument
  [[0, 3]],          // note
];

function cell_chars(layout) {
  return layout.reduce((sum, range) => sum + range[1] - range[0], 0);
}

function fit_layout(width, char_ratio, row_digits, num_channels) {
  for (const layout of CELL_LAYOUTS) {
    const chars = row_digits + 1 + num_channels * (cell_chars(layout) + 1);
    const font_size = Math.min(FONT_SIZE_MAX, width / (chars * char_ratio));
    if (font_size >= FONT_SIZE_MIN) {
      return { layout, font_size, num_channels };
    }
  }

  const layout = CELL_LAYOUTS[CELL_LAYOUTS.length - 1];
  const chars = width / (FONT_SIZE_MIN * char_ratio);
  return {
    layout,
    font_size: FONT_SIZE_MIN,
    num_channels: Math.max(1, Math.floor((chars - row_digits - 1) / (cell_chars(layout) + 1))),
  };
}

function create_instrument_panel(names, ctx, height) {
  if (names.every((name) => name.trim() === '')) {
    return null;
  }

  const font = PANEL_FONT_SIZE + 'px ' + FONT_FAMILY;
  const line_height = Math.ceil(PANEL_FONT_SIZE * LINE_HEIGHT);
  const number_digits = Math.max(2, names.length.toString(16).length);

  ctx.save();
  ctx.font = font;
  const char_width = ctx.measureText('0').width;
  const names_width = names.reduce((max, name) => Math.max(max, ctx.measureText(name).width), 0);
  ctx.restore();

  const padding = Math.round(char_width / 2);
  const scrollbar_width = names.length * line_height > height ? PANEL_SCROLLBAR_WIDTH : 0;
  const width = Math.ceil(DIVIDER_WIDTH + 2 * padding + (number_digits + 1) * char_width + names_width + scrollbar_width);

  const panel = document.createElement('div');
  panel.style.boxSizing = 'border-box';
  panel.style.flex = '1 0 auto';
  panel.style.width = width + 'px';
  panel.style.maxHeight = height + 'px';
  panel.style.overflowX = 'hidden';
  panel.style.overflowY = 'auto';
  panel.style.scrollbarWidth = 'thin';
  panel.style.scrollbarColor = COLOR_DIVIDER + ' ' + COLOR_BACKGROUND;
  panel.style.padding = '0 ' + padding + 'px';
  panel.style.borderLeft = DIVIDER_WIDTH + 'px solid ' + COLOR_DIVIDER;
  panel.style.background = COLOR_BACKGROUND;
  panel.style.color = COLOR_ROW_NUMBER;
  panel.style.font = font;
  panel.style.lineHeight = line_height + 'px';
  panel.style.textAlign = 'left';
  panel.style.whiteSpace = 'pre';

  names.forEach((name, i) => {
    const number = document.createElement('span');
    number.style.color = COLOR_CELL.i;
    number.textContent = (i + 1).toString(16).toUpperCase().padStart(number_digits, '0');
    const line = document.createElement('div');
    line.appendChild(number);
    line.appendChild(document.createTextNode(' ' + name));
    panel.appendChild(line);
  });

  return { element: panel, width };
}

function format_time(seconds) {
  const total = Math.max(0, Math.floor(seconds) || 0);
  return Math.floor(total / 60) + ':' + (total % 60).toString().padStart(2, '0');
}

function create_control_bar(controls) {
  const bar = document.createElement('div');
  bar.style.display = 'flex';
  bar.style.flex = 'none';
  bar.style.alignItems = 'center';
  bar.style.gap = BAR_PADDING + 'px';
  bar.style.paddingRight = BAR_PADDING + 'px';
  bar.style.boxSizing = 'border-box';
  bar.style.background = COLOR_BACKGROUND;
  bar.style.color = COLOR_ROW_NUMBER;
  bar.style.font = PANEL_FONT_SIZE + 'px ' + FONT_FAMILY;
  bar.style.lineHeight = Math.ceil(PANEL_FONT_SIZE * LINE_HEIGHT) + 'px';
  bar.style.textAlign = 'left';
  bar.style.borderTop = DIVIDER_WIDTH + 'px solid ' + COLOR_DIVIDER;
  for (const element of controls) {
    bar.appendChild(element);
  }
  return bar;
}

function set_button_icon(button, icon, label) {
  button.innerHTML = icon;
  button.title = label;
  button.setAttribute('aria-label', label);
}

function create_button(icon, label) {
  const button = document.createElement('button');
  button.type = 'button';
  set_button_icon(button, icon, label);
  button.style.flex = 'none';
  button.style.display = 'flex';
  button.style.alignItems = 'center';
  button.style.justifyContent = 'center';
  button.style.width = BUTTON_SIZE + 'px';
  button.style.height = BUTTON_SIZE + 'px';
  button.style.margin = '0';
  button.style.padding = '0';
  button.style.border = 'none';
  button.style.background = COLOR_BACKGROUND;
  button.style.color = COLOR_ROW_NUMBER;
  button.style.cursor = 'pointer';
  button.style.opacity = BUTTON_OPACITY;
  button.addEventListener('mouseenter', () => {
    button.style.opacity = '1';
  });
  button.addEventListener('mouseleave', () => {
    button.style.opacity = BUTTON_OPACITY;
  });
  return button;
}

function create_overlay_button(icon, label, right) {
  const button = create_button(icon, label);
  button.setAttribute('aria-pressed', 'false');
  button.style.position = 'absolute';
  button.style.top = '0';
  button.style.right = right + 'px';
  return button;
}

function create(player, base_width, base_height) {
  const node = player.currentPlayingNode;
  const names = player.getInstrumentNames();
  const num_channels = player.getNumChannels();
  const num_patterns = player.getTotalPatterns();
  const num_orders = player.getTotalOrder();
  const duration = player.duration();
  const duration_text = format_time(duration);
  let max_rows = 1;
  for (let pattern = 0; pattern < num_patterns; pattern++) {
    max_rows = Math.max(max_rows, player.getPatternNumRows(pattern));
  }
  const row_digits = Math.max(2, (max_rows - 1).toString(16).length);

  const canvas = document.createElement('canvas');
  canvas.style.display = 'block';
  canvas.style.width = '100%';
  canvas.style.height = 'auto';
  const ctx = canvas.getContext('2d');

  const canvas_frame = document.createElement('div');
  canvas_frame.style.position = 'relative';
  canvas_frame.style.maxWidth = '100%';
  canvas_frame.style.alignSelf = 'flex-start';
  canvas_frame.appendChild(canvas);

  let width = 0;
  let height = 0;
  let fit = null;
  let char_width = 0;
  let line_height = 0;
  let dot_size = 0;
  let channel_chars = 0;
  let left = 0;
  let play_y = 0;
  const channel_x = (channel) => left + (row_digits + 1 + channel * channel_chars) * char_width;

  const layout = (new_width, new_height) => {
    width = new_width;
    height = new_height;
    const dpr = window.devicePixelRatio || 1;
    // NOTE: resizing the canvas also resets the context state
    canvas.width = Math.round(width * dpr);
    canvas.height = Math.round(height * dpr);
    canvas_frame.style.width = width + 'px';

    ctx.scale(dpr, dpr);
    ctx.font = '100px ' + FONT_FAMILY;
    fit = fit_layout(width, ctx.measureText('0').width / 100, row_digits, num_channels);
    ctx.font = fit.font_size + 'px ' + FONT_FAMILY;
    ctx.textBaseline = 'middle';

    char_width = ctx.measureText('0').width;
    line_height = Math.ceil(fit.font_size * LINE_HEIGHT);
    dot_size = Math.max(1, Math.round(fit.font_size / 8));
    channel_chars = cell_chars(fit.layout) + 1;
    const content_width = (row_digits + 1 + fit.num_channels * channel_chars) * char_width;
    left = Math.floor((width - content_width) / 2);
    // NOTE: the playing row stays in the middle and the pattern scrolls past it
    play_y = Math.floor((height - line_height) / 2);
  };
  layout(base_width, base_height);

  const draw_cell_text = (text, highlight, x, y) => {
    let start = 0;
    for (let i = 1; i <= text.length; i++) {
      if (i < text.length && highlight[i] === highlight[start]) {
        continue;
      }
      const type = highlight[start];
      if (type === '.') {
        ctx.fillStyle = COLOR_EMPTY;
        for (let j = start; j < i; j++) {
          const dot_x = Math.round(x + (j + 0.5) * char_width - dot_size / 2);
          ctx.fillRect(dot_x, Math.round(y - dot_size / 2), dot_size, dot_size);
        }
      } else if (type !== ' ') {
        ctx.fillStyle = COLOR_CELL[type] || COLOR_CELL.n;
        ctx.fillText(text.slice(start, i), x + start * char_width, y);
      }
      start = i;
    }
  };

  const draw = (position) => {
    const num_rows = player.getPatternNumRows(position.pattern);
    const row_first = Math.max(0, position.row - Math.ceil(play_y / line_height));
    const row_last = Math.min(num_rows - 1, position.row + Math.ceil((height - play_y) / line_height));
    const row_y = (row) => play_y + (row - position.row) * line_height;

    ctx.fillStyle = COLOR_BACKGROUND;
    ctx.fillRect(0, 0, width, height);

    for (let row = row_first; row <= row_last; row++) {
      if (row === position.row || row % ROWS_PER_BEAT === 0) {
        ctx.fillStyle = row === position.row ? COLOR_PLAY_ROW : COLOR_BEAT_ROW;
        ctx.fillRect(0, row_y(row), width, line_height);
      }
    }

    ctx.fillStyle = COLOR_DIVIDER;
    for (let channel = 0; channel <= fit.num_channels; channel++) {
      ctx.fillRect(Math.round(channel_x(channel) - (char_width + DIVIDER_WIDTH) / 2), 0, DIVIDER_WIDTH, height);
    }

    for (let row = row_first; row <= row_last; row++) {
      const y = row_y(row) + line_height / 2;
      ctx.fillStyle = row % ROWS_PER_BEAT === 0 ? COLOR_BEAT_ROW_NUMBER : COLOR_ROW_NUMBER;
      ctx.fillText(row.toString(16).toUpperCase().padStart(row_digits, '0'), left, y);

      for (let channel = 0; channel < fit.num_channels; channel++) {
        const cell = player.getPatternRowChannel(position.pattern, row, channel);
        let x = channel_x(channel);
        for (const range of fit.layout) {
          draw_cell_text(cell.text.slice(range[0], range[1]), cell.highlight.slice(range[0], range[1]), x, y);
          x += (range[1] - range[0]) * char_width;
        }
      }
    }
  };

  let started = false;
  let stopped = false;
  let drawn = null;

  // NOTE: the buttons are in the order of the effects, to the left of the fullscreen button
  const buttons = FX.map((def, i) => {
    const button = create_overlay_button(def.icon, t(def.label), (FX.length - 1 - i + (document.fullscreenEnabled ? 1 : 0)) * BUTTON_SIZE);
    button.addEventListener('click', () => {
      if (stopped) {
        return;
      }
      if (stack.has(def)) {
        stack.get(def).on_button();
      } else {
        stack.push(def);
      }
    });
    canvas_frame.appendChild(button);
    return button;
  });

  const stack = fx.create_stack({
    player,
    canvas,
    ctx,
    effects: FX,
    width,
    height,
    on_change: () => {
      drawn = null;
      FX.forEach((def, i) => {
        buttons[i].setAttribute('aria-pressed', stack.has(def) ? 'true' : 'false');
      });
    },
  });

  canvas.style.cursor = 'pointer';
  canvas.addEventListener('click', () => {
    // NOTE: clicks are input for the interactive effects, e.g. they play the lanes of the game
    if (stopped || stack.interactive()) {
      return;
    }
    if (stack.empty()) {
      FX_CLICK.forEach((def) => stack.push(def));
    } else {
      stack.clear();
    }
  });

  const resize = (new_width, new_height) => {
    layout(new_width, new_height);
    drawn = null;
    stack.resize(width, height);
  };

  // NOTE: the effects are removed when the song stops, so that they do not keep listening to the input
  const stop = () => {
    stopped = true;
    stack.clear();
  };

  const active = () => !stopped && player.currentPlayingNode === node && node.modulePtr !== 0;

  const find_order = (from, step) => {
    for (let order = from + step; order >= 0 && order < num_orders; order += step) {
      if (player.getOrderPattern(order) < num_patterns) {
        return order;
      }
    }
    return -1;
  };

  const jump = (step) => {
    if (!active()) {
      return;
    }
    const order = find_order(player.getCurrentOrder(), step);
    if (order !== -1) {
      player.setPositionOrderRow(order, 0);
    }
  };

  const previous_button = create_button(CONTROL_ICON_PREVIOUS, t('mod.previous'));
  previous_button.addEventListener('click', () => jump(-1));

  const play_button = create_button(CONTROL_ICON_PAUSE, t('mod.pause'));
  let shown_paused = false;
  const show_paused = () => {
    const paused = player.isPaused();
    if (paused !== shown_paused) {
      shown_paused = paused;
      set_button_icon(play_button, paused ? CONTROL_ICON_PLAY : CONTROL_ICON_PAUSE, t(paused ? 'mod.play' : 'mod.pause'));
    }
  };
  play_button.addEventListener('click', () => {
    if (active()) {
      player.togglePause();
      show_paused();
    }
  });

  const stop_button = create_button(CONTROL_ICON_STOP, t('mod.stop'));
  stop_button.addEventListener('click', () => {
    if (active()) {
      player.pause();
      player.seek(0);
      show_paused();
    }
  });

  const next_button = create_button(CONTROL_ICON_NEXT, t('mod.next'));
  next_button.addEventListener('click', () => jump(1));

  const fill = document.createElement('div');
  fill.style.position = 'absolute';
  fill.style.top = '0';
  fill.style.bottom = '0';
  fill.style.left = '0';
  fill.style.boxSizing = 'border-box';
  fill.style.width = '0';
  fill.style.minWidth = PLAYHEAD_WIDTH + 'px';
  fill.style.background = COLOR_PLAY_ROW;
  fill.style.borderRight = PLAYHEAD_WIDTH + 'px solid ' + COLOR_ROW_NUMBER;
  const track = document.createElement('div');
  track.style.position = 'relative';
  track.style.flex = '1 1 auto';
  track.style.boxSizing = 'border-box';
  track.style.height = PROGRESS_HEIGHT + 'px';
  track.style.overflow = 'hidden';
  track.style.border = '1px solid ' + COLOR_DIVIDER;
  track.style.background = COLOR_BEAT_ROW;
  track.appendChild(fill);

  const progress = document.createElement('div');
  progress.tabIndex = 0;
  progress.setAttribute('role', 'slider');
  progress.setAttribute('aria-label', t('mod.position'));
  progress.setAttribute('aria-valuemin', '0');
  progress.setAttribute('aria-valuemax', Math.floor(duration).toString());
  progress.style.display = 'flex';
  progress.style.flex = '1 1 auto';
  progress.style.alignItems = 'center';
  progress.style.minWidth = 2 * BUTTON_SIZE + 'px';
  progress.style.height = BUTTON_SIZE + 'px';
  progress.style.cursor = 'pointer';
  progress.style.touchAction = 'pan-y';
  progress.appendChild(track);

  const time = document.createElement('span');
  time.style.flex = 'none';
  time.style.whiteSpace = 'pre';

  let seek_pointer = null;
  let seek_time = 0;
  let shown_width = null;
  let shown_seconds = null;
  const show_progress = () => {
    const current = seek_pointer != null ? seek_time : player.getCurrentTime();
    const fraction = duration > 0 ? Math.max(0, Math.min(1, current / duration)) : 0;
    const width = (fraction * 100).toFixed(2) + '%';
    if (width !== shown_width) {
      shown_width = width;
      fill.style.width = width;
    }
    const seconds = Math.floor(current);
    if (seconds !== shown_seconds) {
      shown_seconds = seconds;
      const text = format_time(current);
      time.textContent = text.padStart(duration_text.length) + ' / ' + duration_text;
      progress.setAttribute('aria-valuenow', Math.max(0, seconds || 0).toString());
      progress.setAttribute('aria-valuetext', text + ' / ' + duration_text);
    }
  };

  const pointer_time = (event) => {
    const rect = track.getBoundingClientRect();
    const x = rect.width > 0 ? (event.clientX - rect.left) / rect.width : 0;
    return Math.max(0, Math.min(1, x)) * duration;
  };
  progress.addEventListener('pointerdown', (event) => {
    if (!active() || !(duration > 0) || seek_pointer != null || (event.pointerType === 'mouse' && event.button !== 0)) {
      return;
    }
    event.preventDefault();
    try {
      progress.setPointerCapture(event.pointerId);
    } catch (error) {
      // the pointer may already be gone
    }
    seek_pointer = event.pointerId;
    seek_time = pointer_time(event);
    show_progress();
  });
  progress.addEventListener('pointermove', (event) => {
    if (event.pointerId === seek_pointer) {
      seek_time = pointer_time(event);
      show_progress();
    }
  });
  progress.addEventListener('pointerup', (event) => {
    if (event.pointerId === seek_pointer) {
      seek_pointer = null;
      if (active()) {
        player.seek(pointer_time(event));
      }
    }
  });
  for (const type of ['pointercancel', 'lostpointercapture']) {
    progress.addEventListener(type, (event) => {
      if (event.pointerId === seek_pointer) {
        seek_pointer = null;
      }
    });
  }
  progress.addEventListener('keydown', (event) => {
    if (!active() || !(duration > 0) || event.ctrlKey || event.altKey || event.metaKey) {
      return;
    }
    let target = null;
    if (event.key === 'Home') {
      target = 0;
    } else if (event.key === 'End') {
      target = duration;
    } else if (Object.prototype.hasOwnProperty.call(SEEK_KEYS, event.key)) {
      target = player.getCurrentTime() + SEEK_KEYS[event.key];
    } else {
      return;
    }
    event.preventDefault();
    event.stopPropagation();
    player.seek(Math.max(0, Math.min(duration, target)));
  });

  const controls = create_control_bar([previous_button, play_button, stop_button, next_button, progress, time]);

  const update = () => {
    if (started && (!canvas.isConnected || player.currentPlayingNode !== node)) {
      stop();
      return;
    }
    started = true;

    const position = player.getPlayingPosition();
    if (position == null) {
      stop();
      return;
    }

    if (!stack.empty()) {
      stack.render(position);
    } else if (drawn == null || position.pattern !== drawn.pattern || position.row !== drawn.row) {
      draw(position);
      drawn = position;
    }
    show_progress();
    show_paused();

    window.requestAnimationFrame(update);
  };

  const body = document.createElement('div');
  body.style.display = 'flex';
  body.style.flexWrap = 'wrap';
  body.appendChild(canvas_frame);
  const panel = create_instrument_panel(names, ctx, base_height);
  if (panel != null) {
    body.appendChild(panel.element);
  }

  const view = document.createElement('div');
  view.style.display = 'flex';
  view.style.flexDirection = 'column';
  view.style.maxWidth = '100%';
  view.style.width = (base_width + (panel != null ? panel.width : 0)) + 'px';
  view.appendChild(body);
  view.appendChild(controls);

  if (document.fullscreenEnabled) {
    const button = create_overlay_button(FULLSCREEN_ICON_ENTER, t('mod.fullscreen'), 0);
    button.addEventListener('click', () => {
      if (document.fullscreenElement === view) {
        document.exitFullscreen();
      } else {
        view.requestFullscreen().catch((error) => {
          console.error('tracker_view: failed to enter fullscreen:', error);
        });
      }
    });
    canvas_frame.appendChild(button);

    const fit_screen = () => {
      if (document.fullscreenElement !== view) {
        return;
      }
      const panel_width = panel != null && view.clientWidth - panel.width >= base_width ? panel.width : 0;
      const body_height = Math.max(1, view.clientHeight - controls.offsetHeight);
      if (panel != null) {
        panel.element.style.display = panel_width > 0 ? '' : 'none';
        panel.element.style.maxHeight = body_height + 'px';
      }
      resize(view.clientWidth - panel_width, body_height);
    };

    view.addEventListener('fullscreenchange', () => {
      const fullscreen = document.fullscreenElement === view;
      button.innerHTML = fullscreen ? FULLSCREEN_ICON_EXIT : FULLSCREEN_ICON_ENTER;
      button.setAttribute('aria-pressed', fullscreen ? 'true' : 'false');
      body.style.flexWrap = fullscreen ? 'nowrap' : 'wrap';
      // NOTE: the pattern takes the height that is left above the control bar
      body.style.flex = fullscreen ? '1 1 0' : '';
      body.style.minHeight = fullscreen ? '0' : '';
      body.style.overflow = fullscreen ? 'hidden' : '';
      view.style.background = fullscreen ? COLOR_BACKGROUND : '';
      if (panel != null) {
        panel.element.style.display = '';
        panel.element.style.maxHeight = base_height + 'px';
      }
      if (fullscreen) {
        window.addEventListener('resize', fit_screen);
        fit_screen();
      } else {
        window.removeEventListener('resize', fit_screen);
        resize(base_width, base_height);
      }
    });
  }

  update();

  return view;
}

const tracker_view = {
  create,
};

export default tracker_view;
