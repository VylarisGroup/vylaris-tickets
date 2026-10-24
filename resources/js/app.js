import Alpine from 'alpinejs';
import cart from './cart';
import seatEditor from './seat-editor';
import scanner from './scanner';
import { themeToggle } from './theme';
import { installHint, registerServiceWorker } from './pwa';

window.Alpine = Alpine;
Alpine.data('shop', cart);
Alpine.data('seatEditor', seatEditor);
Alpine.data('scanner', scanner);
Alpine.data('installHint', installHint);
Alpine.data('themeToggle', themeToggle);
registerServiceWorker();
Alpine.start();
