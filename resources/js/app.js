import Alpine from 'alpinejs';
import { kataBoxes } from './kata-boxes.js';
import { themeToggle } from './theme.js';

Alpine.data('themeToggle', themeToggle);
Alpine.data('kataBoxes', kataBoxes);

window.Alpine = Alpine;
Alpine.start();
