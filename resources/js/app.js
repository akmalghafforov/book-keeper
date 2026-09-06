import './bootstrap';
import './client-select2-matcher';
import { initializeFormArrowNavigation } from './form-arrow-navigation';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();
initializeFormArrowNavigation();
