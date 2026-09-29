import Alpine from 'alpinejs';
import { kataBoxes } from './kata-boxes.js';
import { nameReview } from './name-review.js';
import { themeToggle } from './theme.js';

Alpine.data('themeToggle', themeToggle);
Alpine.data('kataBoxes', kataBoxes);
Alpine.data('nameReview', nameReview);

window.Alpine = Alpine;
Alpine.start();
