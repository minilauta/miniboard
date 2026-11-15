import fx from './fx';

const ICON = '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" style="display: block"><path d="M1 8l4-4v8zM15 8l-4-4v8zM8 1l4 4H4zM8 15l-4-4h8z"/></svg>';
const KEY_CODES = { ArrowLeft: 0, KeyZ: 0, ArrowDown: 1, KeyX: 1, ArrowUp: 2, Comma: 2, ArrowRight: 3, Period: 3 };
const KEYS = { ArrowLeft: 0, Left: 0, z: 0, Z: 0, ArrowDown: 1, Down: 1, x: 1, X: 1, ArrowUp: 2, Up: 2, ',': 2, ArrowRight: 3, Right: 3, '.': 3 };
const DIFFICULTY_KEYS = '123456789';
// NOTE: indices of the OPENMPT_MODULE_COMMAND_* values of a pattern cell
const COMMAND_NOTE = 0;
const COMMAND_INSTRUMENT = 1;
const COMMAND_EFFECT = 3;
const COMMAND_PARAMETER = 5;
// NOTE: OpenMPT's internal effect numbers
const EFFECT_POSITION_JUMP = 12;
const EFFECT_PATTERN_BREAK = 14;
const EFFECT_SPEED = 16;
const EFFECT_TEMPO = 17;
const EFFECT_MOD_EXTENDED = 19;
const EFFECT_S3M_EXTENDED = 20;
const TEMPO_MIN = 32;
const NOTE_MIN = 1;
const NOTE_MAX = 120;
const ORDER_END = 255;

const LANES = 4;
const ROWS_PER_BEAT = 4;
const DIFFICULTIES = [
  { name: 'EASY', notes: [1, 0, 0], gap: 0.4, rate: 1.5, burst: 2, jump_grid: 0, scroll: 1.8 },
  { name: 'NORMAL', notes: [1, 2, 0], gap: 0.2, rate: 3, burst: 3, jump_grid: 32, scroll: 1.5 },
  { name: 'HARD', notes: [1, 1, 2], gap: 0.11, rate: 5, burst: 4, jump_grid: 16, scroll: 1.2 },
  { name: 'EXPERT', notes: [1, 1, 1], gap: 0.07, rate: 8, burst: 6, jump_grid: 8, scroll: 1.0 },
  { name: 'MASTER', notes: [1, 1, 1], gap: 0.05, rate: 12, burst: 8, jump_grid: 8, scroll: 0.8 },
];
// NOTE: tolerates the rounding of the measured row times
const GAP_TOLERANCE = 0.01;
const JUMP_NOTES = 3;
// NOTE: two steps on the same lane need to be at least this many seconds apart
const JACK_GAP = 0.75;
const JUDGMENTS = [
  { name: 'PERFECT', window: 0.045, points: 3, combo: true, colors: [0xffffff, 0xffffff, 0x6abfc6, 0x6abfc6, 0x887ecb], fill: [0xffffff, 0x6abfc6, 0x887ecb] },
  { name: 'GREAT', window: 0.09, points: 2, combo: true, colors: [0xffffff, 0x9ae29b, 0x9ae29b, 0x5cab5e], fill: [0xffffff, 0x9ae29b, 0x5cab5e] },
  { name: 'GOOD', window: 0.135, points: 1, combo: true, colors: [0xffffff, 0xc9d487, 0xc9d487, 0xa1683c], fill: [0xffffff, 0xc9d487, 0xa1683c] },
  { name: 'BAD', window: 0.18, points: 0, combo: false, colors: [0xadadad, 0x898989, 0x898989, 0x626262], fill: [0xadadad, 0x898989, 0x626262] },
];
const MISS = { name: 'MISS', points: 0, combo: false, colors: [0xcb7e75, 0x9f4e44, 0x9f4e44, 0x6d5412] };
const MISS_WINDOW = JUDGMENTS[JUDGMENTS.length - 1].window;
const MAX_POINTS = JUDGMENTS[0].points;

// NOTE: the length of a tick in classic tempo mode is 2.5 / tempo seconds
const TICK_TEMPO = 2.5;
const SPEED_DEFAULT = 6;
const TEMPO_DEFAULT = 125;
const ROW_HISTORY = 16;
const ROWS_MEASURED = 4;
// NOTE: rows that may have been played between two reads of the playing position
const ROW_SEARCH = 8;
// NOTE: seconds without new rows after which the chart starts over, e.g. after the tab was hidden
const RESYNC_GAP = 3;
const LEAD_MARGIN = 0.25;
const KEEP_PAST = 1;
const MAX_ROWS = 4096;
const CLOCK_SNAP = 0.05;
const CLOCK_SMOOTHING = 0.05;

const LANE_SIZE = 0.12;
const FIELD_WIDTH = 0.9;
const ARROW_PADDING = 2;
const ARROW_SIZE_MIN = 6;
const RECEPTOR_Y = 0.08;
const JUDGMENT_Y = 0.42;
const FONT_LARGE = 1 / 12;
const FONT_SMALL = 1 / 20;
const FONT_MIN = 8;
const COMBO_MIN = 4;
const READY_TIME = 1.5;
const JUDGMENT_TIME = 0.6;
const JUDGMENT_BOUNCE = 0.08;
const FLASH_TIME = 0.15;
const FLASH_SPREAD = 0.3;

const NOTE_OUTLINE = 0x000000;
const NOTE_FILLS = [
  [0xcb7e75, 0x9f4e44, 0x6d5412], // quarter notes, red
  [0x6abfc6, 0x887ecb, 0x50459b], // eighth notes, blue
  [0xffffff, 0xc9d487, 0xa1683c], // sixteenth notes, yellow
];
const MISSED_FILL = [0x898989, 0x626262, 0x626262];
const RECEPTOR_IDLE = 0x626262;
const RECEPTOR_BEAT = 0xadadad;
const RECEPTOR_PRESSED = 0xffffff;
const RECEPTOR_PRESSED_FILL = [0x898989, 0x626262, 0x626262];
const RECEPTOR_FLASH = 0xffffff;
const HUD_COLORS = [0xffffff, 0xffffff, 0xc9d487, 0xc9d487, 0xcb7e75];

// returns the pixels of an arrow for each lane as [x, y, kind, band, ...],
// kind is 1 for the outline and 2 for the fill, band splits the fill in three from top to bottom
function arrow_sprites(size) {
  const up = new Uint8Array(size * size);
  const center = (size - 1) / 2;
  const head = Math.max(2, Math.round(size * 0.55));
  const shaft = size * 0.2;
  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      const dx = Math.abs(x - center);
      up[y * size + x] = (y < head ? dx <= (y + 1.5) / head * size / 2 - 0.5 : dx <= shaft) ? 1 : 0;
    }
  }

  const inside = (x, y) => x >= 0 && x < size && y >= 0 && y < size && up[y * size + x] === 1;
  // NOTE: maps the pixels of the up arrow to the other directions
  const transforms = [
    (x, y) => [y, x],
    (x, y) => [x, size - 1 - y],
    (x, y) => [x, y],
    (x, y) => [size - 1 - y, x],
  ];

  return transforms.map((transform) => {
    const sprite = [];
    for (let y = 0; y < size; y++) {
      for (let x = 0; x < size; x++) {
        if (!inside(x, y)) {
          continue;
        }
        const edge = !inside(x - 1, y) || !inside(x + 1, y) || !inside(x, y - 1) || !inside(x, y + 1);
        const p = transform(x, y);
        sprite.push(p[0], p[1], edge ? 1 : 2, Math.min(2, Math.floor(p[1] * 3 / size)));
      }
    }
    return sprite;
  });
}

// NOTE: fill is null for a hollow arrow
function draw_sprite(surface, sprite, x, y, outline, fill) {
  for (let i = 0; i < sprite.length; i += 4) {
    const c = sprite[i + 2] === 1 ? outline : fill != null ? fill[sprite[i + 3]] : -1;
    if (c >= 0) {
      surface.pixel(x + sprite[i], y + sprite[i + 1], c);
    }
  }
}

function draw_ring(surface, cx, cy, r, c) {
  surface.rect(cx - r, cy - r, r * 2 + 1, 1, c);
  surface.rect(cx - r, cy + r, r * 2 + 1, 1, c);
  surface.rect(cx - r, cy - r, 1, r * 2 + 1, c);
  surface.rect(cx + r, cy - r, 1, r * 2 + 1, c);
}

function same_position(a, b) {
  return a.order === b.order && a.pattern === b.pattern && a.row === b.row;
}

function key_lane(event) {
  if (Object.prototype.hasOwnProperty.call(KEY_CODES, event.code)) {
    return KEY_CODES[event.code];
  }
  if (Object.prototype.hasOwnProperty.call(KEYS, event.key)) {
    return KEYS[event.key];
  }
  return null;
}

// NOTE: the game is played with the keyboard or by touching the lanes, the button cycles through the difficulties
//       and then quits
function create(host, initial_difficulty) {
  const player = host.player;
  const node = player.currentPlayingNode;
  const num_channels = player.getNumChannels();
  const num_orders = player.getTotalOrder();
  const command = (position, channel, type) => player.getPatternRowChannelCommand(position.pattern, position.row, channel, type);

  let difficulty = initial_difficulty || 0;
  // NOTE: the timeline holds the played rows and the rows that are predicted to be played next,
  //       known is the index of the latest row that the player has rendered, the rows after it are predictions
  let rows = [];
  let known = -1;
  let notes = [];
  let last_step = null;
  let tokens = 0;
  let token_time = -Infinity;
  let last_time = -Infinity;
  // NOTE: the measured row lengths compared to the lengths from the speed and tempo, e.g. for modern tempo modes
  let measurements = [];
  let time_scale = 1;
  const instruments = [];
  for (let channel = 0; channel < num_channels; channel++) {
    instruments.push(0);
  }

  let clock_offset = null;
  let now = 0;
  let started = null;
  let combo = 0;
  let points = 0;
  let judged = 0;
  let last_judgment = null;
  const pressed = [];
  const flashes = [];
  for (let lane = 0; lane < LANES; lane++) {
    pressed.push(false);
    flashes.push(null);
  }
  let sprites = null;
  let sprite_size = 0;

  // NOTE: the audio time that is being heard, in the time base of the player's positions
  const sync_clock = () => {
    const context = player.context;
    const perf = performance.now();
    let offset = null;
    if (typeof context.getOutputTimestamp === 'function') {
      const stamp = context.getOutputTimestamp();
      if (stamp.performanceTime > 0) {
        offset = stamp.contextTime - stamp.performanceTime / 1000;
      }
    }
    if (offset == null) {
      offset = context.currentTime - (context.outputLatency || 0) - perf / 1000;
    }
    // NOTE: smooths the jitter of the audio clock, so that the arrows scroll smoothly
    if (clock_offset == null || Math.abs(offset - clock_offset) > CLOCK_SNAP) {
      clock_offset = offset;
    } else {
      clock_offset += (offset - clock_offset) * CLOCK_SMOOTHING;
    }
    now = perf / 1000 + clock_offset;
  };

  const reset_chart = () => {
    rows = [];
    known = -1;
    notes = [];
    last_step = null;
    tokens = DIFFICULTIES[difficulty].burst;
    token_time = -Infinity;
  };

  // compares the time between two rendered rows to the length of the rows between them
  const measure = (from, to, time) => {
    let length = 0;
    for (let i = from; i < to; i++) {
      length += rows[i].length;
    }
    measurements.push({ measured: time - rows[from].time, length });
    if (measurements.length > ROW_HISTORY) {
      measurements.shift();
    }
    let measured = 0;
    length = 0;
    for (const measurement of measurements) {
      measured += measurement.measured;
      length += measurement.length;
    }
    if (measured > 0 && length > 0) {
      time_scale = measured / length;
    }
  };

  // NOTE: the lane depends on the sound and the row, so that a repeating sound moves across the lanes
  //       and a repeating pattern gets the same steps, the row is mixed so that rows that are 1, 2, 4, 8 or 16
  //       rows apart get different lanes
  const lane_of = (note, row) => (note.note + note.instrument + row * 5 + (row >> 2) * 3 + (row >> 4)) % LANES;

  // returns the lane, or the next lane that is not taken
  const free_lane = (lane, taken) => {
    while (taken.indexOf(lane) !== -1) {
      lane = (lane + 1) % LANES;
    }
    return lane;
  };

  // reads a row, its timing continues from the speed and tempo of the previous row, and places its steps
  const make_row = (position, time, speed, tempo) => {
    const row = { order: position.order, pattern: position.pattern, row: position.row, time, dropped: false };
    const row_notes = [];
    let jump = -1;
    let pattern_break = -1;
    let delay = 0;
    let fine_delay = 0;
    for (let channel = 0; channel < num_channels; channel++) {
      const instrument = command(position, channel, COMMAND_INSTRUMENT);
      if (instrument > 0) {
        instruments[channel] = instrument;
      }
      const note = command(position, channel, COMMAND_NOTE);
      if (note >= NOTE_MIN && note <= NOTE_MAX) {
        row_notes.push({ note, instrument: instruments[channel] });
      }

      const effect = command(position, channel, COMMAND_EFFECT);
      const parameter = effect !== 0 ? command(position, channel, COMMAND_PARAMETER) : 0;
      if (effect === EFFECT_POSITION_JUMP) {
        jump = parameter;
      } else if (effect === EFFECT_PATTERN_BREAK) {
        pattern_break = parameter;
      } else if (effect === EFFECT_SPEED && parameter > 0) {
        speed = parameter;
      } else if (effect === EFFECT_TEMPO && parameter >= TEMPO_MIN) {
        // NOTE: smaller values are tempo slides
        tempo = parameter;
      } else if ((effect === EFFECT_MOD_EXTENDED || effect === EFFECT_S3M_EXTENDED) && parameter >> 4 === 0xe) {
        // NOTE: pattern delay, the row is repeated
        delay = parameter & 0xf;
      } else if (effect === EFFECT_S3M_EXTENDED && parameter >> 4 === 0x6) {
        // NOTE: fine pattern delay, the row gets extra ticks
        fine_delay = parameter & 0xf;
      }
    }
    row.jump = jump;
    row.pattern_break = pattern_break;
    row.speed = speed;
    row.tempo = tempo;
    row.length = (speed * (1 + delay) + fine_delay) * TICK_TEMPO / tempo;

    // NOTE: steps are only placed outside of the screen, so that arrows never pop up in the middle of it
    const d = DIFFICULTIES[difficulty];
    const quant = position.row % ROWS_PER_BEAT === 0 ? 0 : position.row % 2 === 0 ? 1 : 2;
    if (d.notes[quant] === 0 || row_notes.length < d.notes[quant] || measurements.length < ROWS_MEASURED || time - now < d.scroll) {
      return row;
    }
    if (last_step != null && time - last_step.row.time < d.gap - GAP_TOLERANCE) {
      return row;
    }
    // NOTE: the steps per second are limited with a token bucket that refills at the rate
    tokens = Math.min(d.burst, tokens + (time - token_time) * d.rate);
    token_time = time;
    if (tokens < 1) {
      return row;
    }
    tokens -= 1;

    // NOTE: the lanes of the previous step are avoided when it is close, the highest note is usually the melody
    const recent = last_step != null && time - last_step.row.time < JACK_GAP ? last_step.row.lanes : [];
    row_notes.sort((a, b) => b.note - a.note);
    let lane = lane_of(row_notes[0], position.row);
    if (recent.indexOf(lane) !== -1) {
      lane = free_lane((lane + 1 + row_notes[0].note % (LANES - 1)) % LANES, recent);
    }
    const lanes = [lane];
    if (d.jump_grid > 0 && position.row % d.jump_grid === 0 && row_notes.length >= JUMP_NOTES) {
      // NOTE: the second step of a jump is the lowest note, usually the bass or a drum
      lanes.push(free_lane(lane_of(row_notes[row_notes.length - 1], position.row), recent.concat(lanes)));
    }

    row.lanes = lanes;
    const steps = lanes.map((step_lane) => ({ row, lane: step_lane, quant, judgment: null }));
    notes = notes.concat(steps);
    last_step = steps[0];
    return row;
  };

  // returns the position that is played after the given row, following position jumps and pattern breaks
  const next_position = (position) => {
    let order = position.order + 1;
    let row = 0;
    if (position.jump < 0 && position.pattern_break < 0) {
      if (position.row + 1 < player.getPatternNumRows(position.pattern)) {
        return { order: position.order, pattern: position.pattern, row: position.row + 1 };
      }
    } else {
      order = position.jump >= 0 ? position.jump : order;
      row = position.pattern_break >= 0 ? position.pattern_break : 0;
    }

    // NOTE: skips the separator orders and starts over after the last order, as the player loops the song
    for (let i = 0; i <= num_orders; i++, order++) {
      if (order < 0 || order >= num_orders) {
        order = 0;
      }
      const pattern = player.getOrderPattern(order);
      if (pattern === ORDER_END) {
        order = -1;
        continue;
      }
      const num_rows = player.getPatternNumRows(pattern);
      if (num_rows > 0) {
        return { order, pattern, row: row < num_rows ? row : 0 };
      }
    }
    return null;
  };

  // drops the predicted rows from index onwards, their steps disappear without counting as misses
  const truncate = (index) => {
    if (index >= rows.length) {
      return;
    }
    for (let i = index; i < rows.length; i++) {
      rows[i].dropped = true;
    }
    rows.length = index;
    notes = notes.filter((note) => !note.row.dropped);
    if (last_step != null && last_step.row.dropped) {
      last_step = notes.length > 0 ? notes[notes.length - 1] : null;
    }
  };

  // handles a row that the player has rendered, time is when it is heard
  const on_position = (position) => {
    if (position.time - last_time > RESYNC_GAP) {
      reset_chart();
      measurements = [];
    }
    last_time = position.time;

    if (known >= 0) {
      for (let i = known + 1; i < rows.length && i <= known + ROW_SEARCH; i++) {
        if (!same_position(rows[i], position)) {
          continue;
        }
        // NOTE: rows that were played between two reads get times between the rows around them
        const from = rows[known];
        for (let j = known + 1; j < i; j++) {
          rows[j].time = from.time + (position.time - from.time) * (j - known) / (i - known);
        }
        measure(known, i, position.time);
        rows[i].time = position.time;
        known = i;
        return;
      }
    }

    // NOTE: the song went somewhere else than predicted, e.g. due to a pattern loop or seeking,
    //       the player has just rendered this row, so its speed and tempo are the current ones
    truncate(known + 1);
    const speed = player.getCurrentSpeed() || SPEED_DEFAULT;
    const tempo = player.getCurrentTempo() || TEMPO_DEFAULT;
    rows.push(make_row(position, position.time, speed, tempo));
    known = rows.length - 1;
  };

  const judge = (note, judgment) => {
    note.judgment = judgment;
    judged++;
    points += judgment.points;
    combo = judgment.combo ? combo + 1 : 0;
    last_judgment = { judgment, time: now };
    if (judgment !== MISS) {
      flashes[note.lane] = { judgment, time: now };
    }
  };

  const update = () => {
    sync_clock();
    if (started == null) {
      started = now;
    }
    if (node.modulePtr === 0) {
      return;
    }

    player.getPlayingPosition();
    if (node.playingPosition != null && node.playingPosition.time > last_time) {
      on_position(node.playingPosition);
    }
    for (const position of node.positionQueue) {
      if (position.time > last_time) {
        on_position(position);
      }
    }
    if (rows.length === 0) {
      return;
    }

    // NOTE: the predicted rows follow the latest rendered row
    for (let i = known + 1; i < rows.length; i++) {
      rows[i].time = rows[i - 1].time + rows[i - 1].length * time_scale;
    }
    const horizon = now + DIFFICULTIES[difficulty].scroll + LEAD_MARGIN;
    while (rows.length < MAX_ROWS && rows[rows.length - 1].time < horizon) {
      const last = rows[rows.length - 1];
      const position = next_position(last);
      if (position == null) {
        break;
      }
      rows.push(make_row(position, last.time + last.length * time_scale, last.speed, last.tempo));
    }

    for (const note of notes) {
      if (note.judgment == null && now - note.row.time > MISS_WINDOW) {
        judge(note, MISS);
      }
    }
    while (notes.length > 0 && notes[0].judgment != null && notes[0].row.time < now - KEEP_PAST) {
      notes.shift();
    }
    let drop = 0;
    while (drop < known && rows[drop].time < now - KEEP_PAST) {
      drop++;
    }
    if (drop > 0) {
      rows.splice(0, drop);
      known -= drop;
    }
  };

  const draw = (surface) => {
    const w = surface.width;
    const h = surface.height;
    const d = DIFFICULTIES[difficulty];
    const lane_width = Math.max(ARROW_SIZE_MIN + ARROW_PADDING * 2, Math.min(Math.round(h * LANE_SIZE), Math.floor(w * FIELD_WIDTH / LANES)));
    const size = lane_width - ARROW_PADDING * 2;
    if (size !== sprite_size) {
      sprites = arrow_sprites(size);
      sprite_size = size;
    }
    const field_x = Math.round((w - lane_width * LANES) / 2);
    const lane_x = (lane) => field_x + lane * lane_width + ARROW_PADDING;
    const receptor_y = Math.round(h * RECEPTOR_Y);
    const speed = (h - receptor_y) / d.scroll;
    const large = Math.max(FONT_MIN, Math.round(h * FONT_LARGE));
    const small = Math.max(FONT_MIN, Math.round(h * FONT_SMALL));

    surface.shade(field_x, 0, lane_width * LANES, h);

    // NOTE: the receptors light up on the beats of the playing row
    let playing = known;
    while (playing > 0 && rows[playing].time > now) {
      playing--;
    }
    const beat = playing >= 0 && rows[playing].row % ROWS_PER_BEAT === 0;
    for (let lane = 0; lane < LANES; lane++) {
      const flash = flashes[lane] != null && now - flashes[lane].time < FLASH_TIME ? flashes[lane] : null;
      if (flash != null) {
        draw_sprite(surface, sprites[lane], lane_x(lane), receptor_y, RECEPTOR_FLASH, flash.judgment.fill);
      } else if (pressed[lane]) {
        draw_sprite(surface, sprites[lane], lane_x(lane), receptor_y, RECEPTOR_PRESSED, RECEPTOR_PRESSED_FILL);
      } else {
        draw_sprite(surface, sprites[lane], lane_x(lane), receptor_y, beat ? RECEPTOR_BEAT : RECEPTOR_IDLE, null);
      }
    }

    for (const note of notes) {
      if (note.judgment != null && note.judgment !== MISS) {
        continue;
      }
      const y = Math.round(receptor_y + (note.row.time - now) * speed);
      if (y > -size && y < h) {
        draw_sprite(surface, sprites[note.lane], lane_x(note.lane), y, NOTE_OUTLINE, note.judgment === MISS ? MISSED_FILL : NOTE_FILLS[note.quant]);
      }
    }

    for (let lane = 0; lane < LANES; lane++) {
      const flash = flashes[lane];
      if (flash != null && now - flash.time < FLASH_TIME) {
        const spread = (now - flash.time) / FLASH_TIME * lane_width * FLASH_SPREAD;
        const center = Math.floor((size - 1) / 2);
        draw_ring(surface, lane_x(lane) + center, receptor_y + center, Math.round(size / 2 + 1 + spread), flash.judgment.fill[1]);
      }
    }

    const center_x = field_x + lane_width * LANES / 2;
    const judgment_y = Math.round(h * JUDGMENT_Y);
    if (last_judgment != null && now - last_judgment.time < JUDGMENT_TIME) {
      const bounce = Math.round(Math.max(0, 1 - (now - last_judgment.time) / JUDGMENT_BOUNCE) * large / 4);
      surface.text(last_judgment.judgment.name, center_x, judgment_y - bounce, large, last_judgment.judgment.colors, 'center');
    } else if (now - started < READY_TIME) {
      surface.text('READY', center_x, judgment_y, large, HUD_COLORS, 'center');
    }
    if (combo >= COMBO_MIN) {
      surface.text(combo + ' COMBO', center_x, judgment_y + surface.text_height(large), small, HUD_COLORS, 'center');
    }

    const hud_y = h - surface.text_height(small) - 1;
    const percent = judged > 0 ? points / (judged * MAX_POINTS) * 100 : 0;
    surface.text(d.name, 2, hud_y, small, HUD_COLORS, 'left');
    surface.text(percent.toFixed(2) + '%', w - 3, hud_y, small, HUD_COLORS, 'right');
  };

  // NOTE: input_time is the time of the input event in seconds, in the time base of performance.now()
  const press = (lane, input_time) => {
    pressed[lane] = true;
    if (clock_offset == null) {
      return;
    }

    const time = input_time + clock_offset;
    let best = null;
    let best_error = Infinity;
    for (const note of notes) {
      const error = Math.abs(note.row.time - time);
      if (note.lane === lane && note.judgment == null && error < best_error) {
        best = note;
        best_error = error;
      }
    }
    if (best == null) {
      return;
    }
    for (const judgment of JUDGMENTS) {
      if (best_error <= judgment.window) {
        judge(best, judgment);
        return;
      }
    }
  };

  const release = (lane) => {
    pressed[lane] = false;
  };

  // NOTE: changing the difficulty starts over with a new chart and score
  const set_difficulty = (index) => {
    difficulty = index;
    reset_chart();
    combo = 0;
    points = 0;
    judged = 0;
    last_judgment = null;
    started = null;
  };

  const render = (surface) => {
    update();
    draw(surface);
  };

  const on_key = (event, time) => {
    const lane = key_lane(event);
    if (lane != null) {
      if (event.type === 'keyup') {
        release(lane);
      } else if (!event.repeat) {
        press(lane, time);
      }
      // NOTE: also keeps the arrow keys from scrolling the page
      return true;
    }
    if (event.type !== 'keydown') {
      return false;
    }
    const index = event.key != null && event.key.length === 1 ? DIFFICULTY_KEYS.indexOf(event.key) : -1;
    if (index >= 0 && index < DIFFICULTIES.length) {
      set_difficulty(index);
      return true;
    }
    if (event.key === 'Escape' || event.key === 'Esc') {
      host.close();
    }
    return false;
  };

  // NOTE: the canvas is split into a touch area for each lane
  const pointer_lanes = {};
  const on_pointer = (event, x, y, time) => {
    if (event.type === 'pointerdown') {
      const lane = Math.max(0, Math.min(LANES - 1, Math.floor(x * LANES)));
      pointer_lanes[event.pointerId] = lane;
      press(lane, time);
      return true;
    }
    if (!(event.pointerId in pointer_lanes)) {
      return false;
    }
    release(pointer_lanes[event.pointerId]);
    delete pointer_lanes[event.pointerId];
    return true;
  };

  const on_blur = () => {
    for (let lane = 0; lane < LANES; lane++) {
      release(lane);
    }
  };

  const on_button = () => {
    if (difficulty + 1 < DIFFICULTIES.length) {
      set_difficulty(difficulty + 1);
    } else {
      host.close();
    }
  };

  return { render, on_key, on_pointer, on_blur, on_button };
}

export default fx.define({
  name: 'minimania',
  icon: ICON,
  label: 'mod.minimania',
  interactive: true,
  create: (host) => create(host, 0),
});
