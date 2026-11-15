const MAX_PIXELS = 320 * 200;
const PLASMA_COLOR_TABLES = [
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
const PLASMA_TIME_SCALE = 0.25;
const PLASMA_SX = 32;
const PLASMA_SY = 12;
const PLASMA_WAVE = 0.75;
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
const DYCP_COLORS = [0xffffff, 0xffffff, 0xc9d487, 0xc9d487, 0xcb7e75];
const DYCP_SHADOW_COLOR = 0x000000;
const DYCP_FONT_FAMILY = '"DejaVu Sans Mono", Consolas, Menlo, monospace';
const DYCP_FONT_SIZE = 1 / 12;
const DYCP_SPEED = 60;
const DYCP_AMPLITUDE = 0.12;
const DYCP_WAVELENGTH = 1.0;
const DYCP_WAVE_SPEED = 3;

function brighten(color, steps) {
  for (let i = 0; i < steps; i++) {
    color = FLASH_BRIGHTER[color] ?? color;
  }
  return color;
}

function rasterize_font(chars, font_size) {
  const canvas = document.createElement('canvas');
  const ctx = canvas.getContext('2d', { willReadFrequently: true });
  const font = 'bold ' + font_size + 'px ' + DYCP_FONT_FAMILY;
  ctx.font = font;
  const width = Math.ceil(ctx.measureText('M').width);
  const height = Math.ceil(font_size * 1.25);
  canvas.width = width;
  canvas.height = height;

  const rasterize = (char) => {
    ctx.clearRect(0, 0, width, height);
    ctx.font = font;
    ctx.textBaseline = 'middle';
    ctx.fillStyle = '#ffffff';
    ctx.fillText(char, 0, height / 2);
    const data = ctx.getImageData(0, 0, width, height).data;

    let mask = null;
    for (let i = 0; i < width * height; i++) {
      if (data[i * 4 + 3] >= 128) {
        mask = mask || new Uint8Array(width * height);
        mask[i] = 1;
      }
    }
    // NOTE: null for characters without pixels, such as spaces
    return mask;
  };

  const glyphs = {};
  for (const char of chars) {
    if (!(char in glyphs)) {
      glyphs[char] = rasterize(char);
    }
  }

  let ink_top = 0;
  let ink_bottom = height - 1;
  const reference = rasterize('H');
  if (reference != null) {
    ink_top = Math.floor(reference.indexOf(1) / width);
    ink_bottom = Math.floor(reference.lastIndexOf(1) / width);
  }

  const row_colors = [];
  for (let y = 0; y < height; y++) {
    const ramp = (y - ink_top) / Math.max(1, ink_bottom - ink_top + 1);
    row_colors.push(DYCP_COLORS[Math.max(0, Math.min(DYCP_COLORS.length - 1, Math.floor(ramp * DYCP_COLORS.length)))]);
  }

  return { width, height, glyphs, row_colors };
}

function create(width, height, text) {
  const scale = Math.ceil(Math.sqrt(width * height / MAX_PIXELS));
  const w = Math.ceil(width / scale);
  const h = Math.ceil(height / scale);

  const buffer = document.createElement('canvas');
  buffer.width = w;
  buffer.height = h;
  const buffer_ctx = buffer.getContext('2d');
  const image = buffer_ctx.createImageData(w, h);
  const pixels = image.data;
  for (let i = 3; i < pixels.length; i += 4) {
    pixels[i] = 255;
  }

  const cols = new Float32Array(w);
  const rows = new Float32Array(h);
  const chars = Array.from(text);
  const font = rasterize_font(chars, Math.round(h * DYCP_FONT_SIZE));
  const start = performance.now();
  let color_table = PLASMA_COLOR_TABLES[0];
  let rendered = 0;
  let note_energy = 0;
  let note_time = 0;

  const set_pixel = (i, c) => {
    pixels[i] = (c >> 16) & 0xff;
    pixels[i + 1] = (c >> 8) & 0xff;
    pixels[i + 2] = c & 0xff;
  };

  const render_plasma = (t, table) => {
    for (let x = 0; x < w; x++) {
      cols[x] = Math.sin(x / w * PLASMA_SX + t) * PLASMA_WAVE * 0.5 + 0.5;
    }
    for (let y = 0; y < h; y++) {
      rows[y] = Math.cos(y / h * PLASMA_SY + Math.sin(t * 2) * PLASMA_SY * 0.5) * PLASMA_WAVE * 0.5 + 0.5;
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
        set_pixel(i, table[Math.floor(f * color_scale) % table.length]);
        i += 4;
      }
    }
  };

  const draw_glyph = (mask, x0, y0, shadow) => {
    for (let gy = 0; gy < font.height; gy++) {
      const y = y0 + gy;
      if (y < 0 || y >= h) {
        continue;
      }
      const c = shadow ? DYCP_SHADOW_COLOR : font.row_colors[gy];
      for (let gx = 0; gx < font.width; gx++) {
        const x = x0 + gx;
        if (x >= 0 && x < w && mask[gy * font.width + gx]) {
          set_pixel((y * w + x) * 4, c);
        }
      }
    }
  };

  const render_dycp = (seconds) => {
    const offset = w - (seconds * DYCP_SPEED) % (w + chars.length * font.width);
    const first = Math.max(0, Math.floor(-offset / font.width));
    const last = Math.min(chars.length - 1, Math.floor((w - offset) / font.width));
    for (let i = first; i <= last; i++) {
      const mask = font.glyphs[chars[i]];
      if (mask == null) {
        continue;
      }
      const x = Math.round(offset + i * font.width);
      const wave = Math.sin(x / (w * DYCP_WAVELENGTH) * Math.PI * 2 + seconds * DYCP_WAVE_SPEED);
      const y = Math.round((h - font.height) / 2 + wave * h * DYCP_AMPLITUDE);
      draw_glyph(mask, x + 1, y + 1, true);
      draw_glyph(mask, x, y, false);
    }
  };

  const render = (ctx) => {
    const seconds = (performance.now() - start) / 1000;
    const dt = Math.min(0.1, seconds - rendered);
    rendered = seconds;
    note_energy *= Math.exp(-NOTE_DECAY * dt);
    note_time += note_energy * NOTE_SPEED * dt;
    const flash_steps = Math.round(note_energy * NOTE_FLASH);
    const table = flash_steps > 0 ? color_table.map((c) => brighten(c, flash_steps)) : color_table;

    render_plasma(seconds * PLASMA_TIME_SCALE + note_time, table);
    render_dycp(seconds);
    buffer_ctx.putImageData(image, 0, 0);

    ctx.imageSmoothingEnabled = false;
    ctx.drawImage(buffer, 0, 0, width, height);
  };

  const trigger = (notes) => {
    note_energy = Math.min(1, note_energy + notes * NOTE_ENERGY);
  };

  const switch_palette = () => {
    // NOTE: never pick the current table again, so that every switch is visible
    const index = PLASMA_COLOR_TABLES.indexOf(color_table);
    const next = Math.floor(Math.random() * (PLASMA_COLOR_TABLES.length - 1));
    color_table = PLASMA_COLOR_TABLES[next >= index ? next + 1 : next];
  };

  return { render, trigger, switch_palette };
}

const plasma_fx = {
  create,
};

export default plasma_fx;
