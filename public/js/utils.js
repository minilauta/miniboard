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

const utils = {
  toHex,
  removeTrailingSlash,
};

export default utils;
