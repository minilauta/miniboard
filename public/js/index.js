function create_dropdown_menu(id, top, left, indices) {
  // create container element
  let div = document.createElement('div');
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
        li.dataset.id = indice.data.id;
        li.innerHTML = indice.text;

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

function delete_dropdown_menu(id) {
  let dd_menus = document.getElementsByClassName('dd-menu');

  Array.from(dd_menus).forEach(element => {
    if (element.dataset.id === id) {
      element.remove();
    }
  });
}

function init_dropdown_menu_listeners() {
  let dd_menu_btns = document.getElementsByClassName('dd-menu-btn');

  Array.from(dd_menu_btns).forEach(element => {
    element.addEventListener('click', function(event) {
      let target = event.target;
      let rect = target.getBoundingClientRect();
      let data = target.dataset;

      // open or close the menu
      if (!target.classList.contains('dd-menu-btn-open')) {
        target.classList.add('dd-menu-btn-open');

        switch (data.cmd) {
          case 'post-menu':
            create_dropdown_menu(data.id, rect.bottom, rect.left, [
              {
                type: 'li',
                text: 'Report post',
                data: {
                  cmd: 'report',
                  id: data.id
                }
              },
              {
                type: 'li',
                text: 'Hide post',
                data: {
                  cmd: 'hide',
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
    });
  });
}

document.addEventListener('DOMContentLoaded', function(event) {
  init_dropdown_menu_listeners();
});
