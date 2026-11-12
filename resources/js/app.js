import datePicker from './date-picker.js';
import richEditor from './editor.js';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('datePicker', datePicker);
    window.Alpine.data('richEditor', richEditor);
});
