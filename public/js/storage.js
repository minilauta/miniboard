/**
 * Set cookie value by key.
 * @param {*} key 
 * @param {*} val 
 * @param {*} samesite 
 * @param {*} expires 
 */
function set_cookie(key, val, samesite, expires) {
  key = 'miniboard/' + key;

  if (expires instanceof Date) {
    expires = expires.toUTCString();
  }

  document.cookie = key + '=' + encodeURIComponent(val) + '; path=/; SameSite=' + samesite + '; expires=' + expires;
}

/**
 * Get cookie value by key.
 * @param {*} key 
 * @returns 
 */
function get_cookie(key) {
  key = 'miniboard/' + key;

  let decoded = decodeURIComponent(document.cookie);
  let cookies = decoded.split(';');

  for (let i = 0; i < cookies.length; i++) {
    let cookie = cookies[i].split('=');

    if (cookie.length !== 2) {
      return;
    }

    if (cookie[0].trimStart() === key) {
      return cookie[1];
    }
  };

  return null;
}

/**
 * Set local storage value by key.
 * @param {*} key 
 * @param {*} val 
 */
function set_lsvar(key, val) {
  key = 'miniboard/' + key;
  window.localStorage.setItem(key, val);
}

/**
 * Get local storage value by key.
 * @param {*} key 
 * @returns 
 */
function get_lsvar(key) {
  key = 'miniboard/' + key;
  return window.localStorage.getItem(key);
}

const storage = {
  set_cookie,
  get_cookie,
  set_lsvar,
  get_lsvar,
};

export default storage;
