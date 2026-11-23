import './bootstrap';
import './client-select2-matcher';
import { initializeFormArrowNavigation } from './form-arrow-navigation';
import { draftMessage, draftReview, draftHistory } from './transaction-drafts';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.data('draftMessage', draftMessage);
Alpine.data('draftReview', draftReview);
Alpine.data('draftHistory', draftHistory);
Alpine.start();
initializeFormArrowNavigation();
