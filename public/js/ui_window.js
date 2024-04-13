/**
 * Opens a native browser window, center if possible.
 * @param {*} url 
 * @param {*} target 
 * @param {*} features 
 * @returns 
 */
function open_native(url, target, features) {
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
 * Opens a custom window with title bar and content.
 * @param {*} id 
 * @param {*} title 
 * @param {*} left 
 * @param {*} top 
 * @param {*} right 
 * @param {*} bottom 
 * @param {*} draggable 
 * @param {*} content 
 * @returns 
 */
function open(id, title, left, top, right, bottom, draggable, content) {
  let fixed_window = {
    element: document.createElement('div'),
    pos: {
      x: left != null ? left : right,
      y: top != null ? top : bottom,
    },
    mouse_down: false,
    mouse_offset: {
      x: 0.0,
      y: 0.0,
    }
  };
  fixed_window.setXY = function(x, y) {
    if (left != null) {
      left = x;
      top = y;
    } else {
      right = x;
      bottom = y;
    }

    fixed_window.pos = {
      x: x,
      y: y,
    };

    fixed_window.element.style.left = left != null ? left + 'px' : undefined;
    fixed_window.element.style.top = top != null ? top + 'px' : undefined;
    fixed_window.element.style.right = right != null ? right + 'px' : undefined;
    fixed_window.element.style.bottom = bottom != null ? bottom + 'px' : undefined;
  };

  fixed_window.element.id = id;
  fixed_window.element.style.position = 'fixed';
  fixed_window.setXY(left != null ? left : right, top != null ? top : bottom);
  fixed_window.element.classList.add('box-container');

  const div_box = document.createElement('div');
  div_box.style.display = 'block';
  div_box.classList.add('box');
  fixed_window.element.appendChild(div_box);

  const div_box_title = document.createElement('div');
  div_box_title.style.cursor = 'move';
  div_box_title.style.userSelect = 'none';
  div_box_title.classList.add('box-title');
  div_box_title.textContent = title;
  const close_anchor = document.createElement('a');
  close_anchor.text = 'x';
  close_anchor.href = '#';
  const click_handler = (event) => {
    event.preventDefault();

    fixed_window.element.remove();
  };
  close_anchor.addEventListener('click', click_handler);
  close_anchor.addEventListener('touchend', click_handler);
  const close_anchor_wrapper = document.createElement('div');
  close_anchor_wrapper.style.float = 'right';
  close_anchor_wrapper.appendChild(close_anchor);
  div_box_title.append(close_anchor_wrapper);
  div_box.appendChild(div_box_title);

  const div_box_content = document.createElement('div');
  div_box_content.classList.add('box-content');
  div_box_content.appendChild(content);
  div_box.appendChild(div_box_content);

  if (draggable === true) {
    const down_handler = (event) => {
      event.preventDefault();

      const clientX = event.clientX || event.touches[0].clientX;
      const clientY = event.clientY || event.touches[0].clientY;

      fixed_window.mouse_down = true;
      fixed_window.mouse_offset.x = clientX;
      fixed_window.mouse_offset.y = clientY;
    };

    const up_handler = (event) => {
      event.preventDefault();
      
      fixed_window.mouse_down = false;
    };

    const move_handler = (event) => {
      if (fixed_window.mouse_down) {
        event.preventDefault();

        const clientX = event.clientX || event.touches[0].clientX;
        const clientY = event.clientY || event.touches[0].clientY;

        if (left != null) {
          fixed_window.pos.x += clientX - fixed_window.mouse_offset.x;
          fixed_window.pos.y += clientY - fixed_window.mouse_offset.y;

          fixed_window.element.style.left = fixed_window.pos.x + 'px';
          fixed_window.element.style.top = fixed_window.pos.y + 'px';
        } else {
          fixed_window.pos.x += fixed_window.mouse_offset.x - clientX;
          fixed_window.pos.y += fixed_window.mouse_offset.y - clientY;

          fixed_window.element.style.right = fixed_window.pos.x + 'px';
          fixed_window.element.style.bottom = fixed_window.pos.y + 'px';
        }
        
        fixed_window.mouse_offset.x = clientX;
        fixed_window.mouse_offset.y = clientY;
      }
    };

    div_box_title.addEventListener('mousedown', down_handler);
    div_box_title.addEventListener('touchstart', down_handler);
    div_box_title.addEventListener('mouseup', up_handler);
    div_box_title.addEventListener('touchend', up_handler);
    document.addEventListener('mousemove', move_handler);
    document.addEventListener('touchmove', move_handler);
  }

  return fixed_window;
}

const ui_window = {
  open_native,
  open,
};

export default ui_window;
