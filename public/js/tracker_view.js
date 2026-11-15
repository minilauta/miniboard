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
// NOTE: the effects are drawn on top of each other in this order, each one has a toggle button next to the
//       fullscreen button, new effects are added by listing their fx_<name>.js here
const FX = [fx_plasma, fx_dycp, fx_minimania];
// NOTE: the effects that clicking the pattern toggles
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
  // NOTE: the row number and each channel are followed by a divider column
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
  // NOTE: grows to the full width when it wraps below the pattern on narrow screens
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
  // NOTE: keeps the spacing of text that is laid out across multiple names
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

// NOTE: placed over the top right corner of the pattern, so that it is also visible in fullscreen
function create_button(icon, label, right) {
  const button = document.createElement('button');
  button.type = 'button';
  button.title = label;
  button.setAttribute('aria-label', label);
  button.setAttribute('aria-pressed', 'false');
  button.innerHTML = icon;
  button.style.position = 'absolute';
  button.style.top = '0';
  button.style.right = right + 'px';
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

function create(player, base_width, base_height) {
  const node = player.currentPlayingNode;
  const names = player.getInstrumentNames();
  const num_channels = player.getNumChannels();
  const num_patterns = player.getTotalPatterns();
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

  // NOTE: shrinks to fit screens narrower than its width, the canvas keeps its aspect ratio
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
    const button = create_button(def.icon, t(def.label), (FX.length - 1 - i + (document.fullscreenEnabled ? 1 : 0)) * BUTTON_SIZE);
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

    window.requestAnimationFrame(update);
  };
  update();

  // NOTE: the instrument panel is placed on the right side of the pattern
  const view = document.createElement('div');
  view.style.display = 'flex';
  view.style.flexWrap = 'wrap';
  view.style.maxWidth = '100%';
  view.appendChild(canvas_frame);
  const panel = create_instrument_panel(names, ctx, base_height);
  if (panel != null) {
    view.appendChild(panel.element);
  }
  view.style.width = (base_width + (panel != null ? panel.width : 0)) + 'px';

  if (document.fullscreenEnabled) {
    const button = create_button(FULLSCREEN_ICON_ENTER, t('mod.fullscreen'), 0);
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

    // NOTE: the pattern fills the screen, the instrument panel is only kept if the pattern still fits next to it
    const fit_screen = () => {
      if (document.fullscreenElement !== view) {
        return;
      }
      const panel_width = panel != null && view.clientWidth - panel.width >= base_width ? panel.width : 0;
      if (panel != null) {
        panel.element.style.display = panel_width > 0 ? '' : 'none';
      }
      resize(view.clientWidth - panel_width, view.clientHeight);
    };

    view.addEventListener('fullscreenchange', () => {
      const fullscreen = document.fullscreenElement === view;
      button.innerHTML = fullscreen ? FULLSCREEN_ICON_EXIT : FULLSCREEN_ICON_ENTER;
      button.setAttribute('aria-pressed', fullscreen ? 'true' : 'false');
      view.style.flexWrap = fullscreen ? 'nowrap' : 'wrap';
      view.style.background = fullscreen ? COLOR_BACKGROUND : '';
      if (panel != null) {
        panel.element.style.display = '';
        panel.element.style.maxHeight = fullscreen ? '100%' : base_height + 'px';
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

  return view;
}

const tracker_view = {
  create,
};

export default tracker_view;
