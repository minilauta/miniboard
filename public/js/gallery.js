const IMAGE_FILE_EXTS = ['.png', '.jpg', '.jpeg', '.bmp', '.gif', '.webp'];

var state = {
  figure_size: 20,
};

function createGallery() {
  // create gallery container
  const e_gallery_container = document.createElement('div');
  e_gallery_container.id = 'gallery-container';
  e_gallery_container.classList.add('gallery-container');

  // get all image elements on the current page
  const images = Array.from(document.getElementsByClassName('file-thumb-href'))
    .filter(image => IMAGE_FILE_EXTS.some(ext => image.href.toLowerCase().endsWith(ext)))
    .map(image => image.href);

  const load_images = () => {
    const e_fragment = document.createDocumentFragment();
    for (const image of images) {
      const e_figure = document.createElement('figure');
      e_figure.classList.add('gallery-item');
      e_figure.style.width = state.figure_size + '%';
      e_figure.style.height = state.figure_size + '%';
      const e_anchor = document.createElement('a');
      e_anchor.href = image;
      e_anchor.target = '_blank';
      const e_img = document.createElement('img');
      e_img.classList.add('gallery-image');
      e_img.src = image;
      e_anchor.appendChild(e_img);
      e_figure.appendChild(e_anchor);
      e_fragment.appendChild(e_figure);
    }
    e_gallery_container.replaceChildren(...e_fragment.childNodes);
  }

  load_images();

  e_gallery_container.addEventListener('wheel', (event) => {
    if (!event.ctrlKey) {
      return;
    }

    event.preventDefault();

    if (event.deltaY > 0) {
      state.figure_size -= 1;
    } else if (event.deltaY < 0) {
      state.figure_size += 1;
    }

    const e_figures = document.getElementsByClassName('gallery-item');
    Array.from(e_figures).forEach((e_figure) => {
      e_figure.style.width = state.figure_size + '%';
      e_figure.style.height = state.figure_size + '%';
    });
  });

  return e_gallery_container;
}

export {
  createGallery,
};
