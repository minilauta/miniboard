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

/**
 * Encodes form data as a single application/octet-stream body instead of multipart/form-data,
 * see src/core/binary_form.php for the layout.
 */
function encodeBinaryForm(form_data) {
  const fields = new URLSearchParams();
  const files = [];
  form_data.forEach((value, key) => {
    if (value instanceof File) {
      files.push(value);
    } else {
      // NOTE: normalize newlines to CRLF like browsers do for regular form submissions
      fields.append(key, value.replace(/\r\n|\r|\n/g, '\r\n'));
    }
  });

  const header = new Blob([JSON.stringify({
    fields: fields.toString(),
    files: files.map((file) => ({ name: file.name, type: file.type, size: file.size })),
  })]);
  const header_size = new DataView(new ArrayBuffer(4));
  header_size.setUint32(0, header.size);

  return new Blob([header_size, header, ...files], { type: 'application/octet-stream' });
}

const utils = {
  toHex,
  removeTrailingSlash,
  isVisible,
  encodeBinaryForm,
};

export default utils;
