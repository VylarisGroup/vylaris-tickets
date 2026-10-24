import Alpine from 'alpinejs';
import cart from './cart';
import seatEditor from './seat-editor';
import scanner from './scanner';
import scanDisplay from './scan-display';
import eventWizard from './wizard';
import { themeToggle } from './theme';
import { installHint, registerServiceWorker } from './pwa';

window.Alpine = Alpine;
Alpine.data('shop', cart);
Alpine.data('seatEditor', seatEditor);
Alpine.data('scanner', scanner);
Alpine.data('scanDisplay', scanDisplay);
Alpine.data('installHint', installHint);
Alpine.data('themeToggle', themeToggle);
Alpine.data('eventWizard', eventWizard);
registerServiceWorker();
Alpine.start();
