import { initComboboxes } from './combobox';

// Прогрессивные улучшения. Всё, что здесь, — надстройка над рабочим SSR:
// страница живёт и без этого файла.
document.addEventListener('DOMContentLoaded', () => {
    initComboboxes();
});
