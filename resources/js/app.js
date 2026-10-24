import Alpine from 'alpinejs';
import cart from './cart';
import seatEditor from './seat-editor';

window.Alpine = Alpine;
Alpine.data('shop', cart);
Alpine.data('seatEditor', seatEditor);
Alpine.start();
