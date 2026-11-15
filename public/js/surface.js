const MAX_PIXELS = 320 * 200;
const FONT_FAMILY = '"DejaVu Sans Mono", Consolas, Menlo, monospace';
const SHADOW_COLOR = 0x000000;

function rasterize_font(font_size) {
  const canvas = document.createElement('canvas');
  const ctx = canvas.getContext('2d', { willReadFrequently: true });
  const font = 'bold ' + font_size + 'px ' + FONT_FAMILY;
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
  const glyph = (char) => {
    if (!(char in glyphs)) {
      glyphs[char] = rasterize(char);
    }
    return glyphs[char];
  };

  let ink_top = 0;
  let ink_bottom = height - 1;
  const reference = glyph('H');
  if (reference != null) {
    ink_top = Math.floor(reference.indexOf(1) / width);
    ink_bottom = Math.floor(reference.lastIndexOf(1) / width);
  }

  return { width, height, glyph, ink_top, ink_bottom };
}

const fonts = {};

function get_font(size) {
  fonts[size] = fonts[size] || rasterize_font(size);
  return fonts[size];
}

function font_gradient(font, colors) {
  const row_colors = [];
  for (let y = 0; y < font.height; y++) {
    const ramp = (y - font.ink_top) / Math.max(1, font.ink_bottom - font.ink_top + 1);
    row_colors.push(colors[Math.max(0, Math.min(colors.length - 1, Math.floor(ramp * colors.length)))]);
  }
  return row_colors;
}

function create(width, height) {
  const scale = Math.max(1, Math.ceil(Math.sqrt(width * height / MAX_PIXELS)));
  const w = Math.max(1, Math.ceil(width / scale));
  const h = Math.max(1, Math.ceil(height / scale));

  const buffer = document.createElement('canvas');
  buffer.width = w;
  buffer.height = h;
  const buffer_ctx = buffer.getContext('2d');
  const image = buffer_ctx.createImageData(w, h);
  const pixels = image.data;
  for (let i = 3; i < pixels.length; i += 4) {
    pixels[i] = 255;
  }

  const put = (i, c) => {
    pixels[i] = (c >> 16) & 0xff;
    pixels[i + 1] = (c >> 8) & 0xff;
    pixels[i + 2] = c & 0xff;
  };

  const glyph = (font, mask, x0, y0, colors) => {
    for (let gy = 0; gy < font.height; gy++) {
      const y = y0 + gy;
      if (y < 0 || y >= h) {
        continue;
      }
      const c = typeof colors === 'number' ? colors : colors[gy];
      for (let gx = 0; gx < font.width; gx++) {
        const x = x0 + gx;
        if (x >= 0 && x < w && mask[gy * font.width + gx]) {
          put((y * w + x) * 4, c);
        }
      }
    }
  };

  return {
    width: w,
    height: h,
    pixels,
    put,
    pixel: (x, y, c) => {
      if (x >= 0 && x < w && y >= 0 && y < h) {
        put((y * w + x) * 4, c);
      }
    },
    rect: (x, y, rw, rh, c) => {
      const x1 = Math.min(w, x + rw);
      const y1 = Math.min(h, y + rh);
      for (let py = Math.max(0, y); py < y1; py++) {
        for (let px = Math.max(0, x); px < x1; px++) {
          put((py * w + px) * 4, c);
        }
      }
    },
    clear: (c) => {
      for (let i = 0; i < pixels.length; i += 4) {
        put(i, c || 0);
      }
    },
    shade: (x, y, rw, rh) => {
      const x1 = Math.min(w, x + rw);
      const y1 = Math.min(h, y + rh);
      for (let py = Math.max(0, y); py < y1; py++) {
        for (let px = Math.max(0, x); px < x1; px++) {
          const i = (py * w + px) * 4;
          pixels[i] >>= 2;
          pixels[i + 1] >>= 2;
          pixels[i + 2] >>= 2;
        }
      }
    },
    font: get_font,
    gradient: font_gradient,
    glyph,
    text_width: (text, size) => Array.from(text).length * get_font(size).width,
    text_height: (size) => get_font(size).height,
    text: (text, x, y, size, colors, align) => {
      const font = get_font(size);
      const row_colors = font_gradient(font, colors);
      const text_chars = Array.from(text);
      const text_width = text_chars.length * font.width;
      let x0 = Math.round(align === 'center' ? x - text_width / 2 : align === 'right' ? x - text_width : x);
      for (const char of text_chars) {
        const mask = font.glyph(char);
        if (mask != null) {
          glyph(font, mask, x0 + 1, y + 1, SHADOW_COLOR);
          glyph(font, mask, x0, y, row_colors);
        }
        x0 += font.width;
      }
    },
    blit: (ctx, dest_width, dest_height) => {
      buffer_ctx.putImageData(image, 0, 0);
      ctx.imageSmoothingEnabled = false;
      ctx.drawImage(buffer, 0, 0, dest_width, dest_height);
    },
  };
}

const surface = {
  create,
};

export default surface;
