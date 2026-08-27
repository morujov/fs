import { initComboboxes } from './combobox';
import { initDigitCells } from './digit-cells';

// Прогрессивные улучшения. Всё, что здесь, — надстройка над рабочим SSR:
// страница живёт и без этого файла.
document.addEventListener('DOMContentLoaded', () => {
    initComboboxes();
    initDigitCells();
});
