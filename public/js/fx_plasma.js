import fx from './fx';

const ICON = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" style="display: block"><path d="M1 5q3.5-4 7 0t7 0M1 11q3.5-4 7 0t7 0"/></svg>';
const COLOR_TABLES = [
  // blue
  [
    0x000000, 0x000000, 0x50459b, 0x50459b, 0x887ecb, 0x887ecb, 0x6abfc6, 0x6abfc6,
    0xc9d487, 0xc9d487, 0xffffff, 0xffffff, 0xc9d487, 0xc9d487, 0x6abfc6, 0x6abfc6,
    0x887ecb, 0x887ecb, 0x50459b, 0x50459b, 0x000000, 0x000000,
  ],
  // rainbow
  [
    0x000000, 0x000000, 0x6d5412, 0x6d5412, 0x9f4e44, 0x9f4e44, 0xa1683c, 0xa1683c,
    0xcb7e75, 0xcb7e75, 0xadadad, 0xadadad, 0xc9d487, 0xc9d487, 0xffffff, 0xffffff,
    0x9ae29b, 0x9ae29b, 0x6abfc6, 0x6abfc6, 0x887ecb, 0x887ecb, 0x626262, 0x626262,
    0x50459b, 0x50459b, 0x000000, 0x000000,
  ],
  // grey
  [
    0x000000, 0x000000, 0x626262, 0x626262, 0x898989, 0x898989, 0xadadad, 0xadadad,
    0xffffff, 0xffffff, 0xadadad, 0xadadad, 0x898989, 0x898989, 0x626262, 0x626262,
    0x000000, 0x000000,
  ],
  // ice
  [
    0x000000, 0x000000, 0x50459b, 0x50459b, 0x887ecb, 0x887ecb, 0x6abfc6, 0x6abfc6,
    0xadadad, 0xadadad, 0xffffff, 0xffffff, 0xadadad, 0xadadad, 0x6abfc6, 0x6abfc6,
    0x887ecb, 0x887ecb, 0x50459b, 0x50459b, 0x000000, 0x000000,
  ],
  // red
  [
    0x000000, 0x000000, 0x6d5412, 0x6d5412, 0xa1683c, 0xa1683c, 0x9f4e44, 0x9f4e44,
    0xcb7e75, 0xcb7e75, 0xc9d487, 0xc9d487, 0xffffff, 0xffffff, 0xc9d487, 0xc9d487,
    0xcb7e75, 0xcb7e75, 0x9f4e44, 0x9f4e44, 0xa1683c, 0xa1683c, 0x6d5412, 0x6d5412,
    0x000000, 0x000000,
  ],
  // sunset
  [
    0x000000, 0x000000, 0x50459b, 0x50459b, 0x9f4e44, 0x9f4e44, 0xcb7e75, 0xcb7e75,
    0xc9d487, 0xc9d487, 0xffffff, 0xffffff, 0xc9d487, 0xc9d487, 0xcb7e75, 0xcb7e75,
    0x9f4e44, 0x9f4e44, 0x50459b, 0x50459b, 0x000000, 0x000000,
  ],
  // green
  [
    0x000000, 0x000000, 0x626262, 0x626262, 0x5cab5e, 0x5cab5e, 0x9ae29b, 0x9ae29b,
    0xc9d487, 0xc9d487, 0xffffff, 0xffffff, 0xc9d487, 0xc9d487, 0x9ae29b, 0x9ae29b,
    0x5cab5e, 0x5cab5e, 0x626262, 0x626262, 0x000000, 0x000000,
  ],
  // yellow
  [
    0x626262, 0x626262, 0x898989, 0x898989, 0xadadad, 0xadadad, 0xc9d487, 0xc9d487,
    0xffffff, 0xffffff, 0xc9d487, 0xc9d487, 0xadadad, 0xadadad, 0x898989, 0x898989,
    0x626262, 0x626262,
  ],
];
const TIME_SCALE = 0.25;
const SX = 32;
const SY = 12;
const WAVE = 0.75;
const NOTE_ENERGY = 0.5;
const NOTE_DECAY = 6;
const NOTE_FLASH = 1;
const NOTE_SPEED = 2;
// NOTE: map the C64 colors from dim to bright
const FLASH_BRIGHTER = {
  0x000000: 0x626262,
  0x50459b: 0x887ecb,
  0x6d5412: 0xa1683c,
  0x626262: 0x898989,
  0x9f4e44: 0xcb7e75,
  0xa1683c: 0xcb7e75,
  0x898989: 0xadadad,
  0x887ecb: 0x6abfc6,
  0x5cab5e: 0x9ae29b,
  0xcb7e75: 0xc9d487,
  0x6abfc6: 0x9ae29b,
  0xadadad: 0xffffff,
  0x9ae29b: 0xffffff,
  0xc9d487: 0xffffff,
  0xffffff: 0xffffff,
};

function brighten(color, steps) {
  for (let i = 0; i < steps; i++) {
    color = FLASH_BRIGHTER[color] ?? color;
  }
  return color;
}

// NOTE: the notes of the playing row flash the colors and speed up the plasma, the palette changes with the pattern
function create() {
  let cols = null;
  let rows = null;
  let size = 0;
  let color_table = COLOR_TABLES[0];
  let note_energy = 0;
  let note_time = 0;

  const resize = (surface) => {
    cols = new Float32Array(surface.width);
    rows = new Float32Array(surface.height);
    size = Math.sqrt(surface.width * surface.height);
  };

  const render = (surface, frame) => {
    const w = surface.width;
    const h = surface.height;
    const put = surface.put;

    note_energy *= Math.exp(-NOTE_DECAY * frame.dt);
    note_time += note_energy * NOTE_SPEED * frame.dt;
    const flash_steps = Math.round(note_energy * NOTE_FLASH);
    const table = flash_steps > 0 ? color_table.map((c) => brighten(c, flash_steps)) : color_table;
    const t = frame.time * TIME_SCALE + note_time;

    for (let x = 0; x < w; x++) {
      cols[x] = Math.sin(x / size * SX + t) * WAVE * 0.5 + 0.5;
    }
    for (let y = 0; y < h; y++) {
      rows[y] = Math.cos(y / size * SY + Math.sin(t * 2) * SY * 0.5) * WAVE * 0.5 + 0.5;
    }
    const cx = Math.sin(t * Math.PI * 2) * 0.5 + 0.5;
    const cy = Math.cos(t * Math.PI * 3) * 0.5 + 0.5;
    const color_scale = table.length / 3;

    let i = 0;
    for (let y = 0; y < h; y++) {
      const dy = y / h - cy;
      for (let x = 0; x < w; x++) {
        const dx = x / w - cx;
        const f = cols[x] + rows[y] + Math.sqrt(dx * dx + dy * dy) * 2; // *2 clips it outside col table but idgaf
        put(i, table[Math.floor(f * color_scale) % table.length]);
        i += 4;
      }
    }
  };

  const on_row = (event) => {
    note_energy = Math.min(1, note_energy + event.notes * NOTE_ENERGY);
  };

  const on_pattern = () => {
    // NOTE: never pick the current table again, so that every switch is visible
    const index = COLOR_TABLES.indexOf(color_table);
    const next = Math.floor(Math.random() * (COLOR_TABLES.length - 1));
    color_table = COLOR_TABLES[next >= index ? next + 1 : next];
  };

  return { render, resize, on_row, on_pattern };
}

export default fx.define({
  name: 'plasma',
  icon: ICON,
  label: 'mod.plasma',
  opaque: true,
  create,
});
