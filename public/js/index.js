export * from './polyfill';
export * from './utils';

// ruffle player
window.RufflePlayer = window.RufflePlayer || {};
window.RufflePlayer.config = {
  // Options affecting the whole page
  'publicPath': undefined,
  'polyfills': false,

  // Options affecting files only
  'autoplay': 'on',
  'unmuteOverlay': 'visible',
  'backgroundColor': null,
  'wmode': 'window',
  'letterbox': 'fullscreen',
  'warnOnUnsupportedContent': false,
  'contextMenu': true,
  'showSwfDownload': false,
  'upgradeToHttps': false,
  'maxExecutionDuration': {'secs': 15, 'nanos': 0},
  'logLevel': 'error',
  'base': null,
  'menu': true,
  'salign': '',
  'scale': 'showAll',
  'quality': 'high',
  'preloader': true,
};

// app state
var state = {
  mouse_over_post_ref_link: false
};

/**
 * Open a new window, center if possible.
 * @param {*} url 
 * @param {*} target 
 * @param {*} features 
 * @returns 
 */
function open_window(url, target, features) {
  if (features != null) {
    let features_split = features.split(',')
      .map(x => x.trim());
    let width = features_split.find(x => x.startsWith('width'));
    let height = features_split.find(x => x.startsWith('height'));

    // center window
    if (width != null && height != null) {
      width = parseInt(width.split('=')[1]);
      height = parseInt(height.split('=')[1]);

      let top = 0.5 * (screen.height - height);
      let left = 0.5 * (screen.width - width);

      features = features.concat(',', 'top=' + top);
      features = features.concat(',', 'left=' + left);
    }
  }

  return window.open(url, target, features);
}

/**
 * Set cookie value.
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
 * Event listener: click on post thumbnail anchor.
 * Expands/shrinks the content.
 * @param {*} event 
 */
function listener_post_thumb_link_click(event) {
  event.preventDefault();
  event.stopPropagation();

  let target = event.target;
  let current = event.currentTarget;

  const shrink = function(target, current, file_info, file_ext) {
    current.firstElementChild.style.display = null;

    switch (file_ext) {
      case 'mp3':
        current.lastElementChild.remove();
        break;
      default:
        target.remove();
        break;
    }

    const file_shrink = file_info.getElementsByClassName('file-shrink-href');
    if (file_shrink.length > 0) {
      file_shrink[0].remove();
    }

    current.setAttribute('expanded', 'false');
  };

  const expand = function(target, current, file_info, file_href, file_ext, file_data) {
    switch (file_ext) {
      case 'mp4':
      case 'webm':
        target.style.display = 'none';
        
        let source = document.createElement('source');
        source.src = file_href;
        let video = document.createElement('video');
        video.setAttribute('onloadstart', 'this.volume=0.25');
        video.setAttribute('autoplay', 'true');
        video.setAttribute('controls', 'true');
        video.style.maxWidth = '85vw';
        video.style.height = 'auto';
        video.style.cursor = 'default';
        video.appendChild(source);

        current.appendChild(video);
        break;
      case 'mp3':
        let audio = document.createElement('audio');
        audio.src = file_href;
        audio.setAttribute('onloadstart', 'this.volume=0.25');
        audio.setAttribute('autoplay', 'true');
        audio.setAttribute('controls', 'true');
        audio.style.width = target.width + 'px';
        audio.style.cursor = 'default';

        current.appendChild(audio);
        break;
      case 'swf':
        target.style.display = 'none';

        const ruffle = window.RufflePlayer.newest();
        const player = ruffle.createPlayer();

        current.appendChild(player);
        player.load({
          url: file_href,
          autoplay: 'on',
          allowScriptAccess: false,
        }).then(() => {
          player.volume = 0.25;
        });
        break;
      case 'embed':
        target.style.display = 'none';

        let embed = document.createElement('div');
        embed.innerHTML = decodeURIComponent(file_data);
        embed.style.width = '33vw';
        embed.style.maxWidth = '33vw';
        embed.style.height = '33vh';
        embed.style.maxHeight = '33vh';
        embed.firstElementChild.width = '100%';
        embed.firstElementChild.height = '100%';

        current.appendChild(embed);
        break;
      default:
        target.style.display = 'none';

        let img = document.createElement('img');
        img.src = file_href;
        img.style.maxWidth = '85vw';
        img.style.height = 'auto';
        img.loading = 'lazy';

        current.appendChild(img);
        break;
    }

    let anchor = document.createElement('a');
    anchor.href = '';
    anchor.innerHTML = '[-]';
    anchor.classList.add('file-shrink-href')
    anchor.onclick = function(event) {
      event.preventDefault();
      event.stopPropagation();

      shrink(current.lastElementChild, current, file_info, file_ext);
    }
    file_info.prepend(anchor);

    current.setAttribute('expanded', 'true');
  };

  let file_info = current.parentElement.parentElement.getElementsByClassName('file-info');
  file_info = file_info.length > 0 ? file_info[0] : null;
  let file_data = current.parentElement.parentElement.getElementsByClassName('file-data');
  file_data = file_data.length > 0 ? file_data[0].innerHTML : null;
  file_data = file_data.length > 0 ? file_data : null;
  const file_href = current.href;
  let file_ext = file_data == null ? file_href.split('.').pop().toLowerCase() : 'embed';
  
  if (current.getAttribute('expanded') !== 'true') {
    expand(target, current, file_info, file_href, file_ext, file_data);
  } else {
    if (file_ext !== 'swf') {
      shrink(target, current, file_info, file_ext);
    }
  }
}

/**
 * Event listener: click on dropdown menu button.
 * Opens/closes the menu.
 * @param {*} event 
 */
function listener_dropdown_menu_button_click(event) {
  event.preventDefault();

  let target = event.target;
  let rect = target.getBoundingClientRect();
  let data = target.dataset;

  // open or close the menu
  if (!target.classList.contains('dd-menu-btn-open')) {
    target.classList.add('dd-menu-btn-open');

    switch (data.cmd) {
      case 'post-menu':
        let lis = [];
        lis.push({
          type: 'li',
          text: 'Report post',
          data: {
            cmd: 'report',
            board_id: data.board_id,
            id: data.id
          }
        });
        if (data.parent_id == null) {
          lis.push({
            type: 'li',
            text: !location.pathname.includes('/hidden/') ? 'Hide thread' : 'Unhide thread',
            data: {
              cmd: 'hide',
              board_id: data.board_id,
              id: data.id
            }
          });
        }
        let thumb_img = document.getElementById('thumb-' + data.board_id + '-' + data.id);
        if (thumb_img != null && !thumb_img.src.includes('/static/')) {
          lis.push({
            type: 'li',
            text: 'Search: SauceNAO',
            data: {
              cmd: 'search_saucenao',
              board_id: data.board_id,
              id: data.id
            }
          }, {
            type: 'li',
            text: 'Search: IQDB',
            data: {
              cmd: 'search_iqdb',
              board_id: data.board_id,
              id: data.id
            }
          }, {
            type: 'li',
            text: 'Search: IQDB 3D',
            data: {
              cmd: 'search_iqdb3d',
              board_id: data.board_id,
              id: data.id
            }
          }, {
            type: 'li',
            text: 'Search: ASCII2D',
            data: {
              cmd: 'search_ascii2d',
              board_id: data.board_id,
              id: data.id
            }
          }, {
            type: 'li',
            text: 'Search: TinEye',
            data: {
              cmd: 'search_tineye',
              board_id: data.board_id,
              id: data.id
            }
          });
        }
        create_dropdown_menu(target, data.board_id, data.parent_id, data.id, rect, lis);
        break;
      default:
        break;
    }
  } else {
    delete_dropdown_menu(data.id);
  }
}

/**
 * Event listener: focus shifts from dropdown menu button.
 * Closes all dropdown menus.
 * @param {*} event 
 */
function listener_dropdown_menu_button_blur(event) {
  event.preventDefault();

  let target_related = event.relatedTarget;

  if (target_related == null) {
    delete_dropdown_menu();
  }
}

/**
 * Event listener: mouse over on post reference link.
 * Opens a preview.
 * @param {*} event 
 */
function listener_post_reference_link_mouseenter(event) {
  event.preventDefault();

  // update state
  state.mouse_over_post_ref_link = true;

  let target = event.target;
  let rect = target.getBoundingClientRect();
  let data = target.dataset;
  
  if (data.board_id == null || data.parent_id == null || data.id == null) {
    return;
  }

  let xhr = new XMLHttpRequest();
  xhr.onreadystatechange = function() {
    if (!state.mouse_over_post_ref_link) {
      xhr.abort();
      return;
    }

    if (xhr.readyState !== XMLHttpRequest.DONE) {
      return;
    }
    
    create_post_preview(target, data.board_id, data.parent_id, data.id, rect, xhr.responseText);
  }
  xhr.open('GET', '/' + data.board_id + '/' + data.parent_id + '/' + data.id, true);
  xhr.send();
}

/**
 * Event listener: mouse out from post reference link.
 * Closes all opened previews.
 * @param {*} event 
 */
function listener_post_reference_link_mouseleave(event) {
  event.preventDefault();

  // update state
  state.mouse_over_post_ref_link = false;

  delete_post_previews(event.target);
}

/**
 * Event listener: click on dropdown menu indice.
 * Executes menu action.
 * @param {*} event 
 */
 function listener_dropdown_menu_indice(event) {
  event.preventDefault();

  let target = event.target;
  let rect = target.getBoundingClientRect();
  let data = target.dataset;
  let thumb = document.getElementById('thumb-' + data.board_id + '-' + data.id);

  switch (data.cmd) {
    case 'report':
      open_window('/' + data.board_id + '/' + data.id + '/report', '_blank', 'location=true,status=true,width=480,height=640');
      break;
    case 'hide':
      let xhr = new XMLHttpRequest();
      xhr.onreadystatechange = function() {
        if (xhr.readyState !== XMLHttpRequest.DONE) {
          return;
        }

        let thread = document.getElementById('thread_' + data.board_id + '-' + data.id);
        if (thread != null) {
          let divider = thread.nextElementSibling;
          if (divider != null && divider.nodeName === 'HR') {
            divider.remove();
          }

          thread.remove();
        }
      };
      xhr.open('POST', '/' + data.board_id + '/' + data.id + '/hide', true);
      xhr.send();
      break;
    case 'search_saucenao':
      if (thumb != null) {
        open_window('https://saucenao.com/search.php?url=' + thumb.src, '_blank');
      }
      break;
    case 'search_iqdb':
      if (thumb != null) {
        open_window('http://iqdb.org/?url=' + thumb.src, '_blank');
      }
      break;
    case 'search_iqdb3d':
      if (thumb != null) {
        open_window('http://3d.iqdb.org/?url=' + thumb.src, '_blank');
      }
      break;
    case 'search_ascii2d':
      if (thumb != null) {
        open_window('https://ascii2d.net/search/url/' + thumb.src, '_blank');
      }
      break;
    case 'search_tineye':
      if (thumb != null) {
        open_window('https://tineye.com/search?url=' + thumb.src, '_blank');
      }
      break;
    default:
      console.error('listener_dropdown_menu_indice unhandled cmd: ' + data.cmd);
  }
  
  delete_dropdown_menu(data.id);
}

/**
 * Creates a new dropdown menu.
 * @param {string} board_id 
 * @param {number} id 
 * @param {Rect} rect 
 * @param {array} indices 
 */
function create_dropdown_menu(target, board_id, parent_id, id, rect, indices) {
  // create container element
  let div = document.createElement('div');
  div.dataset.board_id = board_id;
  div.dataset.parent_id = parent_id;
  div.dataset.id = id;
  div.classList.add('dd-menu');
  div.style.top = (rect.bottom + window.scrollY) + 'px';
  div.style.left = (rect.left + window.scrollX) + 'px';
  div.tabIndex = -1; // blur event hack

  // create list element
  let ul = document.createElement('ul');

  // create menu indice elements
  indices.forEach(indice => {
    switch (indice.type) {
      case 'li':
        let li = document.createElement('li');
        li.dataset.cmd = indice.data.cmd;
        li.dataset.board_id = indice.data.board_id;
        li.dataset.id = indice.data.id;
        li.innerHTML = indice.text;
        
        li.addEventListener('click', listener_dropdown_menu_indice);

        // append to list
        ul.appendChild(li);
        break;
    }
  });

  // append list to container
  div.appendChild(ul);

  // append container to body
  // NOTE: figure out why appending to target glitches out
  document.body.appendChild(div);

  // get initial container client rect
  let div_rect = div.getBoundingClientRect();

  // shift container up if overflow-y
  if (div_rect.bottom > window.innerHeight) {
    div.style.top = (rect.top + window.scrollY - div_rect.height) + 'px';
  }

  // shift container left if overflow-x
  div_rect = div.getBoundingClientRect();
  if (div_rect.right > window.innerWidth) {
    div.style.left = (rect.right + window.scrollX - div_rect.width) + 'px';
  }
}

/**
 * Creates a new post preview.
 * @param {string} board_id 
 * @param {number} parent_id 
 * @param {number} id 
 * @param {Rect} rect 
 * @param {array} content 
 */
function create_post_preview(target, board_id, parent_id, id, rect, content) {
  // get target bounding client rect
  let target_rect = target.getBoundingClientRect();

  // create container element
  let div = document.createElement('div');
  div.dataset.board_id = board_id;
  div.dataset.parent_id = parent_id;
  div.dataset.id = id;
  div.classList.add('post-preview');
  div.style.left = '0';
  div.style.top = '0';
  div.style.right = 'auto';
  div.style.bottom = 'auto';

  // append post HTML content
  div.innerHTML = content;

  // append container to target element
  target.appendChild(div);

  // get container bounding client rect
  let div_rect = div.getBoundingClientRect();

  // position container in viewport
  div.style.left = target_rect.right + 'px';
  div.style.top = (target_rect.bottom - div_rect.height * 0.5) + 'px';

  // overflow on y-axis: shift container up/down by overflow amount
  div_rect = div.getBoundingClientRect();
  if (div_rect.bottom > window.innerHeight) {
    let overflow_y = div_rect.bottom - window.innerHeight;
    div.style.top = (parseInt(div.style.top, 10) - overflow_y) + 'px';
  } else if (div_rect.top < 0) {
    let overflow_y = div_rect.top;
    div.style.top = (parseInt(div.style.top, 10) - overflow_y) + 'px';
  }

  // overflow on x-axis: shift container left/right by overflow amount
  div_rect = div.getBoundingClientRect();
  if (div_rect.right > window.innerWidth) {
    let overflow_x = div_rect.right - window.innerWidth;
    div.style.left = (parseInt(div.style.left, 10) - overflow_x) + 'px';
  } else if (div_rect.left < 0) {
    let overflow_x = div_rect.left;
    div.style.left = (parseInt(div.style.left, 10) - overflow_x) + 'px';
  }

  // recursively init container post ref links
  init_post_reference_links(div);
}

/**
 * Deletes an existing dropdown menu.
 * @param {number} id 
 */
function delete_dropdown_menu(id) {
  let dd_menus = document.getElementsByClassName('dd-menu');

  Array.from(dd_menus).forEach(element => {
    if (id == null || element.dataset.id === id) {
      element.remove();
    }
  });

  let dd_menu_btns = document.getElementsByClassName('dd-menu-btn');

  Array.from(dd_menu_btns).forEach(element => {
    if (id == null || element.dataset.id === id) {
      element.classList.remove('dd-menu-btn-open');
    }
  });
}

/**
 * Deletes all existing post previews.
 */
function delete_post_previews(target) {
  if (target == null) {
    target = document;
  }

  let post_previews = target.getElementsByClassName('post-preview');
  Array.from(post_previews).forEach(element => {
    element.remove();
  });
}

/**
 * Highlights a post.
 */
function create_post_highlight(id) {
  // cleanup old highlights
  let highlighted_elements = document.getElementsByClassName('highlight');

  Array.from(highlighted_elements).forEach(element => {
    element.classList.remove('highlight');
  });

  // add current highlight
  let post_element = document.getElementById(id);
  
  if (post_element != null && post_element.classList.contains('reply')) {
    post_element.classList.add('highlight');
  }
}

/**
 * Insert a post ref to the message.
 */
function insert_ref_to_message(id) {
  let postform_message = document.getElementById('form-post-message');

  if (postform_message == null) {
    return;
  }

  let text_idx = postform_message.selectionEnd;
  let text_val = postform_message.value;
  let text_ref = '>>' + id + '\n';
  postform_message.value = text_val.slice(0, text_idx) + text_ref + text_val.slice(text_idx);
  postform_message.setSelectionRange(text_idx + text_ref.length, text_idx + text_ref.length);
  postform_message.focus();
}

/**
 * Initializes all post file thumbnail hrefs.
 */
function init_post_thumb_links(target) {
  if (target == null) {
    target = document;
  }

  let post_thumb_links = document.getElementsByClassName('file-thumb-href');
  Array.from(post_thumb_links).forEach(element => {
    element.addEventListener('click', listener_post_thumb_link_click);
  });
}

/**
 * Initializes all dropdown menu buttons.
 */
function init_dropdown_menu_buttons(target) {
  if (target == null) {
    target = document;
  }

  let dd_menu_btns = document.getElementsByClassName('dd-menu-btn');
  Array.from(dd_menu_btns).forEach(element => {
    element.addEventListener('click', listener_dropdown_menu_button_click);
    element.addEventListener('blur', listener_dropdown_menu_button_blur);
  });
}

/**
 * Initializes all post reference links under target element.
 */
function init_post_reference_links(target) {
  if (target == null) {
    target = document;
  }

  let post_ref_links = target.getElementsByClassName('reference');
  Array.from(post_ref_links).forEach(element => {
    element.addEventListener('mouseenter', listener_post_reference_link_mouseenter);
    element.addEventListener('mouseleave', listener_post_reference_link_mouseleave);
  });
}

/**
 * Initializes all post backreference links under target element.
 */
function init_post_backreference_links(target) {
  if (target == null) {
    target = document;
  }

  // get all post elements
  let post_elements = target.getElementsByClassName('post');

  // create a lookup map of posts and array of {post, refs} objs
  let post_lookup = {};
  let post_ref_array = [];
  Array.from(post_elements).forEach(e => {
    // select the correct element from op|reply type of elements
    let post = e.id !== '' ? e : e.getElementsByClassName('reply')[0];

    // append post to the lookup map
    post_lookup[post.id] = post;

    // append to post_ref array (skip op post)
    let post_msg_element = post.getElementsByClassName('post-message')[0];
    let post_ref_elements = post_msg_element.getElementsByClassName('reference');
    if (post_ref_elements.length > 0) {
      post_ref_array.push({
        board_id: post.id.split('-')[0],
        parent_id: post.dataset.parent_id,
        post_id: post.id.split('-')[1],
        refs: post_ref_elements
      });
    }
  });

  // insert backreference links to posts
  for (let i = 0; i < post_ref_array.length; i++) {
    const post_ref_obj = post_ref_array[i];

    for (let j = 0; j < post_ref_obj.refs.length; j++) {
      // get ref (skip circular refs)
      const ref = post_ref_obj.refs[j];
      if (post_ref_obj.post_id == ref.dataset.id) {
        continue;
      }
      
      // get target post to append backref to (skip if not found)
      let backref_post = post_lookup[ref.dataset.board_id + '-' + ref.dataset.id];
      if (backref_post == null) {
        continue;
      }

      // construct the backreference element
      let backreference = document.createElement('a');
      backreference.classList.add('backreference');
      if (post_ref_obj.parent_id == null) {
        backreference.href = '/' + post_ref_obj.board_id + '/' + post_ref_obj.post_id + '/';
        backreference.dataset.board_id = post_ref_obj.board_id;
        backreference.dataset.parent_id = post_ref_obj.post_id;
        backreference.dataset.id = post_ref_obj.post_id;
      } else {
        backreference.href = '/' + post_ref_obj.board_id + '/' + post_ref_obj.parent_id + '/#' + post_ref_obj.board_id + '-' + post_ref_obj.post_id;
        backreference.dataset.board_id = post_ref_obj.board_id;
        backreference.dataset.parent_id = post_ref_obj.parent_id;
        backreference.dataset.id = post_ref_obj.post_id;
      }
      backreference.innerHTML = '>>' + post_ref_obj.post_id;
      backreference.addEventListener('mouseenter', listener_post_reference_link_mouseenter);
      backreference.addEventListener('mouseleave', listener_post_reference_link_mouseleave);

      // append to post-info section
      let backref_post_info = backref_post.getElementsByClassName('post-info')[0];
      backref_post_info.appendChild(backreference);
      backref_post_info.insertAdjacentHTML('beforeend', ' ');
    }
  }
}

/**
 * Initializes all post hashid fields with unique RGB color hash.
 */
function init_post_hashid_features() {
  let hashid_elements = document.getElementsByClassName('post-hashid-hash');
  Array.from(hashid_elements).forEach(element => {
    // calculate hashid bg color by simple hash to rgb
    const hid_bg = element.innerHTML.toHex();

    // calculate hashid bg color luminance
    const hid_bg_rgb = parseInt(hid_bg.substring(1), 16);
    const hid_bg_r = (hid_bg_rgb >> 16) & 0xff;
    const hid_bg_g = (hid_bg_rgb >> 8) & 0xff;
    const hid_bg_b = hid_bg_rgb & 0xff;
    const hid_bg_l = 0.2126 * hid_bg_r + 0.7152 * hid_bg_g + 0.0722 * hid_bg_b;

    // set the hashid bgcolor and also set font color based on luminance
    element.style.backgroundColor = hid_bg;
    element.style.color = hid_bg_l < 100 ? '#ffffff' : '#000000';
  });
}

/**
 * Initializes features related to interpreting location.hash value.
 * - Post highlights (#ID)
 * - Insert post ref link to postform message (#qID)
 */
function init_location_hash_features() {
  function highlight_or_ref(hash) {
    if (hash.startsWith('#q')) {
      insert_ref_to_message(hash.substring(2));
    } else {
      create_post_highlight(hash.substring(1));
    }
  }

  if (location.hash.length > 1) {
    highlight_or_ref(location.hash);
  }
  
  window.addEventListener('hashchange', function(event) {
    highlight_or_ref(location.hash);
  });
}

/**
 * Initializes features related to postform fields.
 * - Remember password (local cookie)
 */
function init_postform_features() {
  // update password fields appropriately
  let cookie_pass = get_cookie('password');
  let postform_pass = document.getElementById('form-post-password');
  let deleteform_pass = document.getElementById('deleteform-password');

  if (postform_pass != null) {
    if (cookie_pass != null) {
      postform_pass.value = cookie_pass;
      if (deleteform_pass != null) {
        deleteform_pass.value = cookie_pass;
      }
    }

    let cookie_pass_expires = new Date();
    cookie_pass_expires.setFullYear(cookie_pass_expires.getFullYear() + 10);
    postform_pass.addEventListener('input', function(event) {
      set_cookie('password', event.target.value, 'Strict', cookie_pass_expires);
      
      if (deleteform_pass != null) {
        deleteform_pass.value = event.target.value;
      }
    });
  }

  // setup submit handler
  let post_form = document.getElementById('form-post');
  if (post_form != null) {
    let submit_btn = post_form.querySelector('input[type=submit]');

    post_form.addEventListener('submit', (event) => {
      event.preventDefault();
      
      submit_btn.disabled = true;
      fetch(post_form.action, {
        method: 'POST',
        body: new FormData(post_form)
      }).then((data) => {
        data.json().then((response) => {
          // 200 OK, follow redirect
          if (data.status === 200 && response['redirect_url'] != null) {
            window.location.href = window.location.origin + response['redirect_url'];
            setTimeout(() => {
              window.location.reload(true);
            }, 250);
          // xxx ERROR, show error window
          } else {
            open_window('', '_blank', 'location=true,status=true,width=480,height=640')
              .document.write(response['error_message']);
            submit_btn.disabled = false;
          }
        });
      }).catch((error) => {
        open_window('', '_blank', 'location=true,status=true,width=480,height=640')
          .document.write(error);
        submit_btn.disabled = false;
      });
    });
  }
}

function init_stylepicker_features() {
  const stylepicker_element = document.getElementById('stylepicker');
  if (stylepicker_element == null) {
    return;
  }

  let style_expires = new Date();
  style_expires.setFullYear(style_expires.getFullYear() + 10);
  stylepicker_element.addEventListener('change', (event) => {
    set_cookie('style', event.target.value, 'Strict', style_expires);
    location.reload();
  });
}

document.addEventListener('DOMContentLoaded', function(event) {
  if (!location.pathname.includes('/catalog/') && !location.pathname.includes('/manage/')) {
    console.time('init_post_thumb_links');
    init_post_thumb_links();
    console.timeEnd('init_post_thumb_links');

    console.time('init_dropdown_menu_buttons');
    init_dropdown_menu_buttons();
    console.timeEnd('init_dropdown_menu_buttons');

    console.time('init_post_reference_links');
    init_post_reference_links();
    console.timeEnd('init_post_reference_links');

    console.time('init_post_backreference_links');
    init_post_backreference_links();
    console.timeEnd('init_post_backreference_links');

    console.time('init_post_hashid_features');
    init_post_hashid_features();
    console.timeEnd('init_post_hashid_features');

    console.time('init_location_hash_features');
    init_location_hash_features();
    console.timeEnd('init_location_hash_features');
  }
  
  console.time('init_postform_features');
  init_postform_features();
  console.timeEnd('init_postform_features');

  console.time('init_stylepicker_features');
  init_stylepicker_features();
  console.timeEnd('init_stylepicker_features');
});
