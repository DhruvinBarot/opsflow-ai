import Alpine from 'alpinejs';
import { fmt } from './lib/format';
import { registerStores } from './stores';
import { overview } from './pages/overview';
import { requestForm, requestList } from './pages/requests';
import { requestDetail } from './pages/request-detail';
import { adminConsole, reviewQueue } from './pages/operations';

registerStores(Alpine);

Alpine.magic('fmt', () => fmt);

Alpine.data('overview', overview);
Alpine.data('requestList', requestList);
Alpine.data('requestForm', requestForm);
Alpine.data('requestDetail', requestDetail);
Alpine.data('reviewQueue', reviewQueue);
Alpine.data('adminConsole', adminConsole);

/* Landing-page mobile navigation, ported from the 21st "Hero Section 6" nav pattern. */
Alpine.data('siteNav', () => ({
    open: false,
    toggle() {
        this.open = !this.open;
    },
}));

window.Alpine = Alpine;
Alpine.start();
