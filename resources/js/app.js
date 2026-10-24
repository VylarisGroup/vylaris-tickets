import Alpine from 'alpinejs';
import cart from './cart';
import seatEditor from './seat-editor';
import scanner from './scanner';

window.Alpine = Alpine;
Alpine.data('shop', cart);
Alpine.data('seatEditor', seatEditor);
Alpine.data('scanner', scanner);
Alpine.start();
