/**
 * Event listener: click on dropdown menu button.
 * Opens/closes the menu.
 * @param {*} event 
 */
function listener_dropdown_menu_button(event) {
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
            text: !window.location.pathname.includes('/hidden/') ? 'Hide thread' : 'Unhide thread',
            data: {
              cmd: 'hide',
              board_id: data.board_id,
              id: data.id
            }
          });
        }
        create_dropdown_menu(data.board_id, data.id, rect, lis);
        break;
      default:
        break;
    }
  } else {
    target.classList.remove('dd-menu-btn-open');

    delete_dropdown_menu(data.id);
  }
}

/**
 * Event listener: mouse over on post reference link.
 * Opens a preview.
 * @param {*} event 
 */
function listener_post_reference_link_mouseover(event) {
  event.preventDefault();

  let target = event.target;
  let rect = target.getBoundingClientRect();
  let data = target.dataset;
  
  if (data.board_id == null || data.parent_id == null || data.id == null) {
    return;
  }

  let xhr = new XMLHttpRequest();
  xhr.onreadystatechange = function() {
    if (xhr.readyState !== XMLHttpRequest.DONE) {
      return;
    }
  
    create_post_preview(data.board_id, data.parent_id, data.id, rect, xhr.responseText);
  }
  xhr.open('GET', '/' + data.board_id + '/' + data.parent_id + '/' + data.id, true);
  xhr.send();
}

/**
 * Event listener: mouse out from post reference link.
 * Closes all opened previews.
 * @param {*} event 
 */
function listener_post_reference_link_mouseout(event) {
  event.preventDefault();

  let post_previews = document.getElementsByClassName('post-preview');
  Array.from(post_previews).forEach(element => {
    element.remove();
  });
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
function create_dropdown_menu(board_id, id, rect, indices) {
  // create container element
  let div = document.createElement('div');
  div.dataset.board_id = board_id;
  div.dataset.id = id;
  div.classList.add('dd-menu');
  div.style.top = (rect.bottom + window.scrollY) + 'px';
  div.style.left = (rect.left + window.scrollX) + 'px';

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
  document.body.appendChild(div);

  // shift container up if overflow-y
  let div_rect = div.getBoundingClientRect();
  if (div_rect.bottom > window.innerHeight) {
    div.style.top = (rect.top + window.scrollY - div_rect.height) + 'px';
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
function create_post_preview(board_id, parent_id, id, rect, content) {
  // create container element
  let div = document.createElement('div');
  div.dataset.board_id = board_id;
  div.dataset.parent_id = parent_id;
  div.dataset.id = id;
  div.classList.add('post-preview');
  div.style.top = (rect.bottom + window.scrollY) + 'px';
  div.style.left = (rect.left + window.scrollX) + 'px';

  // append post HTML content
  div.innerHTML = content;

  // append container to body
  document.body.appendChild(div);

  // shift container up if overflow-y
  let div_rect = div.getBoundingClientRect();
  if (div_rect.bottom > window.innerHeight) {
    div.style.top = (rect.top + window.scrollY - div_rect.height) + 'px';
  }
}

/**
 * Deletes an existing dropdown menu.
 * @param {number} id 
 */
function delete_dropdown_menu(id) {
  let dd_menus = document.getElementsByClassName('dd-menu');

  Array.from(dd_menus).forEach(element => {
    if (element.dataset.id === id) {
      element.remove();
    }
  });
}

/**
 * Initializes all dropdown menu buttons.
 */
function init_dropdown_menu_buttons() {
  let dd_menu_btns = document.getElementsByClassName('dd-menu-btn');

  Array.from(dd_menu_btns).forEach(element => {
    element.addEventListener('click', listener_dropdown_menu_button);
  });
}

/**
 * Initializes all post reference links.
 */
function init_post_reference_links() {
  let post_ref_links = document.getElementsByClassName('reference');

  Array.from(post_ref_links).forEach(element => {
    element.addEventListener('mouseover', listener_post_reference_link_mouseover);
    element.addEventListener('mouseout', listener_post_reference_link_mouseout);
  });
}

document.addEventListener('DOMContentLoaded', function(event) {
  init_dropdown_menu_buttons();
  init_post_reference_links();
});
