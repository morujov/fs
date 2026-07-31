/**
 * Combobox — прогрессивное улучшение нативного <select data-combobox>.
 *
 * Зачем: список из 52 провинций в <select> неудобно листать. Здесь поверх него
 * рисуется поле с фильтрацией по вводу (нечувствительной к диакритике: «malaga»
 * находит «Málaga»).
 *
 * Инварианты:
 *  - SSR/no-JS floor: нативный <select> остаётся в DOM (краулимо, работает без
 *    JS). Улучшение навешивается только если JS есть; значение всегда живёт в
 *    самом <select>, форма сабмитит его — combobox только «перекрашивает».
 *  - Ноль зависимостей: без Alpine и любых библиотек (zero-CDN, локальная сборка).
 */

// Свернуть регистр и диакритику: «Málaga» → «malaga», чтобы поиск по латинице
// находил акцентированные имена.
const fold = (s) =>
    s.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();

export function initComboboxes(root = document) {
    root.querySelectorAll('select[data-combobox]').forEach(enhance);
}

function enhance(select) {
    if (select.dataset.comboReady) return;
    select.dataset.comboReady = '1';

    const options = Array.from(select.options).map((o) => ({
        value: o.value,
        label: o.textContent.trim(),
    }));
    const hasEmpty = options.some((o) => o.value === '');
    const placeholder = select.dataset.placeholder || '';
    const noMatch = select.dataset.nomatch || '—';

    const wrap = document.createElement('div');
    wrap.className = 'combobox';
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);
    select.classList.add('combobox-native');
    select.setAttribute('tabindex', '-1');
    select.setAttribute('aria-hidden', 'true');

    const listId = 'cb-' + (select.id || Math.random().toString(36).slice(2));

    const input = document.createElement('input');
    input.type = 'text';
    input.autocomplete = 'off';
    input.className = 'combobox-input ' + select.className.replace('combobox-native', '').trim();
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', listId);
    input.placeholder = placeholder;
    if (select.id) {
        input.id = select.id + '-combo';
        const label = document.querySelector(`label[for="${select.id}"]`);
        if (label) label.setAttribute('for', input.id);
    }

    const list = document.createElement('ul');
    list.id = listId;
    list.className = 'combobox-list';
    list.setAttribute('role', 'listbox');
    list.hidden = true;

    wrap.append(input, list);

    let filtered = [];
    let active = -1;

    const currentLabel = () => {
        const sel = options.find((o) => o.value === select.value);
        return sel && sel.value !== '' ? sel.label : '';
    };
    input.value = currentLabel();

    function render(query) {
        const q = fold(query.trim());
        filtered = options.filter(
            (o) => o.value !== '' && (q === '' || fold(o.label).includes(q))
        );
        list.innerHTML = '';
        if (filtered.length === 0) {
            const li = document.createElement('li');
            li.className = 'combobox-empty';
            li.textContent = noMatch;
            list.appendChild(li);
        } else {
            filtered.forEach((o, i) => {
                const li = document.createElement('li');
                li.className = 'combobox-option';
                li.id = listId + '-o' + i;
                li.setAttribute('role', 'option');
                li.textContent = o.label;
                if (o.value === select.value) li.setAttribute('aria-selected', 'true');
                li.addEventListener('mousedown', (e) => {
                    e.preventDefault(); // не дать input потерять фокус до выбора
                    choose(o);
                });
                list.appendChild(li);
            });
        }
        active = -1;
    }

    function open() {
        if (list.hidden) {
            render(input.value === currentLabel() ? '' : input.value);
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }
    }
    function close() {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    }
    function setActive(i) {
        const items = list.querySelectorAll('.combobox-option');
        items.forEach((el) => el.classList.remove('is-active'));
        if (i >= 0 && i < items.length) {
            items[i].classList.add('is-active');
            items[i].scrollIntoView({ block: 'nearest' });
            input.setAttribute('aria-activedescendant', items[i].id);
            active = i;
        }
    }
    function choose(o) {
        select.value = o.value;
        input.value = o.value === '' ? '' : o.label;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        close();
    }
    function commitTyped() {
        // Ввод не выбран из списка: если пусто и есть «любая» — сбрасываем в неё,
        // иначе возвращаем поле к текущему валидному выбору (не даём мусор).
        if (input.value.trim() === '' && hasEmpty) {
            choose({ value: '', label: '' });
        } else {
            input.value = currentLabel();
        }
    }

    input.addEventListener('focus', open);
    input.addEventListener('input', () => {
        render(input.value);
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    });
    input.addEventListener('keydown', (e) => {
        const items = list.querySelectorAll('.combobox-option');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (list.hidden) open();
            setActive(Math.min(active + 1, items.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive(Math.max(active - 1, 0));
        } else if (e.key === 'Enter') {
            if (!list.hidden && active >= 0 && filtered[active]) {
                e.preventDefault();
                choose(filtered[active]);
            }
        } else if (e.key === 'Escape') {
            close();
        }
    });
    input.addEventListener('blur', () => {
        // Небольшая задержка: mousedown по опции должен успеть отработать.
        setTimeout(() => {
            if (!list.hidden) close();
            commitTyped();
        }, 120);
    });

    document.addEventListener('click', (e) => {
        if (!wrap.contains(e.target)) close();
    });
}
