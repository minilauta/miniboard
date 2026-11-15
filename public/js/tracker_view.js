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

function create(player, width, height) {
  const node = player.currentPlayingNode;
  const num_patterns = player.getTotalPatterns();
  let max_rows = 1;
  for (let pattern = 0; pattern < num_patterns; pattern++) {
    max_rows = Math.max(max_rows, player.getPatternNumRows(pattern));
  }
  const row_digits = Math.max(2, (max_rows - 1).toString(16).length);

  const canvas = document.createElement('canvas');
  const dpr = window.devicePixelRatio || 1;
  canvas.width = Math.round(width * dpr);
  canvas.height = Math.round(height * dpr);
  canvas.style.display = 'block';
  // NOTE: shrinks to fit screens narrower than its width, keeping its aspect ratio
  canvas.style.width = width + 'px';
  canvas.style.maxWidth = '100%';
  canvas.style.height = 'auto';

  const ctx = canvas.getContext('2d');
  ctx.scale(dpr, dpr);
  ctx.font = '100px ' + FONT_FAMILY;
  const fit = fit_layout(width, ctx.measureText('0').width / 100, row_digits, player.getNumChannels());
  ctx.font = fit.font_size + 'px ' + FONT_FAMILY;
  ctx.textBaseline = 'middle';

  const char_width = ctx.measureText('0').width;
  const line_height = Math.ceil(fit.font_size * LINE_HEIGHT);
  const dot_size = Math.max(1, Math.round(fit.font_size / 8));
  const channel_chars = cell_chars(fit.layout) + 1;
  const content_width = (row_digits + 1 + fit.num_channels * channel_chars) * char_width;
  const left = Math.floor((width - content_width) / 2);
  const channel_x = (channel) => left + (row_digits + 1 + channel * channel_chars) * char_width;
  // NOTE: the playing row stays in the middle and the pattern scrolls past it
  const play_y = Math.floor((height - line_height) / 2);

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

  let drawn = null;
  const update = () => {
    if (drawn != null && (!canvas.isConnected || player.currentPlayingNode !== node)) {
      return;
    }

    const position = player.getPlayingPosition();
    if (position == null) {
      return;
    }

    if (drawn == null || position.pattern !== drawn.pattern || position.row !== drawn.row) {
      draw(position);
      drawn = position;
    }

    window.requestAnimationFrame(update);
  };
  update();

  return canvas;
}

const tracker_view = {
  create,
};

export default tracker_view;
