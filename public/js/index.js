// app state
var state = {
  mouse_over_post_ref_link: false
};

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
        let thumb_img = document.getElementById('thumb-' + data.id);
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

  delete_post_previews();
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
  let thumb = document.getElementById('thumb-' + data.id);

  switch (data.cmd) {
    case 'report':
      window.open('/' + data.board_id + '/' + data.id + '/report', '_blank', 'location=true,status=true,width=480,height=640');
      break;
    case 'hide':
      let xhr = new XMLHttpRequest();
      xhr.onreadystatechange = function() {
        if (xhr.readyState !== XMLHttpRequest.DONE) {
          return;
        }

        let post = document.getElementById(data.id);
        if (post != null) {
          if (post.parentElement.classList.contains('reply')) {
            post.parentElement.remove();
          } else {
            post.remove();
          }
        }
      };
      xhr.open('POST', '/' + data.board_id + '/' + data.id + '/hide', true);
      xhr.send();
      break;
    case 'search_saucenao':
      if (thumb != null) {
        window.open('https://saucenao.com/search.php?url=' + thumb.src, '_blank');
      }
      break;
    case 'search_iqdb':
      if (thumb != null) {
        window.open('http://iqdb.org/?url=' + thumb.src, '_blank');
      }
      break;
    case 'search_iqdb3d':
      if (thumb != null) {
        window.open('http://3d.iqdb.org/?url=' + thumb.src, '_blank');
      }
      break;
    case 'search_ascii2d':
      if (thumb != null) {
        window.open('https://ascii2d.net/search/url/' + thumb.src, '_blank');
      }
      break;
    case 'search_tineye':
      if (thumb != null) {
        window.open('https://tineye.com/search?url=' + thumb.src, '_blank');
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
  // create container element
  let div = document.createElement('div');
  div.dataset.board_id = board_id;
  div.dataset.parent_id = parent_id;
  div.dataset.id = id;
  div.classList.add('post-preview');
  div.style.left = (rect.right + window.scrollX) + 'px';

  // append post HTML content
  div.innerHTML = content;

  // append container to target element
  target.appendChild(div);

  // get initial container client rect
  let div_rect = div.getBoundingClientRect();

  // position container next to target
  div.style.top = (rect.bottom + 0.5 * (div_rect.top - div_rect.bottom) + window.scrollY) + 'px';

  // overflow on y-axis: shift container up/down by overflow amount
  div_rect = div.getBoundingClientRect();
  if (div_rect.bottom > window.innerHeight) {
    let overflow_y = div_rect.bottom - window.innerHeight;
    div.style.top = (parseInt(div.style.top, 10) - overflow_y) + 'px';
  } else if (div_rect.top < 0) {
    let overflow_y = div_rect.top;
    div.style.top = (parseInt(div.style.top, 10) - overflow_y) + 'px';
  }
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
function delete_post_previews() {
  let post_previews = document.getElementsByClassName('post-preview');

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
  let postform_message = document.getElementById('postform-message');

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
 * Initializes all dropdown menu buttons.
 */
function init_dropdown_menu_buttons() {
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

document.addEventListener('DOMContentLoaded', function(event) {
  init_dropdown_menu_buttons();
  init_post_reference_links();
  init_location_hash_features();
});
