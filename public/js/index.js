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
        create_dropdown_menu(data.board, data.id, rect.bottom + window.scrollY, rect.left + window.scrollX, [
          {
            type: 'li',
            text: 'Report post',
            data: {
              cmd: 'report',
              board: data.board,
              id: data.id
            }
          },
          {
            type: 'li',
            text: 'Hide post',
            data: {
              cmd: 'hide',
              board: data.board,
              id: data.id
            }
          }
        ]);
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
      let report_window = window.open('/' + data.board + '/' + data.id + '/report', '_blank', 'location=true,status=true,width=480,height=640');
      break;
    case 'hide':
      break;
  }

  delete_dropdown_menu(data.id);
}

/**
 * Creates a new dropdown menu.
 * @param {string} board 
 * @param {number} id 
 * @param {number} top 
 * @param {number} left 
 * @param {array} indices 
 */
function create_dropdown_menu(board, id, top, left, indices) {
  // create container element
  let div = document.createElement('div');
  div.dataset.board = board;
  div.dataset.id = id;
  div.classList.add('dd-menu');
  div.style.top = top + 'px';
  div.style.left = left + 'px';

  // create list element
  let ul = document.createElement('ul');

  // create menu indice elements
  indices.forEach(indice => {
    switch (indice.type) {
      case 'li':
        let li = document.createElement('li');
        li.dataset.cmd = indice.data.cmd;
        li.dataset.board = indice.data.board;
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

document.addEventListener('DOMContentLoaded', function(event) {
  init_dropdown_menu_buttons();
});
