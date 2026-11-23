import { formatDraftAmount } from './form-amount';
import './bootstrap';
import './client-select2-matcher';
import { initializeFormArrowNavigation } from './form-arrow-navigation';
import { draftMessage, draftReview, draftHistory, missingPicker, missingSelect } from './transaction-drafts';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.data('draftMessage', draftMessage);
Alpine.data('draftReview', draftReview);
Alpine.data('draftHistory', draftHistory);
Alpine.data('missingPicker', missingPicker);
Alpine.data('missingSelect', missingSelect);
window.formatDraftAmount = formatDraftAmount;
Alpine.start();
initializeFormArrowNavigation();
