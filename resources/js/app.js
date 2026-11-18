import datePicker from './date-picker.js';
import openExternalLinksInBrowser from './desktop.js';
import richEditor from './editor.js';
import openPaddleCheckout from './paddle-checkout.js';

window.openPaddleCheckout = openPaddleCheckout;

openExternalLinksInBrowser();

document.addEventListener('alpine:init', () => {
    window.Alpine.data('datePicker', datePicker);
    window.Alpine.data('richEditor', richEditor);
});
