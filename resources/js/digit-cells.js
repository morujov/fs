/**
 * Digit-cells — прогрессивное улучшение поля поиска номера.
 *
 * Превращает <input data-digit-cells> в ряд из 9 ячеек (по цифре в каждой),
 * как на телефонных досках. Значение синхронизируется обратно в нативный
 * input, и форма сабмитит именно его — так поиск работает и без JS (тогда
 * это обычное текстовое поле), и краулится.
 *
 * Синтаксис поиска не меняется (NumberPatternQuery): цифры и '?' (любая
 * цифра). Пустая ячейка в середине = '?', хвостовые пустые отбрасываются:
 *   [2,1,1,_,_,_,_,_,_]      -> "211"        (начинается на 211)
 *   [6,_,_,1,2,_,_,3,4]      -> "6??12??34"
 */

const LEN = 9;

export function initDigitCells(root = document) {
    root.querySelectorAll('input[data-digit-cells]').forEach(enhance);
}

function enhance(input) {
    if (input.dataset.cellsReady) return;
    input.dataset.cellsReady = '1';

    const wrap = document.createElement('div');
    wrap.className = 'digit-cells';
    wrap.setAttribute('role', 'group');
    wrap.setAttribute('aria-label', input.getAttribute('aria-label') || input.getAttribute('placeholder') || '');
    input.parentNode.insertBefore(wrap, input);

    // Нативное поле — источник значения; прячем, но оставляем в DOM.
    input.classList.add('digit-cells-native');
    input.setAttribute('tabindex', '-1');
    input.setAttribute('aria-hidden', 'true');
    wrap.appendChild(input);

    const cells = [];
    for (let i = 0; i < LEN; i++) {
        const c = document.createElement('input');
        c.type = 'text';
        c.inputMode = 'numeric';
        c.maxLength = 1;
        c.autocomplete = 'off';
        c.className = 'digit-cell';
        c.setAttribute('aria-label', `${i + 1}`);
        cells.push(c);
        wrap.appendChild(c);
    }

    // Разложить стартовое значение по ячейкам.
    const start = (input.value || '').replace(/[^0-9?]/g, '').slice(0, LEN).split('');
    cells.forEach((c, i) => (c.value = start[i] || ''));

    const isAllowed = (ch) => /^[0-9?]$/.test(ch);

    function sync() {
        let last = -1;
        for (let i = 0; i < LEN; i++) if (cells[i].value !== '') last = i;
        let out = '';
        for (let i = 0; i <= last; i++) out += cells[i].value || '?';
        input.value = out;
    }

    function focusCell(i) {
        if (i >= 0 && i < LEN) {
            cells[i].focus();
            cells[i].select();
        }
    }

    cells.forEach((c, i) => {
        c.addEventListener('input', () => {
            // Берём последний введённый допустимый символ.
            const ch = c.value.slice(-1);
            c.value = isAllowed(ch) ? ch : '';
            sync();
            if (c.value !== '') focusCell(i + 1);
        });

        c.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace') {
                if (c.value === '') {
                    e.preventDefault();
                    focusCell(i - 1);
                    if (cells[i - 1]) cells[i - 1].value = '';
                    sync();
                } else {
                    c.value = '';
                    sync();
                    e.preventDefault();
                }
            } else if (e.key === 'ArrowLeft') {
                e.preventDefault();
                focusCell(i - 1);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                focusCell(i + 1);
            }
        });

        c.addEventListener('paste', (e) => {
            e.preventDefault();
            const digits = (e.clipboardData.getData('text') || '').replace(/[^0-9?]/g, '').split('');
            let j = i;
            for (const d of digits) {
                if (j >= LEN) break;
                cells[j].value = d;
                j++;
            }
            sync();
            focusCell(Math.min(j, LEN - 1));
        });
    });
}
