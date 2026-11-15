import fx from './fx';

const ICON = '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" style="display: block"><path d="M1 8h4v5H1zM6 3h4v5H6zM11 8h4v5h-4z"/></svg>';
const COLORS = [0xffffff, 0xffffff, 0xc9d487, 0xc9d487, 0xcb7e75];
const SHADOW_COLOR = 0x000000;
const FONT_SIZE = 1 / 12;
const SPEED = 60;
const AMPLITUDE = 0.12;
const WAVELENGTH = 1.0;
const WAVE_SPEED = 3;

function create_scroller_text(metadata, names) {
  return [metadata.title, metadata.artist, names.join(' ')]
    .map((part) => (part || '').replace(/\s+/g, ' ').trim())
    .filter((part) => part !== '')
    .join('   ');
}

function create(host) {
  const chars = Array.from(create_scroller_text(host.player.metadata(), host.player.getInstrumentNames()));
  let font = null;
  let font_colors = null;

  const resize = (surface) => {
    font = surface.font(Math.max(1, Math.round(surface.height * FONT_SIZE)));
    font_colors = surface.gradient(font, COLORS);
    chars.forEach((char) => font.glyph(char));
  };

  const render = (surface, frame) => {
    const w = surface.width;
    const h = surface.height;
    const seconds = frame.time;
    const offset = w - (seconds * SPEED) % (w + chars.length * font.width);
    const first = Math.max(0, Math.floor(-offset / font.width));
    const last = Math.min(chars.length - 1, Math.floor((w - offset) / font.width));
    for (let i = first; i <= last; i++) {
      const mask = font.glyph(chars[i]);
      if (mask == null) {
        continue;
      }
      const x = Math.round(offset + i * font.width);
      const wave = Math.sin(x / (w * WAVELENGTH) * Math.PI * 2 + seconds * WAVE_SPEED);
      const y = Math.round((h - font.height) / 2 + wave * h * AMPLITUDE);
      surface.glyph(font, mask, x + 1, y + 1, SHADOW_COLOR);
      surface.glyph(font, mask, x, y, font_colors);
    }
  };

  return { render, resize };
}

export default fx.define({
  name: 'dycp',
  icon: ICON,
  label: 'mod.dycp',
  create,
});
