/**
 * Adds a "download thread" entry to the post dropdown menu of thread OPs.
 */
(function () {
    const CMD = 'thread_archive';
    const LABELS = {
        en: 'Download thread (.zip)',
        fi: 'Lataa lanka (.zip)',
        hu: 'Téma letöltése (.zip)',
        pl: 'Pobierz wątek (.zip)',
    };

    document.addEventListener('DOMContentLoaded', function(event) {
        if (window.miniboard === undefined || window.miniboard.add_hook === undefined) {
            console.error('archive plugin: miniboard public API not found');
            return;
        }

        window.miniboard.add_hook('post_menu.indices', function (lis, data) {
            if (data.parent_id != null) {
                return;
            }

            lis.push({
                type: 'li',
                text: LABELS[window.miniboard.current_lang] || LABELS.en,
                data: {
                    cmd: CMD,
                    board_id: data.board_id,
                    id: data.id,
                },
            });
        });

        window.miniboard.add_hook('post_menu.command', function (data) {
            if (data.cmd !== CMD) {
                return false;
            }

            window.location.assign('/' + data.board_id + '/' + data.id + '/archive');
            return true;
        });
    });
})();
