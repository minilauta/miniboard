(function() {
    const lemmings = [];
    let TIME = 0.0;

    function spawn_lemming() {
        const elements = Array.from(document.querySelectorAll('.banner > a > img,.post-catalog,.reply-container,.box-title'));
        const target = elements[Math.floor(Math.random() * elements.length)];

        if (target != null) {
            const target_rect = target.getBoundingClientRect();

            let img = document.createElement('img');
            img.src = '/plugins/winter2025/lemmingwalk.gif';
            img.width = 6;
            img.height = 10;
            img.style.imageRendering = 'pixelated';
            img.style.width = '100%';
            img.style.height = '100%';

            let div = document.createElement('div');
            div.style.position = 'absolute';
            div.style.width = '12px';
            div.style.height = '20px';
            div.style.bottom = 'auto';
            div.style.right = 'auto';

            const lemming_dir = Math.random() > 0.5 ? 'right' : 'left';
            if (lemming_dir === 'left') img.style.transform = 'scaleX(-1)';

            div.appendChild(img);

            lemmings.push({
                s: 'walk',
                d: lemming_dir,
                x: Math.random() * target_rect.width,
                y: -20,
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

    function process_lemmings(container) {
        lemmings.forEach((lemming, index, object) => {
            const target_rect = lemming.target.getBoundingClientRect();

            switch (lemming.s) {
                case 'walk': {
                    lemming.tx = target_rect.left + window.scrollX;
                    lemming.ty = target_rect.top + window.scrollY;
                    lemming.tw = target_rect.width;
                    lemming.th = target_rect.height;

                    if (lemming.d === 'right') {
                        lemming.x += 0.25;
                        if (lemming.x > lemming.tw) lemming.s = 'fall';
                    } else {
                        lemming.x -= 0.25;
                        if (lemming.x < -6) lemming.s = 'fall';
                    }
                } break;
                case 'fall': {
                    if (lemming.y > 0) {
                        lemming.x += Math.cos(lemming.r + TIME) * 0.5;
                        if (!lemming.img.src.endsWith('lemmingfallchute.gif')) {
                            lemming.img.src = '/plugins/winter2025/lemmingfallchute.gif';
                        }
                    } else if (!lemming.img.src.endsWith('lemmingopenchute.gif')) {
                        lemming.img.src = '/plugins/winter2025/lemmingopenchute.gif';
                    }
                    lemming.y += 0.5;
                    if (lemming.t > lemming.l ||
                        (lemming.ty + lemming.y + 20) > document.body.scrollHeight ||
                        (lemming.tx + lemming.x + 12) > document.body.scrollWidth ||
                        (lemming.tx + lemming.x) <= 0
                    ) {
                        container.removeChild(lemming.div);
                        object.splice(index, 1);
                    }
                    lemming.t += 1.0 / 60;
                } break;
            }

            lemming.div.style.top = lemming.ty + lemming.y + 'px';
            lemming.div.style.left = lemming.tx + lemming.x + 'px';

            if (lemming.cnt == null) {
                container.appendChild(lemming.div);
                lemming.cnt = container;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function(event) {
        const container = document.createElement('div');
        container.id = 'winter2025';
        document.body.appendChild(container);

        setInterval(() => {
            spawn_lemming(container);
        }, 1000);
        setInterval(() => {
            process_lemmings(container);
            TIME += 1.0 / 60;
        }, 16);
    });
})();
