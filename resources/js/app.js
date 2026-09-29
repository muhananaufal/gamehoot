import Alpine from 'alpinejs';
import { themeToggle } from './theme.js';

Alpine.data('themeToggle', themeToggle);

window.Alpine = Alpine;
Alpine.start();
