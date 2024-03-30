// Source: https://gist.github.com/0x263b/2bdd90886c2036a1ad5bcf06d6e6fb37
function toHex(input_str) {
  let hash = 0;
  if (input_str.length === 0) return hash;
  for (let i = 0; i < input_str.length; i++) {
    hash = input_str.charCodeAt(i) + ((hash << 5) - hash);
    hash = hash & hash;
  }
  let color = '#';
  for (let i = 0; i < 3; i++) {
    let value = (hash >> (i * 8)) & 255;
    color += ('00' + value.toString(16)).substr(-2);
  }
  return color;
}

function removeTrailingSlash(input_str) {
  return input_str.endsWith('/') ? input_str.slice(0, -1) : input_str;
}

function isVisible(element, threshold, mode) {
  if (element == null) {
    return false;
  }

  threshold = threshold || 0;
  mode = mode || 'visible';

  const rect = element.getBoundingClientRect();
  const viewHeight = Math.max(document.documentElement.clientHeight, window.innerHeight);
  const above = rect.bottom - threshold < 0;
  const below = rect.top - viewHeight + threshold >= 0;

  return mode === 'above' ? above : (mode === 'below' ? below : !above && !below);
}

const utils = {
  toHex,
  removeTrailingSlash,
  isVisible,
};

export default utils;
