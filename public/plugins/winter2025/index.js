(function() {
    const lemmings = [];
    const LEMMING_S = 2;
    let TIME = 0.0;

    function spawn_lemming() {
        const elements = Array.from(document.querySelectorAll('.banner > a > img,.post-catalog,.reply-container,.box-title'));
        const target = elements[Math.floor(Math.random() * elements.length)];

        if (target != null) {
            const target_rect = target.getBoundingClientRect();

            let img = document.createElement('img');
            img.width = 0;
            img.height = 0;
            img.style.imageRendering = 'pixelated';
            img.style.width = '100%';
            img.style.height = '100%';

            let div = document.createElement('div');
            div.style.position = 'absolute';
            div.style.width = '0px';
            div.style.height = '0px';
            div.style.bottom = 'auto';
            div.style.right = 'auto';

            div.appendChild(img);

            lemmings.push({
                id: target.id,
                s: 'walk',
                d: Math.random() > 0.5 ? 'right' : 'left',
                x: Math.random() * target_rect.width,
                y: LEMMING_S * -10,
                tx: target_rect.left + window.scrollX,
                ty: target_rect.top + window.scrollY,
                tw: target_rect.width,
                th: target_rect.height,
                r: Math.random() * Math.PI,
                t: 0.0,
                l: 10 + Math.random() * 25,
                target: target,
                div: div,
                img: img,
                cnt: null,
            });
        }
    }

    function update_lemming(lemming, index, array) {
        const target_rect = lemming.target.getBoundingClientRect();

        switch (lemming.s) {
            case 'walk': {
                lemming.tx = target_rect.left + window.scrollX;
                lemming.ty = target_rect.top + window.scrollY;
                lemming.tw = target_rect.width;
                lemming.th = target_rect.height;

                if (lemming.d === 'right') {
                    lemming.x += LEMMING_S * 0.125;
                    if (lemming.x > lemming.tw) lemming.s = 'openchute';
                } else {
                    lemming.x -= LEMMING_S * 0.125;
                    if (lemming.x < LEMMING_S * -6) lemming.s = 'openchute';
                }
            } break;
            case 'openchute': {
                if (lemming.y > 0) lemming.s = 'fall';
                lemming.y += LEMMING_S * 0.125;
            } break;
            case 'fall': {
                if (lemming.y > 0) {
                    lemming.x += Math.cos(lemming.r + TIME) * 0.5;
                }
                lemming.y += LEMMING_S * 0.25;
                if (lemming.t > lemming.l ||
                    (lemming.ty + lemming.y + LEMMING_S * 10) >= document.body.scrollHeight + 32 ||
                    (lemming.tx + lemming.x + LEMMING_S * 6) >= document.body.scrollWidth + 32 ||
                    (lemming.tx + lemming.x) <= -32
                ) {
                    lemming.cnt.removeChild(lemming.div);
                    array.splice(index, 1);
                }
                lemming.t += 1.0 / 60;
            } break;
        }
    }

    function set_lemming_sprite(lemming, sprite, w, h) {
        let changed = false;
        if (!lemming.img.src.endsWith(sprite)) {
            lemming.img.src = '/plugins/winter2025/' + sprite;
            changed = true;
        }
        if (lemming.d === 'left' && !lemming.img.style.transform) {
            lemming.img.style.transform = 'scaleX(-1)';
            changed = true;
        } else if (lemming.d === 'right' && lemming.img.style.transform) {
            lemming.img.style.transform = '';
            changed = true;
        }
        if (changed) {
            lemming.img.width = w;
            lemming.img.height = h;
            lemming.div.style.width = (LEMMING_S * w) + 'px';
            lemming.div.style.height = (LEMMING_S * h) + 'px';
        }
    }

    function draw_lemming(lemming, index, array) {
        switch (lemming.s) {
            case 'walk': {
                set_lemming_sprite(lemming, 'lemmingwalk.gif', 6, 10);
            } break;
            case 'openchute': {
                set_lemming_sprite(lemming, 'lemmingopenchute.gif', 9, 15);
            } break;
            case 'fall': {
                set_lemming_sprite(lemming, 'lemmingfallchute.gif', 9, 16);
            } break;
        }

        lemming.div.style.top = lemming.ty + lemming.y + 'px';
        lemming.div.style.left = lemming.tx + lemming.x + 'px';
    }

    document.addEventListener('DOMContentLoaded', function(event) {
        const container = document.createElement('div');
        container.id = 'winter2025';
        container.style.pointerEvents = 'none';
        container.style.position = 'absolute';
        container.style.top = '0px';
        container.style.left = '0px';
        container.style.width = '100%';
        container.style.height = '100%';
        container.style.overflow = 'hidden';
        document.body.appendChild(container);

        setInterval(() => {
            spawn_lemming(container);
        }, 1000);
        setInterval(() => {
            lemmings.forEach((lemming, index, array) => {
                update_lemming(lemming, index, array);
                draw_lemming(lemming, index, array);
                if (lemming.cnt == null) {
                    container.appendChild(lemming.div);
                    lemming.cnt = container;
                }
            });
            TIME += 1.0 / 60;
        }, 16);
    });
})();
